<?php
/**
 * Daily Expense Tracker V2 - Budget Management
 * Allows users to set, monitor, edit, and delete monthly budgets.
 */

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_login();

$user_id = get_user_id();

$page_title = "Budget";
$page_css = ["budget"];

/* Selected Month & Navigation Calculation */

$selected_month = $_GET["month"] ?? date("Y-m");

if (!preg_match("/^\d{4}-\d{2}$/", $selected_month)) {
    $selected_month = date("Y-m");
}

$month_start = $selected_month . "-01";
$month_end = date("Y-m-t", strtotime($month_start));

$current_month = date("Y-m");
$is_current_month = ($selected_month === $current_month);

$prev_month = date("Y-m", strtotime($month_start . " -1 month"));
$next_month = date("Y-m", strtotime($month_start . " +1 month"));

$month_display_name = date("F Y", strtotime($month_start));
$period_display_range = date("d M Y", strtotime($month_start)) . " – " . date("d M Y", strtotime($month_end));

/* Get Budget */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT id, amount
    FROM budgets
    WHERE user_id = ?
    AND period_start = ?
    AND period_end = ?
    LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "iss",
    $user_id,
    $month_start,
    $month_end
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$budget_row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$budget_id = $budget_row["id"] ?? null;
$budget_amount = (float) ($budget_row["amount"] ?? 0);

/* Calculate Spent & Expense Count */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE user_id = ?
    AND expense_date BETWEEN ? AND ?
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "iss",
    $user_id,
    $month_start,
    $month_end
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$spent_row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$spent_amount = (float) ($spent_row["total"] ?? 0);
$expense_count = (int) ($spent_row["total_count"] ?? 0);

/* Remaining & Progress Percentage */

$remaining_amount = $budget_amount - $spent_amount;

$budget_percentage = 0;
if ($budget_amount > 0) {
    $budget_percentage = ($spent_amount / $budget_amount) * 100;
}

$progress_percentage = min(max($budget_percentage, 0), 100);

/* Budget Status & Styling Class */

$budget_status = "No Budget";
$status_type = "neutral";

if ($budget_amount > 0) {
    if ($budget_percentage >= 100) {
        $budget_status = "Budget Exceeded";
        $status_type = "danger";
    } elseif ($budget_percentage >= 80) {
        $budget_status = "Warning (80%+)";
        $status_type = "warning";
    } else {
        $budget_status = "On Track";
        $status_type = "success";
    }
}

require_once __DIR__ . "/../includes/header.php";

?>

