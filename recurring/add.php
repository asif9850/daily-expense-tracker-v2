<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

$page_title = "Add Recurring Expense";
$page_css = ["forms", "recurring"];

$user_id = get_user_id();

$error = "";

$amount = "";
$category = "";
$description = "";
$frequency = "monthly";
$start_date = date("Y-m-d");
$next_date = date("Y-m-d");
$end_date = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";

    $amount = trim($_POST["amount"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $frequency = $_POST["frequency"] ?? "";
    $start_date = $_POST["start_date"] ?? "";
    $next_date = $_POST["next_date"] ?? "";
    $end_date = $_POST["end_date"] ?? "";

    /* Validation */

    if (!verify_csrf_token($csrf_token)) {
        $error = "Invalid request.";
    } elseif (
        $amount === "" ||
        $category === "" ||
        $frequency === "" ||
        $start_date === "" ||
        $next_date === ""
    ) {
        $error = "Amount, category, frequency and dates are required.";
    } elseif (!is_numeric($amount) || (float) $amount <= 0) {
        $error = "Amount must be greater than 0.";
    } elseif (
        !in_array(
            $frequency,
            ["daily", "weekly", "monthly", "yearly"],
            true
        )
    ) {
        $error = "Invalid frequency selected.";
    } elseif ($next_date < $start_date) {
        $error = "Next date cannot be before the start date.";
    } elseif ($end_date !== "" && $end_date < $next_date) {
        $error = "End date cannot be before the next date.";
    } else {
        $amount_value = (float) $amount;

        $end_date_value = $end_date !== "" ? $end_date : null;

        /* Insert Recurring Expense */

        $stmt = mysqli_prepare(
            $conn,
            "
            INSERT INTO recurring_expenses
            (
                user_id,
                amount,
                category,
                description,
                frequency,
                start_date,
                next_date,
                end_date,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
            "
        );

        mysqli_stmt_bind_param(
            $stmt,
            "idssssss",
            $user_id,
            $amount_value,
            $category,
            $description,
            $frequency,
            $start_date,
            $next_date,
            $end_date_value
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            invalidate_notification_sync();

            header("Location: index.php");
            exit;
        } else {
            $error = "Failed to add recurring expense.";

            mysqli_stmt_close($stmt);
        }
    }
}

require_once __DIR__ . "/../includes/header.php";

?>

<div class="form-page recurring-form-page">

    <div class="form-card">

        <div class="page-header">

            <div>

                <h1>
                    Add Recurring Expense
                </h1>

                <p>
                    Create an expense that repeats automatically.
                </p>

            </div>

        </div>

        <?php if ($error !== ""): ?>

            <div class="alert alert-error">

                <?= htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token()); ?>"
            >

            <div class="form-group">

                <label for="amount">
                    Amount
                </label>

                <input
                    type="number"
                    id="amount"
                    name="amount"
                    step="0.01"
                    min="0.01"
                    value="<?= htmlspecialchars($amount); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="category">
                    Category
                </label>

                <select
                    id="category"
                    name="category"
                    required
                >

                    <option value="">
                        Select Category
                    </option>

                    <option
                        value="Food"
                        <?= $category === "Food" ? "selected" : ""; ?>
                    >
                        Food
                    </option>

                    <option
                        value="Transport"
                        <?= $category === "Transport" ? "selected" : ""; ?>
                    >
                        Transport
                    </option>

                    <option
                        value="Shopping"
                        <?= $category === "Shopping" ? "selected" : ""; ?>
                    >
                        Shopping
                    </option>

                    <option
                        value="Bills"
                        <?= $category === "Bills" ? "selected" : ""; ?>
                    >
                        Bills
                    </option>

                    <option
                        value="Health"
                        <?= $category === "Health" ? "selected" : ""; ?>
                    >
                        Health
                    </option>

                    <option
                        value="Education"
                        <?= $category === "Education" ? "selected" : ""; ?>
                    >
                        Education
                    </option>

                    <option
                        value="Entertainment"
                        <?= $category === "Entertainment" ? "selected" : ""; ?>
                    >
                        Entertainment
                    </option>

                    <option
                        value="Other"
                        <?= $category === "Other" ? "selected" : ""; ?>
                    >
                        Other
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    maxlength="255"
                    rows="3"
                ><?= htmlspecialchars($description); ?></textarea>

            </div>

            <div class="form-group">

                <label for="frequency">
                    Frequency
                </label>

                <select
                    id="frequency"
                    name="frequency"
                    required
                >

                    <option
                        value="daily"
                        <?= $frequency === "daily" ? "selected" : ""; ?>
                    >
                        Daily
                    </option>

                    <option
                        value="weekly"
                        <?= $frequency === "weekly" ? "selected" : ""; ?>
                    >
                        Weekly
                    </option>

                    <option
                        value="monthly"
                        <?= $frequency === "monthly" ? "selected" : ""; ?>
                    >
                        Monthly
                    </option>

                    <option
                        value="yearly"
                        <?= $frequency === "yearly" ? "selected" : ""; ?>
                    >
                        Yearly
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label for="start_date">
                    Start Date
                </label>

                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?= htmlspecialchars($start_date); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="next_date">
                    Next Date
                </label>

                <input
                    type="date"
                    id="next_date"
                    name="next_date"
                    value="<?= htmlspecialchars($next_date); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="end_date">
                    End Date
                    <span class="optional-label">(Optional)</span>
                </label>

                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?= htmlspecialchars($end_date); ?>"
                >

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn-primary"
                >
                    Save Recurring
                </button>

                <a
                    href="index.php"
                    class="btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
