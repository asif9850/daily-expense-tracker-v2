<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_login();

$page_title = "Expenses";
$page_css = ["expenses"];

$user_id = get_user_id();

$search = trim($_GET["search"] ?? "");
$category = trim($_GET["category"] ?? "");
$date_from = $_GET["date_from"] ?? "";
$date_to = $_GET["date_to"] ?? "";

$page = max(1, (int) ($_GET["page"] ?? 1));

$per_page = 10;
$offset = ($page - 1) * $per_page;

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

if ($category !== "") {
    $where .= " AND category = ?";

    $types .= "s";
    $params[] = $category;
}

if ($date_from !== "") {
    $where .= " AND expense_date >= ?";

    $types .= "s";
    $params[] = $date_from;
}

if ($date_to !== "") {
    $where .= " AND expense_date <= ?";

    $types .= "s";
    $params[] = $date_to;
}

/* Total Rows */

$count_sql = "SELECT COUNT(*) AS total FROM expenses $where";

$stmt = mysqli_prepare($conn, $count_sql);

mysqli_stmt_bind_param($stmt, $types, ...$params);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$count_row = mysqli_fetch_assoc($result);

$total_rows = (int) ($count_row["total"] ?? 0);

mysqli_stmt_close($stmt);

/* Filtered Total */

$total_sql = "SELECT COALESCE(SUM(amount), 0) AS total FROM expenses $where";

$stmt = mysqli_prepare($conn, $total_sql);

mysqli_stmt_bind_param($stmt, $types, ...$params);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$total_row = mysqli_fetch_assoc($result);

$filtered_total = (float) ($total_row["total"] ?? 0);

mysqli_stmt_close($stmt);

/* Expense List */

$list_sql = "
    SELECT id, amount, category, expense_date, description
    FROM expenses
    $where
    ORDER BY expense_date DESC, id DESC
    LIMIT ? OFFSET ?
";

$list_types = $types . "ii";
$list_params = $params;
$list_params[] = $per_page;
$list_params[] = $offset;

$stmt = mysqli_prepare($conn, $list_sql);

mysqli_stmt_bind_param($stmt, $list_types, ...$list_params);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$expenses = [];

while ($row = mysqli_fetch_assoc($result)) {
    $expenses[] = $row;
}

mysqli_stmt_close($stmt);

/* Pagination */

$total_pages = max(1, (int) ceil($total_rows / $per_page));

require_once __DIR__ . "/../includes/header.php";

?>

<div class="expenses-page">

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
            <h1>Expenses</h1>

            <p>Manage your expenses.</p>
        </div>

        <a href="add.php" class="add-expense-btn">
            + Add Expense
        </a>

    </div>

    <!-- Unified Filter & Summary Panel -->
    <div class="expense-filter-panel">
        <div class="filter-panel-header">
            <div class="filter-title-wrap">
                <span class="filter-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                    </svg>
                    Filter Expenses
                </span>
                <?php if ($search !== "" || $category !== "" || $date_from !== "" || $date_to !== ""): ?>
                    <span class="active-filter-tag">Active Filter</span>
                <?php endif; ?>
            </div>

            <div class="filter-total-badge">
                <span class="total-badge-label">Filtered Total:</span>
                <strong class="total-badge-amount">₹<?= number_format($filtered_total, 2); ?></strong>
            </div>
        </div>

        <form method="GET" class="filter-controls-form">
            <div class="filter-field search-col">
                <label for="search">Search</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    placeholder="Search description or category"
                    value="<?= htmlspecialchars($search); ?>">
            </div>

            <div class="filter-field category-col">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All Categories</option>
                    <option value="Food" <?= $category === "Food" ? "selected" : ""; ?>>Food</option>
                    <option value="Transport" <?= $category === "Transport" ? "selected" : ""; ?>>Transport</option>
                    <option value="Shopping" <?= $category === "Shopping" ? "selected" : ""; ?>>Shopping</option>
                    <option value="Bills" <?= $category === "Bills" ? "selected" : ""; ?>>Bills</option>
                    <option value="Health" <?= $category === "Health" ? "selected" : ""; ?>>Health</option>
                    <option value="Education" <?= $category === "Education" ? "selected" : ""; ?>>Education</option>
                    <option value="Entertainment" <?= $category === "Entertainment" ? "selected" : ""; ?>>Entertainment</option>
                    <option value="Other" <?= $category === "Other" ? "selected" : ""; ?>>Other</option>
                </select>
            </div>

            <div class="filter-field date-col">
                <label for="date_from">Date From</label>
                <input
                    type="date"
                    id="date_from"
                    name="date_from"
                    value="<?= htmlspecialchars($date_from); ?>">
            </div>

            <div class="filter-field date-col">
                <label for="date_to">Date To</label>
                <input
                    type="date"
                    id="date_to"
                    name="date_to"
                    value="<?= htmlspecialchars($date_to); ?>">
            </div>

            <div class="filter-field actions-col">
                <button type="submit" class="filter-btn">
                    Filter
                </button>
                <a href="index.php" class="clear-filter-btn" title="Reset all filters">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <div class="expense-table-wrapper">

        <table class="expense-table">

            <thead>

                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Actions</th>
                </tr>

            </thead>

            <tbody>

                <?php if (empty($expenses)): ?>

                    <tr>
                        <td colspan="5" class="empty-cell">
                            No expenses found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($expenses as $expense): ?>

                        <tr class="expense-item-row">

                            <td class="cell-date" data-label="Date">
                                <span class="date-calendar-icon">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                </span>
                                <span class="expense-date-val"><?= htmlspecialchars($expense["expense_date"]); ?></span>
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

                            <td class="cell-amount expense-amount" data-label="Amount">
                                <span class="amount-value">₹<?= number_format((float) $expense["amount"], 2); ?></span>
                            </td>

                            <td class="cell-actions expense-actions" data-label="Actions">
                                <div class="action-buttons row-action-buttons">
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

                                    <form
                                        method="POST"
                                        action="delete.php"
                                        class="inline-form delete-form"
                                        onsubmit="return confirm('Delete this expense?');">
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

    <?php if ($total_pages > 1): ?>

        <div class="pagination">

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                <?php

                $query = http_build_query([
                    "search" => $search,
                    "category" => $category,
                    "date_from" => $date_from,
                    "date_to" => $date_to,
                    "page" => $i
                ]);

                ?>

                <a
                    href="?<?= htmlspecialchars($query); ?>"
                    class="<?= $i === $page ? "active" : ""; ?>">
                    <?= $i; ?>
                </a>

            <?php endfor; ?>

        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
