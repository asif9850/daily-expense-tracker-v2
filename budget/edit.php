<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

$user_id = get_user_id();

$page_title = "Edit Budget";
$page_css = ["forms", "budget"];

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: index.php");
    exit;
}

/* Get Existing Budget */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        id,
        amount,
        period_start,
        period_end
    FROM budgets
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $id,
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$budget = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$budget) {
    header("Location: index.php");
    exit;
}

$selected_month = date(
    "Y-m",
    strtotime($budget["period_start"])
);

$amount = $budget["amount"];

/* Update Budget */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf_token)) {
        die("Invalid request.");
    }

    $new_amount = trim($_POST["amount"] ?? "");

    $errors = [];

    /* Validate Amount */

    if ($new_amount === "") {
        $errors[] = "Budget amount is required.";
    } elseif (
        !is_numeric($new_amount) ||
        (float) $new_amount <= 0
    ) {
        $errors[] = "Budget amount must be greater than 0.";
    }

    /* Update */

    if (empty($errors)) {
        $new_amount = (float) $new_amount;

        $stmt = mysqli_prepare(
            $conn,
            "
            UPDATE budgets
            SET amount = ?
            WHERE id = ?
            AND user_id = ?
            "
        );

        mysqli_stmt_bind_param(
            $stmt,
            "dii",
            $new_amount,
            $id,
            $user_id
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);

        invalidate_notification_sync();

        header(
            "Location: index.php?month=" .
            urlencode($selected_month) .
            "&success=" . urlencode("Budget updated successfully.")
        );

        exit;
    }

    $amount = $new_amount;
}

require_once __DIR__ . "/../includes/header.php";

?>

<div class="budget-page form-page">

    <div class="form-card">

        <div class="page-header">

            <h1>Edit Budget</h1>

            <p>
                Update your budget for
                <?= htmlspecialchars($selected_month); ?>.
            </p>

        </div>

        <?php if (!empty($errors)): ?>

            <div class="form-errors">

                <?php foreach ($errors as $error): ?>

                    <p>
                        <?= htmlspecialchars($error); ?>
                    </p>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token()); ?>"
            >

            <div class="form-group">

                <label for="budget-month">
                    Month
                </label>

                <input
                    type="month"
                    id="budget-month"
                    value="<?= htmlspecialchars($selected_month); ?>"
                    disabled
                >

            </div>

            <div class="form-group">

                <label for="budget-amount">
                    Budget Amount
                </label>

                <input
                    type="number"
                    id="budget-amount"
                    name="amount"
                    min="0.01"
                    step="0.01"
                    value="<?= htmlspecialchars($amount); ?>"
                    required
                >

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="add-expense-btn"
                >
                    Update Budget
                </button>

                <a
                    href="index.php?month=<?= htmlspecialchars($selected_month); ?>"
                    class="btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php

require_once __DIR__ . "/../includes/footer.php";
