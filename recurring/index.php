<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_login();

$page_title = "Recurring Expenses";
$page_css = ["recurring"];

$user_id = get_user_id();

$search = trim($_GET["search"] ?? "");
$status = $_GET["status"] ?? "active";

/* Fetch Recurring Expenses */

$sql = "
    SELECT
        id,
        amount,
        category,
        description,
        frequency,
        start_date,
        next_date,
        end_date,
        status
    FROM recurring_expenses
    WHERE user_id = ?
";

$params = [$user_id];
$types = "i";

if ($search !== "") {
    $sql .= "
        AND (
            category LIKE ?
            OR description LIKE ?
        )
    ";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "ss";
}

if (in_array($status, ["active", "paused", "ended"], true)) {
    $sql .= " AND status = ?";

    $params[] = $status;
    $types .= "s";
}

$sql .= "
    ORDER BY next_date ASC, id ASC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    $types,
    ...$params
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$recurring_expenses = [];

while ($row = mysqli_fetch_assoc($result)) {
    $recurring_expenses[] = $row;
}

mysqli_stmt_close($stmt);

/* Page Header */

require_once __DIR__ . "/../includes/header.php";

?>

<div class="page-container recurring-page">

    <?php if (isset($_GET["generated"])): ?>
        <div class="alert alert-success">
            Recurring expense successfully generated and logged into your expenses.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["paused"])): ?>
        <div class="alert alert-success">
            Recurring expense paused.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["resumed"])): ?>
        <div class="alert alert-success">
            Recurring expense resumed.
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
            <h1>Recurring Expenses</h1>

            <p>
                Manage expenses that repeat automatically.
            </p>
        </div>

        <a
            href="add.php"
            class="add-expense-btn">
            + Add Recurring
        </a>

    </div>

    <div class="recurring-filters">

        <form method="GET">

            <input
                type="text"
                name="search"
                placeholder="Search description or category"
                value="<?= htmlspecialchars($search); ?>">

            <select name="status">

                <option
                    value="active"
                    <?= $status === "active" ? "selected" : ""; ?>>
                    Active
                </option>

                <option
                    value="paused"
                    <?= $status === "paused" ? "selected" : ""; ?>>
                    Paused
                </option>

                <option
                    value="ended"
                    <?= $status === "ended" ? "selected" : ""; ?>>
                    Ended
                </option>

            </select>

            <button
                type="submit"
                class="filter-btn">
                Filter
            </button>

            <a
                href="index.php"
                class="clear-filter-btn">
                Clear
            </a>

        </form>

    </div>

    <div class="recurring-table-wrapper">

        <table class="recurring-table">

            <thead>

                <tr>

                    <th>Next Date</th>

                    <th>Category</th>

                    <th>Description</th>

                    <th>Amount</th>

                    <th>Frequency</th>

                    <th>Status</th>

                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

                <?php if (empty($recurring_expenses)): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty-state">
                            No recurring expenses found.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($recurring_expenses as $expense): ?>

                        <tr class="recurring-item-row">

                            <td class="cell-date" data-label="Next Date">
                                <span class="date-calendar-icon">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                </span>
                                <span class="next-date-val"><?= htmlspecialchars($expense["next_date"]); ?></span>
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

                            <td class="cell-amount amount-cell" data-label="Amount">
                                <span class="amount-value">₹<?= number_format((float) $expense["amount"], 2); ?></span>
                            </td>

                            <td class="cell-frequency" data-label="Frequency">
                                <span class="frequency-badge">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="23 4 23 10 17 10"></polyline>
                                        <polyline points="1 20 1 14 7 14"></polyline>
                                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                    </svg>
                                    <?= ucfirst(htmlspecialchars($expense["frequency"])); ?>
                                </span>
                            </td>

                            <td class="cell-status" data-label="Status">
                                <span class="status-badge status-<?= htmlspecialchars($expense["status"]); ?>">
                                    <?= ucfirst(htmlspecialchars($expense["status"])); ?>
                                </span>
                            </td>

                            <td class="cell-actions" data-label="Actions">
                                <div class="action-buttons row-action-buttons">
                                    <a
                                        href="edit.php?id=<?= (int) $expense["id"]; ?>"
                                        class="edit-btn"
                                        title="Edit recurring expense">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        Edit
                                    </a>

                                    <?php if ($expense["status"] === "active"): ?>
                                        <form
                                            method="POST"
                                            action="pause.php"
                                            class="inline-form"
                                            onsubmit="return confirm('Pause this recurring expense?');">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $expense["id"]; ?>">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(csrf_token()); ?>">
                                            <button
                                                type="submit"
                                                class="pause-btn"
                                                title="Pause automatic reminders">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="6" y="4" width="4" height="16"></rect>
                                                    <rect x="14" y="4" width="4" height="16"></rect>
                                                </svg>
                                                Pause
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="generate.php"
                                            class="inline-form"
                                            onsubmit="return confirm('Generate this recurring expense now?');">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $expense["id"]; ?>">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(csrf_token()); ?>">
                                            <button
                                                type="submit"
                                                class="generate-btn"
                                                title="Generate this expense now">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                                </svg>
                                                Generate
                                            </button>
                                        </form>

                                    <?php elseif ($expense["status"] === "paused"): ?>
                                        <form
                                            method="POST"
                                            action="resume.php"
                                            class="inline-form"
                                            onsubmit="return confirm('Resume this recurring expense?');">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $expense["id"]; ?>">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(csrf_token()); ?>">
                                            <button
                                                type="submit"
                                                class="resume-btn"
                                                title="Resume recurring reminders">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                                </svg>
                                                Resume
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form
                                        method="POST"
                                        action="delete.php"
                                        class="inline-form"
                                        onsubmit="return confirm('Delete this recurring expense?');">
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $expense["id"]; ?>">
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(csrf_token()); ?>">
                                        <button
                                            type="submit"
                                            class="delete-btn"
                                            title="Delete this recurring rule">
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

<?php

require_once __DIR__ . "/../includes/footer.php";

?>
