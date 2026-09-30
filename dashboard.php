<?php

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/functions.php";

require_login();

$page_title = "Dashboard";
$page_css = ["dashboard", "forms"];

$user_id = get_user_id();

$user_name = $_SESSION["user_name"] ?? "User";

/* Period */

$period = $_GET["period"] ?? "this_month";

$today = date("Y-m-d");

switch ($period) {
    case "last_month":

        $period_start = date("Y-m-01", strtotime("first day of last month"));
        $period_end = date("Y-m-t", strtotime("last day of last month"));
        $period_label = "Last Month";

        break;

    case "this_year":

        $period_start = date("Y-01-01");
        $period_end = date("Y-12-31");
        $period_label = "This Year";

        break;

    case "this_month":

    default:

        $period = "this_month";

        $period_start = date("Y-m-01");
        $period_end = date("Y-m-t");
        $period_label = "This Month";

        break;
}

/* Quick Add Expense */

$quick_add_error = "";

$quick_amount = "";
$quick_category = "";
$quick_expense_date = date("Y-m-d");
$quick_description = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";

    $quick_amount = trim($_POST["amount"] ?? "");
    $quick_category = trim($_POST["category"] ?? "");
    $quick_expense_date = $_POST["expense_date"] ?? "";
    $quick_description = trim($_POST["description"] ?? "");

    if (!verify_csrf_token($csrf_token)) {
        $quick_add_error = "Invalid request.";
} elseif (
    !is_numeric($quick_amount)
    || (float) $quick_amount <= 0
) {
    $quick_add_error = "Amount must be greater than 0.";
} elseif (
    !in_array(
        $quick_category,
        [
            "Food",
            "Transport",
            "Shopping",
            "Bills",
            "Health",
            "Education",
            "Entertainment",
            "Other"
        ],
        true
    )
) {
    $quick_add_error = "Please select a valid category.";
} elseif (
    !DateTime::createFromFormat("Y-m-d", $quick_expense_date)
    || DateTime::createFromFormat("Y-m-d", $quick_expense_date)->format("Y-m-d") !== $quick_expense_date
) {
    $quick_add_error = "Please enter a valid date.";
} else {
    $quick_amount_value = (float) $quick_amount;

        $stmt = mysqli_prepare(
            $conn,
            "
            INSERT INTO expenses
            (
                user_id,
                amount,
                category,
                expense_date,
                description,
                source_type
            )
            VALUES (?, ?, ?, ?, ?, 'manual')
            "
        );

        mysqli_stmt_bind_param(
            $stmt,
            "idsss",
            $user_id,
            $quick_amount_value,
            $quick_category,
            $quick_expense_date,
            $quick_description
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            invalidate_notification_sync();

            header(
                "Location: dashboard.php?period="
                . urlencode($period)
                . "&expense_added=1"
            );

            exit;
        } else {
            $quick_add_error = "Failed to add expense.";
            check_sql_errors($conn, "Quick Add Expense");

            mysqli_stmt_close($stmt);
        }
    }
}

/* Daily Spending */

$stmt = mysqli_prepare(
    $conn,
    "
  SELECT
    expense_date,
    SUM(amount) AS total
FROM expenses
WHERE user_id = ?
AND expense_date BETWEEN ? AND ?
GROUP BY expense_date
ORDER BY expense_date ASC
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "iss",
    $user_id,
    $period_start,
    $period_end
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$dashboard_daily_spending = [];

while ($row = mysqli_fetch_assoc($result)) {
    $dashboard_daily_spending[] = [
        "date" => $row["expense_date"],
        "total" => (float) $row["total"]
    ];
}

mysqli_stmt_close($stmt);

/* Yearly Monthly Spending */

$dashboard_monthly_spending = [];

foreach ($dashboard_daily_spending as $day) {
    $month_key = date(
        "Y-m",
        strtotime($day["date"])
    );

    if (!isset($dashboard_monthly_spending[$month_key])) {
        $dashboard_monthly_spending[$month_key] = 0;
    }

    $dashboard_monthly_spending[$month_key] += $day["total"];
}

/* Category Spending */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        category,
        SUM(amount) AS total
    FROM expenses
    WHERE user_id = ?
    AND expense_date BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total DESC
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "iss",
    $user_id,
    $period_start,
    $period_end
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$dashboard_category_data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $dashboard_category_data[] = [
        "category" => $row["category"],
        "total" => (float) $row["total"]
    ];
}