<div class="budget-page">

    <?php if (!empty($_GET["success"])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_GET["success"]); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET["error"])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_GET["error"]); ?>
        </div>
    <?php endif; ?>

    <!-- Page Header with Actions -->
    <div class="page-header">
        <div>
            <h1>Budget</h1>
            <p>Manage and monitor your monthly spending budget.</p>
        </div>

        <?php if ($budget_id): ?>
            <div class="header-action-group">
                <a href="edit.php?id=<?= (int) $budget_id; ?>" class="budget-action-btn edit-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Edit Budget
                </a>
                <form
                    method="POST"
                    action="delete.php"
                    onsubmit="return confirm('Are you sure you want to delete this budget?');"
                    class="budget-delete-form">
                    <input type="hidden" name="id" value="<?= (int) $budget_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                    <button type="submit" class="budget-action-btn delete-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        Delete Budget
                    </button>
                </form>
            </div>
        <?php else: ?>
            <a href="add.php?month=<?= htmlspecialchars($selected_month); ?>" class="add-expense-btn">
                + Set Budget
            </a>
        <?php endif; ?>
    </div>

    <!-- Month Navigation Toolbar -->
    <div class="budget-toolbar">
        <div class="month-nav-controls">
            <a href="?month=<?= htmlspecialchars($prev_month); ?>" class="month-nav-btn prev-btn" title="Previous Month (<?= date('M Y', strtotime($prev_month . '-01')); ?>)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                <span>Prev</span>
            </a>

            <form method="GET" class="month-picker-form">
                <div class="month-picker-wrap">
                    <svg class="calendar-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <input
                        type="month"
                        id="budget-month"
                        name="month"
                        value="<?= htmlspecialchars($selected_month); ?>"
                        onchange="this.form.submit()"
                        aria-label="Select month"
                    >
                </div>
            </form>

            <a href="?month=<?= htmlspecialchars($next_month); ?>" class="month-nav-btn next-btn" title="Next Month (<?= date('M Y', strtotime($next_month . '-01')); ?>)">
                <span>Next</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>
        </div>

        <div class="toolbar-secondary-actions">
            <?php if (!$is_current_month): ?>
                <a href="?month=<?= htmlspecialchars($current_month); ?>" class="this-month-btn" title="Jump to Current Month">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 14 14"></polyline>
                    </svg>
                    This Month
                </a>
            <?php endif; ?>

            <div class="period-info">
                <span class="period-label">Period:</span>
                <strong class="period-range"><?= htmlspecialchars($period_display_range); ?></strong>
            </div>
        </div>
    </div>

    <!-- 3 Summary Cards -->
    <div class="budget-summary">
        <div class="budget-card budget-card-limit">
            <div class="budget-card-header">
                <span class="budget-label">Monthly Budget</span>
                <div class="budget-card-icon limit-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                </div>
            </div>
            <strong class="budget-value">₹<?= number_format($budget_amount, 2); ?></strong>
            <span class="budget-subtext">Planned limit for <?= htmlspecialchars($month_display_name); ?></span>
        </div>

        <div class="budget-card budget-card-spent">
            <div class="budget-card-header">
                <span class="budget-label">Total Spent</span>
                <div class="budget-card-icon spent-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
            </div>
            <strong class="budget-value">₹<?= number_format($spent_amount, 2); ?></strong>
            <span class="budget-subtext">
                <?= $expense_count; ?> <?= $expense_count === 1 ? 'expense' : 'expenses'; ?> logged
                <?php if ($budget_amount > 0): ?>
                    (<?= number_format($budget_percentage, 1); ?>%)
                <?php endif; ?>
            </span>
        </div>

        <div class="budget-card budget-card-remaining <?= $remaining_amount < 0 ? 'is-over' : 'is-positive'; ?>">
            <div class="budget-card-header">
                <span class="budget-label">
                    <?= $remaining_amount < 0 ? "Exceeded By" : "Remaining"; ?>
                </span>
                <div class="budget-card-icon remaining-icon">
                    <?php if ($remaining_amount < 0): ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    <?php else: ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    <?php endif; ?>
                </div>
            </div>
            <strong class="budget-value">
                <?php if ($remaining_amount < 0): ?>
                    -₹<?= number_format(abs($remaining_amount), 2); ?>
                <?php else: ?>
                    ₹<?= number_format($remaining_amount, 2); ?>
                <?php endif; ?>
            </strong>
            <span class="budget-subtext">
                <?= $remaining_amount < 0 ? 'Over limit this month' : 'Safe to spend'; ?>
            </span>
        </div>
    </div>

    <!-- Budget Progress Bar Section with Circular Gauge & Progress Meter -->
    <?php if ($budget_amount > 0): ?>
        <div class="budget-progress-panel">
            <div class="budget-progress-inner-layout">
                <div id="budget-circular-gauge" class="budget-circular-col"></div>
                <div class="budget-progress-details-col">
                    <div class="budget-progress-header">
                        <div class="status-indicator-wrap">
                            <span class="budget-status-pill status-<?= $status_type; ?>">
                                <?php if ($status_type === 'success'): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <?php elseif ($status_type === 'warning'): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                <?php else: ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                <?php endif; ?>
                                <?= htmlspecialchars($budget_status); ?>
                            </span>
                        </div>
                        <div class="percentage-display status-text-<?= $status_type; ?>">
                            <?= number_format($budget_percentage, 1); ?>%
                        </div>
                    </div>

                    <div class="budget-progress-bar-wrap">
                        <div class="progress-bar">
                            <div class="progress-fill fill-<?= $status_type; ?>" style="width: <?= $progress_percentage; ?>%;"></div>
                        </div>
                    </div>

                    <div class="budget-progress-footer">
                        <span class="progress-detail-spent">
                            ₹<?= number_format($spent_amount, 2); ?> spent of ₹<?= number_format($budget_amount, 2); ?>
                        </span>
                        <span class="progress-detail-remaining">
                            <?php if ($remaining_amount >= 0): ?>
                                <strong>₹<?= number_format($remaining_amount, 2); ?></strong> remaining
                            <?php else: ?>
                                <strong class="text-danger">₹<?= number_format(abs($remaining_amount), 2); ?> over budget</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    if (window.ExpenseCharts) {
                        ExpenseCharts.renderBudgetGauge('budget-circular-gauge', <?= (float)$budget_percentage; ?>, <?= (float)$spent_amount; ?>, <?= (float)$budget_amount; ?>);
                    }
                });
            </script>
        </div>
    <?php else: ?>
        <div class="no-budget-card">
            <div class="no-budget-icon">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
            <h3>No Budget Set for <?= htmlspecialchars($month_display_name); ?></h3>
            <p>Set a spending goal for this month to monitor your daily expenses, receive budget warning alerts, and prevent overspending.</p>
            <a href="add.php?month=<?= htmlspecialchars($selected_month); ?>" class="add-expense-btn">
                + Set Monthly Budget
            </a>
        </div>
    <?php endif; ?>

</div>

<?php

require_once __DIR__ . "/../includes/footer.php";
