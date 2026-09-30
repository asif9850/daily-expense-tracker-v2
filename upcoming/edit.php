<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

$page_title = "Edit Upcoming Expense";
$page_css = ["forms", "upcoming"];

$user_id = get_user_id();

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT amount, category, due_date, description, status
    FROM upcoming_expenses
    WHERE id = ? AND user_id = ?
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

$expense = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$expense) {
    header("Location: index.php");
    exit;
}

if ($expense["status"] === "paid") {
    header("Location: index.php");
    exit;
}

$amount = $expense["amount"];
$category = $expense["category"];
$due_date = $expense["due_date"];
$description = $expense["description"] ?? "";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";

    $amount = trim($_POST["amount"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $due_date = $_POST["due_date"] ?? "";
    $description = trim($_POST["description"] ?? "");

    if (!verify_csrf_token($csrf_token)) {
        $error = "Invalid request.";
    } elseif ($amount === "" || $category === "" || $due_date === "") {
        $error = "Amount, category and due date are required.";
    } elseif (!is_numeric($amount) || (float) $amount <= 0) {
        $error = "Amount must be greater than 0.";
    } else {
        $amount_value = (float) $amount;

        $stmt = mysqli_prepare(
            $conn,
            "
            UPDATE upcoming_expenses
            SET amount = ?,
                category = ?,
                due_date = ?,
                description = ?
            WHERE id = ?
            AND user_id = ?
            AND status = 'upcoming'
            "
        );

        mysqli_stmt_bind_param(
            $stmt,
            "dsssii",
            $amount_value,
            $category,
            $due_date,
            $description,
            $id,
            $user_id
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            invalidate_notification_sync();

            header("Location: index.php");
            exit;
        } else {
            $error = "Failed to update upcoming expense.";

            mysqli_stmt_close($stmt);
        }
    }
}

require_once __DIR__ . "/../includes/header.php";

?>

<div class="form-page">

    <div class="form-card">

        <div class="page-header">

            <div>

                <h1>Edit Upcoming Expense</h1>

                <p>
                    Update the upcoming expense.
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

                    <option value="Food" <?= $category === "Food" ? "selected" : ""; ?>>
                        Food
                    </option>

                    <option value="Transport" <?= $category === "Transport" ? "selected" : ""; ?>>
                        Transport
                    </option>

                    <option value="Shopping" <?= $category === "Shopping" ? "selected" : ""; ?>>
                        Shopping
                    </option>

                    <option value="Bills" <?= $category === "Bills" ? "selected" : ""; ?>>
                        Bills
                    </option>

                    <option value="Health" <?= $category === "Health" ? "selected" : ""; ?>>
                        Health
                    </option>

                    <option value="Education" <?= $category === "Education" ? "selected" : ""; ?>>
                        Education
                    </option>

                    <option value="Entertainment" <?= $category === "Entertainment" ? "selected" : ""; ?>>
                        Entertainment
                    </option>

                    <option value="Other" <?= $category === "Other" ? "selected" : ""; ?>>
                        Other
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label for="due_date">
                    Due Date
                </label>

                <input
                    type="date"
                    id="due_date"
                    name="due_date"
                    value="<?= htmlspecialchars($due_date); ?>"
                    required
                >

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

            <div class="form-actions">

                <button type="submit" class="btn-primary">
                    Update Upcoming
                </button>

                <a href="index.php" class="btn-secondary">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