mysqli_stmt_close($stmt);

/* Budget */

$budget_month = date("Y-m");

$budget_start = $budget_month . "-01";

$budget_end = date(
    "Y-m-t",
    strtotime($budget_start)
);

/* Get Monthly Budget */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT amount
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
    $budget_start,
    $budget_end
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$budget_row = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$dashboard_budget = (float) (
    $budget_row["amount"] ?? 0
);

/* Current Month Spent */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE user_id = ?
    AND expense_date BETWEEN ? AND ?
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "iss",
    $user_id,
    $budget_start,
    $budget_end
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$budget_spent_row = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$dashboard_budget_spent = (float) (
    $budget_spent_row["total"] ?? 0
);

$dashboard_remaining =
    $dashboard_budget - $dashboard_budget_spent;

/* Total Spent */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE user_id = ?
    AND expense_date BETWEEN ? AND ?
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "iss",
    $user_id,
    $period_start,
    $period_end
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$total_row = mysqli_fetch_assoc($result);

$total_spent = (float) ($total_row["total"] ?? 0);

mysqli_stmt_close($stmt);

/* Recent Expenses */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT id, amount, category, expense_date, description
    FROM expenses
    WHERE user_id = ?
    ORDER BY expense_date DESC, id DESC
    LIMIT 5
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$recent_expenses = [];

while ($row = mysqli_fetch_assoc($result)) {
    $recent_expenses[] = $row;
}

mysqli_stmt_close($stmt);

/* Upcoming Expenses Summary */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        COALESCE(SUM(amount), 0) AS total
    FROM upcoming_expenses
    WHERE user_id = ?
    AND status = 'upcoming'
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$upcoming_row = mysqli_fetch_assoc($result);

$upcoming_total = (float) ($upcoming_row["total"] ?? 0);

mysqli_stmt_close($stmt);

/* Upcoming Expenses Widget */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        id,
        amount,
        category,
        due_date,
        description
    FROM upcoming_expenses
    WHERE user_id = ?
    AND status = 'upcoming'
    ORDER BY due_date ASC, id ASC
    LIMIT 5
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$dashboard_upcoming = [];

while ($row = mysqli_fetch_assoc($result)) {
    $dashboard_upcoming[] = $row;
}

mysqli_stmt_close($stmt);

/* Recurring Expenses Summary */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        COUNT(*) AS active_count,
        COALESCE(SUM(amount), 0) AS total
    FROM recurring_expenses
    WHERE user_id = ?
    AND status = 'active'
    "
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$rec_result = mysqli_stmt_get_result($stmt);
$rec_row = mysqli_fetch_assoc($rec_result);
$recurring_active_count = (int) ($rec_row["active_count"] ?? 0);
$recurring_total = (float) ($rec_row["total"] ?? 0);
mysqli_stmt_close($stmt);

/* Budget Health & Alert Status */

$dashboard_budget_pct = $dashboard_budget > 0 ? round(($dashboard_budget_spent / $dashboard_budget) * 100, 1) : 0;
$dashboard_is_over_budget = $dashboard_budget > 0 && ($dashboard_budget_spent > $dashboard_budget);
$dashboard_budget_near_limit = $dashboard_budget > 0 && ($dashboard_budget_pct >= 80 && !$dashboard_is_over_budget);

require_once __DIR__ . "/includes/header.php";

?>

<div class="dashboard-page">
        <?php if (isset($_GET["expense_added"])): ?>

    <div class="alert alert-success" id="expense-success-message">
        Expense added successfully.
    </div>

