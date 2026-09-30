<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_login();
$page_title = "Upcoming Expenses";
$page_css = ["upcoming"];

$user_id = get_user_id();

$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "upcoming");

/* Build Filters */

$where = "WHERE user_id = ?";
$types = "i";
$params = [$user_id];

if ($search !== "") {
    $where .= " AND (description LIKE ? OR category LIKE ?)";

    $search_value = "%" . $search . "%";

    $types .= "ss";

    $params[] = $search_value;
    $params[] = $search_value;
}

if (in_array($status, ["upcoming", "paid"], true)) {
    $where .= " AND status = ?";

    $types .= "s";
    $params[] = $status;
} else {
    $status = "upcoming";
}

/* Upcoming Expenses */

$sql = "
    SELECT
        id,
        amount,
        category,
        due_date,
        description,
        status,
        paid_at
    FROM upcoming_expenses
    $where
    ORDER BY
        CASE WHEN status = 'upcoming' THEN 0 ELSE 1 END,
        due_date ASC,
        id DESC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, $types, ...$params);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$upcoming_expenses = [];

while ($row = mysqli_fetch_assoc($result)) {
    $upcoming_expenses[] = $row;
}

mysqli_stmt_close($stmt);

/* Total */

$total_sql = "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM upcoming_expenses
    $where
";

$stmt = mysqli_prepare($conn, $total_sql);

mysqli_stmt_bind_param($stmt, $types, ...$params);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$total_row = mysqli_fetch_assoc($result);

$filtered_total = (float) ($total_row["total"] ?? 0);

mysqli_stmt_close($stmt);

require_once __DIR__ . "/../includes/header.php";

?>

<div class="upcoming-page">

    <?php if (isset($_GET["paid"])): ?>
        <div class="alert alert-success">
            Expense marked as paid and logged into your expenses.
        </div>
    <?php endif; ?>

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

    <div class="page-header">

        <div>
            <h1>Upcoming Expenses</h1>
            <p>Track expenses that are due in the future.</p>
        </div>

        <a href="add.php" class="add-expense-btn">
            + Add Upcoming
        </a>

    </div>

    <div class="upcoming-summary">

        <span>
            <?= $status === "paid" ? "Paid Total" : "Upcoming Total"; ?>
        </span>

        <strong>
            ₹<?= number_format($filtered_total, 2); ?>
        </strong>

    </div>

    <form method="GET" class="upcoming-filters">

        <input
            type="text"
            name="search"
            placeholder="Search description or category"
            value="<?= htmlspecialchars($search); ?>"
        >

        <select name="status">

            <option
                value="upcoming"
                <?= $status === "upcoming" ? "selected" : ""; ?>
            >
                Upcoming
            </option>

            <option
                value="paid"
                <?= $status === "paid" ? "selected" : ""; ?>
            >
                Paid
            </option>

        </select>

        <button type="submit" class="filter-btn">
            Filter
        </button>

        <a href="index.php" class="clear-filter-btn">
            Clear
        </a>

    </form>

    <div class="upcoming-table-wrapper">

        <table class="upcoming-table">

            <thead>

                <tr>
                    <th>Due Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>

            </thead>

            <tbody>

                <?php if (empty($upcoming_expenses)): ?>

                    <tr>
                        <td colspan="6" class="empty-cell">
                            No upcoming expenses found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($upcoming_expenses as $expense): ?>

                        <?php

                        $today = date("Y-m-d");

                        if ($expense["status"] === "paid") {
                            $date_label = "Paid";
                            $date_class = "status-paid";
                        } elseif ($expense["due_date"] < $today) {
                            $date_label = "Overdue";
                            $date_class = "status-overdue";
                        } elseif ($expense["due_date"] === $today) {
                            $date_label = "Due Today";
                            $date_class = "status-today";
                        } else {
                            $date_label = "Upcoming";
                            $date_class = "status-upcoming";
                        }

                        ?>

                        <tr class="upcoming-item-row">

                            <td class="cell-date" data-label="Due Date">
                                <span class="date-calendar-icon">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                </span>
                                <span class="due-date-val"><?= htmlspecialchars($expense["due_date"]); ?></span>
                                <span class="date-label <?= $date_class; ?>">
                                    <?= $date_label; ?>
                                </span>
                            </td>

                            <td class="cell-category" data-label="Category">
                                <span class="category-pill">
                                    <?= htmlspecialchars($expense["category"]); ?>
                                </span>
                            </td>

                            <td class="cell-description" data-label="Description">
                                <span class="description-text">
                                    <?= htmlspecialchars($expense["description"] ?: "No description"); ?>
                                </span>
                            </td>

                            <td class="cell-amount upcoming-amount" data-label="Amount">
                                <span class="amount-value">₹<?= number_format((float) $expense["amount"], 2); ?></span>
                            </td>

                            <td class="cell-status" data-label="Status">
                                <?php if ($expense["status"] === "paid"): ?>
                                    <span class="status-badge status-paid">
                                        Paid
                                    </span>
                                <?php elseif ($expense["due_date"] < $today): ?>
                                    <span class="status-badge status-overdue">
                                        Overdue
                                    </span>
                                <?php elseif ($expense["due_date"] === $today): ?>
                                    <span class="status-badge status-today">
                                        Due Today
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-upcoming">
                                        Upcoming
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="cell-actions upcoming-actions" data-label="Actions">
                                <div class="row-action-buttons">
                                    <a
                                        href="edit.php?id=<?= (int) $expense["id"]; ?>"
                                        class="edit-btn"
                                        title="Edit this expense">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        Edit
                                    </a>

                                    <?php if ($expense["status"] === "upcoming"): ?>
                                        <form
                                            method="POST"
                                            action="mark_paid.php"
                                            class="inline-form">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(csrf_token()); ?>">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $expense["id"]; ?>">
                                            <button
                                                type="submit"
                                                class="paid-btn"
                                                title="Mark as paid">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="20 6 9 17 4 12"></polyline>
                                                </svg>
                                                Mark Paid
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form
                                        method="POST"
                                        action="delete.php"
                                        class="inline-form"
                                        onsubmit="return confirm('Delete this upcoming expense?');">
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(csrf_token()); ?>">
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $expense["id"]; ?>">
                                        <button
                                            type="submit"
                                            class="delete-btn"
                                            title="Delete this expense">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
