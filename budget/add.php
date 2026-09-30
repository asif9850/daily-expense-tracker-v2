<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

$user_id = get_user_id();

$page_title = "Set Budget";
$page_css = ["forms", "budget"];

$selected_month = $_GET["month"] ?? date("Y-m");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf_token)) {
        die("Invalid request.");
    }

    $selected_month = $_POST["month"] ?? "";
    $amount = trim($_POST["amount"] ?? "");

    $errors = [];

    /* Validate Month */

    if (
        !preg_match(
            "/^\d{4}-\d{2}$/",
            $selected_month
        )
    ) {
        $errors[] = "Please select a valid month.";
    }

    /* Validate Amount */

    if ($amount === "") {
        $errors[] = "Budget amount is required.";
    } elseif (!is_numeric($amount) || (float) $amount <= 0) {
        $errors[] = "Budget amount must be greater than 0.";
    }

    /* Calculate Period */

    if (empty($errors)) {
        $month_start = $selected_month . "-01";

        $month_end = date(
            "Y-m-t",
            strtotime($month_start)
        );

        /* Duplicate Budget Check */

        $stmt = mysqli_prepare(
            $conn,
            "
            SELECT id
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

        $existing_budget = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if ($existing_budget) {
            $errors[] = "A budget already exists for this month.";
        }
    }

    /* Insert Budget */

    if (empty($errors)) {
        $amount = (float) $amount;

        $stmt = mysqli_prepare(
            $conn,
            "
            INSERT INTO budgets
            (
                user_id,
                amount,
                period_start,
                period_end
            )
            VALUES (?, ?, ?, ?)
            "
        );

        mysqli_stmt_bind_param(
            $stmt,
            "idss",
            $user_id,
            $amount,
            $month_start,
            $month_end
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);

        invalidate_notification_sync();

        header("Location: index.php?month=" . urlencode($selected_month) . "&success=" . urlencode("Budget set successfully."));
        exit;
    }
}

require_once __DIR__ . "/../includes/header.php";

?>

<div class="budget-page form-page">

    <div class="form-card">

        <div class="page-header">

            <h1>Set Budget</h1>

            <p>
                Set your spending limit for the selected month.
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
                    name="month"
                    value="<?= htmlspecialchars($selected_month); ?>"
                    required
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
                    placeholder="Enter budget amount"
                    value="<?= htmlspecialchars($_POST["amount"] ?? ""); ?>"
                    required
                >

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="add-expense-btn"
                >
                    Save Budget
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