<?php endif; ?>
    <!-- Dashboard Header -->

    <section class="dashboard-header">

        <div>

            <h1>
                Welcome, <?= htmlspecialchars($user_name); ?>
            </h1>

            <p>
                Here's your expense overview.
            </p>

        </div>

        <div class="dashboard-actions">

            <form method="GET">

                <select
                    name="period"
                    onchange="this.form.submit()"
                    id="period-selector">

                    <option
                        value="this_month"
                        <?= $period === "this_month" ? "selected" : ""; ?>>
                        This Month
                    </option>

                    <option
                        value="last_month"
                        <?= $period === "last_month" ? "selected" : ""; ?>>
                        Last Month
                    </option>

                    <option
                        value="this_year"
                        <?= $period === "this_year" ? "selected" : ""; ?>>
                        This Year
                    </option>

                </select>

            </form>

          <button
    type="button"
    class="add-expense-btn"
    id="open-expense-modal">
    + Add Expense
</button>

        </div>

    </section>

    <!-- Summary Cards (4 Balanced Cards) -->
    <section class="summary-grid">

        <div class="summary-card">
            <span class="summary-label">Total Spent</span>
            <strong class="summary-value">₹<?= number_format($total_spent, 2); ?></strong>
            <span class="summary-subtext"><?= htmlspecialchars($period_label); ?></span>
        </div>

        <div class="summary-card">
            <span class="summary-label">Budget Remaining</span>
            <strong class="summary-value" style="<?= $dashboard_budget > 0 && $dashboard_remaining < 0 ? 'color: var(--color-danger);' : ($dashboard_budget > 0 ? 'color: var(--color-success);' : ''); ?>">
                <?= $dashboard_budget > 0 ? ($dashboard_remaining < 0 ? '-₹' . number_format(abs($dashboard_remaining), 2) : '₹' . number_format($dashboard_remaining, 2)) : '<span class="text-muted" style="font-size:16px;">Not Set</span>'; ?>
            </strong>
            <span class="summary-subtext">
                <?php if ($dashboard_budget > 0): ?>
                    of ₹<?= number_format($dashboard_budget, 2); ?> limit •
                    <?php if ($dashboard_is_over_budget): ?>
                        <span class="badge badge-danger">Exceeded</span>
                    <?php elseif ($dashboard_budget_near_limit): ?>
                        <span class="badge badge-warning">Near Limit</span>
                    <?php else: ?>
                        <span class="badge badge-success">On Track</span>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="budget/index.php" style="color:var(--color-primary);font-weight:600;">+ Set Monthly Budget</a>
                <?php endif; ?>
            </span>
        </div>

        <div class="summary-card">
            <span class="summary-label">Upcoming Bills</span>
            <strong class="summary-value">₹<?= number_format($upcoming_total, 2); ?></strong>
            <span class="summary-subtext">
                <?= count($dashboard_upcoming); ?> queued • <a href="upcoming/index.php" style="color:var(--color-primary);font-weight:600;">View All</a>
            </span>
        </div>

        <div class="summary-card">
            <span class="summary-label">Active Recurring</span>
            <strong class="summary-value">₹<?= number_format($recurring_total, 2); ?></strong>
            <span class="summary-subtext">
                <?= $recurring_active_count; ?> active • <a href="recurring/index.php" style="color:var(--color-primary);font-weight:600;">View All</a>
            </span>
        </div>

    </section>

    <?php if ($dashboard_budget > 0): ?>
        <!-- Budget Progress & Alert Bar -->
        <div class="dashboard-budget-bar-card">
            <div class="dashboard-budget-bar-header">
                <div>
                    <strong>Monthly Budget Status (<?= date("F Y"); ?>)</strong>
                    <span class="text-muted" style="margin-left: 8px; font-size: 13px;">
                        ₹<?= number_format($dashboard_budget_spent, 2); ?> spent of ₹<?= number_format($dashboard_budget, 2); ?>
                    </span>
                </div>
                <div>
                    <?php if ($dashboard_is_over_budget): ?>
                        <span class="badge badge-danger">🚨 Over by ₹<?= number_format(abs($dashboard_remaining), 2); ?> (<?= number_format($dashboard_budget_pct, 1); ?>%)</span>
                    <?php elseif ($dashboard_budget_near_limit): ?>
                        <span class="badge badge-warning">⚠️ <?= number_format($dashboard_budget_pct, 1); ?>% Limit Used</span>
                    <?php else: ?>
                        <span class="badge badge-success">✓ <?= number_format($dashboard_budget_pct, 1); ?>% Used (₹<?= number_format($dashboard_remaining, 2); ?> left)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dashboard-budget-bar-track">
                <div class="dashboard-budget-bar-fill" style="width: <?= min(100, $dashboard_budget_pct); ?>%; background: <?= $dashboard_is_over_budget ? 'var(--color-danger)' : ($dashboard_budget_near_limit ? 'var(--color-warning)' : 'var(--color-success)'); ?>;"></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Charts (Modern Donut & Vertical Bar Charts) -->
    <section class="dashboard-charts">

        <!-- Spending Trend Chart -->
        <div class="dashboard-chart">
            <div class="dashboard-chart-header">
                <h2>Spending Trend</h2>
                <span><?= htmlspecialchars($period_label); ?></span>
            </div>

            <?php if ($period === "this_year"): ?>
                <?php if (!empty($dashboard_monthly_spending)): ?>
                    <div id="dashboard-trend-chart"></div>
                    <script>
                        document.addEventListener("DOMContentLoaded", function () {
                            if (window.ExpenseCharts) {
                                const trendData = <?= json_encode(array_map(function($month, $total) {
                                    return [
                                        'dateLabel' => date("M Y", strtotime($month . "-01")),
                                        'total' => (float)$total
                                    ];
                                }, array_keys($dashboard_monthly_spending), array_values($dashboard_monthly_spending))); ?>;
                                ExpenseCharts.renderTrendBarChart('dashboard-trend-chart', trendData);
                            }
                        });
                    </script>
                <?php else: ?>
                    <p class="dashboard-empty">No spending data for this year.</p>
                <?php endif; ?>
            <?php elseif (!empty($dashboard_daily_spending)): ?>
                <div id="dashboard-trend-chart"></div>
                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        if (window.ExpenseCharts) {
                            const trendData = <?= json_encode(array_map(function($day) {
                                return [
                                    'dateLabel' => date("d M", strtotime($day["date"])),
                                    'total' => (float)$day["total"]
                                ];
                            }, $dashboard_daily_spending)); ?>;
                            ExpenseCharts.renderTrendBarChart('dashboard-trend-chart', trendData);
                        }
                    });
                </script>
            <?php else: ?>
                <p class="dashboard-empty">No spending data for this period.</p>
            <?php endif; ?>
        </div>

        <!-- Category Breakdown Donut Chart -->
        <div class="dashboard-chart">
            <div class="dashboard-chart-header">
                <h2>Category Breakdown</h2>
                <span><?= htmlspecialchars($period_label); ?></span>
            </div>

            <?php if (!empty($dashboard_category_data)): ?>
                <div id="dashboard-category-donut"></div>
                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        if (window.ExpenseCharts) {
                            const catData = <?= json_encode(array_map(function($cat) {
                                return [
                                    'category' => $cat['category'],
                                    'total' => (float)$cat['total']
                                ];
                            }, $dashboard_category_data)); ?>;
                            ExpenseCharts.renderDonutChart('dashboard-category-donut', catData, {
                                centerTitle: 'Total Spent',
                                compact: false
                            });
                        }
                    });
                </script>
            <?php else: ?>
                <p class="dashboard-empty">No spending data for this period.</p>
            <?php endif; ?>
        </div>

    </section>

    <!-- Recent and Upcoming -->

    <section class="dashboard-bottom">

        <div class="dashboard-card">

            <div class="card-header">

                <h2>Recent Expenses</h2>

                <a href="expenses/index.php">
                    View All
                </a>

            </div>

            <?php if (empty($recent_expenses)): ?>

    <div class="empty-state">
        <p>No recent expenses.</p>
    </div>

<?php else: ?>

    <div class="recent-expenses">

        <?php foreach ($recent_expenses as $expense): ?>

            <div class="recent-expense-row">

                <div class="recent-expense-info">

                    <strong>
                        <?= htmlspecialchars($expense["category"]); ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($expense["description"] ?: "No description"); ?>
                    </span>

                </div>

                <div class="recent-expense-right">

                    <strong>
                        ₹<?= number_format((float) $expense["amount"], 2); ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($expense["expense_date"]); ?>
                    </span>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

        </div>

        <div class="dashboard-card">

            <div class="card-header">

                <h2>Upcoming Expenses</h2>

                <a href="upcoming/index.php">
                    View All
                </a>

            </div>

            <?php if (empty($dashboard_upcoming)): ?>

    <div class="empty-state">
        <p>No upcoming expenses.</p>
    </div>

<?php else: ?>

    <div class="recent-expenses">

        <?php foreach ($dashboard_upcoming as $expense): ?>

            <div class="recent-expense-row">

                <div class="recent-expense-info">

                    <strong>
                        <?= htmlspecialchars($expense["category"]); ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($expense["description"] ?: "No description"); ?>
                    </span>

                </div>

                <div class="recent-expense-right">

                    <strong>
                        ₹<?= number_format((float) $expense["amount"], 2); ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($expense["due_date"]); ?>
                    </span>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

        </div>

    </section>

</div>

<div
    class="expense-modal <?= $quick_add_error !== "" ? "active" : ""; ?>"
    id="expense-modal"
    aria-hidden="<?= $quick_add_error !== "" ? "false" : "true"; ?>">

    <div
        class="expense-modal-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="expense-modal-title">

        <div class="expense-modal-header">

            <h2 id="expense-modal-title">
                Add Expense
            </h2>

            <button
                type="button"
                class="expense-modal-close"
                id="close-expense-modal"
                aria-label="Close">
                ×
            </button>

        </div>

        <?php if ($quick_add_error !== ""): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars($quick_add_error); ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token()); ?>"
            >

            <div class="form-group">

                <label for="quick-amount">
                    Amount
                </label>

                <input
                    type="number"
                    id="quick-amount"
                    name="amount"
                    step="0.01"
                    min="0.01"
                    value="<?= htmlspecialchars($quick_amount); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="quick-category">
                    Category
                </label>

                <select
                    id="quick-category"
                    name="category"
                    required>

                    <option value="">
                        Select Category
                    </option>

                    <option value="Food"
                        <?= $quick_category === "Food" ? "selected" : ""; ?>>
                        Food
                    </option>

                    <option value="Transport"
                        <?= $quick_category === "Transport" ? "selected" : ""; ?>>
                        Transport
                    </option>

                    <option value="Shopping"
                        <?= $quick_category === "Shopping" ? "selected" : ""; ?>>
                        Shopping
                    </option>

                    <option value="Bills"
                        <?= $quick_category === "Bills" ? "selected" : ""; ?>>
                        Bills
                    </option>

                    <option value="Health"
                        <?= $quick_category === "Health" ? "selected" : ""; ?>>
                        Health
                    </option>

                    <option value="Education"
                        <?= $quick_category === "Education" ? "selected" : ""; ?>>
                        Education
                    </option>

                    <option value="Entertainment"
                        <?= $quick_category === "Entertainment" ? "selected" : ""; ?>>
                        Entertainment
                    </option>

                    <option value="Other"
                        <?= $quick_category === "Other" ? "selected" : ""; ?>>
                        Other
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label for="quick-expense-date">
                    Date
                </label>

                <input
                    type="date"
                    id="quick-expense-date"
                    name="expense_date"
                    value="<?= htmlspecialchars($quick_expense_date); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="quick-description">
                    Description
                </label>

                <textarea
                    id="quick-description"
                    name="description"
                    maxlength="255"
                    rows="3"><?= htmlspecialchars($quick_description); ?></textarea>

            </div>

            <div class="expense-modal-actions">

                <button
                    type="button"
                    class="btn-secondary"
                    id="cancel-expense-modal">
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn-primary">
                    Save Expense
                </button>

            </div>

        </form>

    </div>

</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
