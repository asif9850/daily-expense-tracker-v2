<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_login();

$page_title = "Change Password";
$page_css = ["profile", "forms"];

$user_id = get_user_id();

$errors = [];
$success = false;

/* Change Password */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf_token)) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($current_password === "") {
        $errors[] = "Current password is required.";
    }

    if ($new_password === "") {
        $errors[] = "New password is required.";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters.";
    }

    if ($confirm_password === "") {
        $errors[] = "Please confirm your new password.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }

    /* Verify Current Password */

    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            "
            SELECT password_hash
            FROM users
            WHERE id = ?
            LIMIT 1
            "
        );

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if (!$user || !password_verify($current_password, $user["password_hash"])) {
            $errors[] = "Current password is incorrect.";
        }
    }

    /* Update Password */

    if (empty($errors)) {
        $password_hash = password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );

        $stmt = mysqli_prepare(
            $conn,
            "
            UPDATE users
            SET password_hash = ?
            WHERE id = ?
            "
        );

        mysqli_stmt_bind_param(
            $stmt,
            "si",
            $password_hash,
            $user_id
        );

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

session_regenerate_id(true);

$success = true;
    }
    }
}

require_once __DIR__ . "/../includes/header.php";
?>

<div class="profile-page">

    <div class="page-header">
        <div>
            <h1>Change Password</h1>
            <p>Update your account password.</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>

        <div class="alert alert-error">

            <?php foreach ($errors as $error): ?>

                <p><?= htmlspecialchars($error); ?></p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="alert alert-success">
            Password changed successfully.
        </div>

    <?php endif; ?>

    <div class="profile-card">

        <form method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token()); ?>">

            <div class="form-group">

                <label for="current_password">
                    Current Password
                </label>

                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    required>

            </div>

            <div class="form-group">

                <label for="new_password">
                    New Password
                </label>

                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    minlength="8"
                    required>

            </div>

            <div class="form-group">

                <label for="confirm_password">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    required>

            </div>

            <div class="profile-actions">

                <button
                    type="submit"
                    class="btn-primary">
                    Change Password
                </button>

                <a
                    href="index.php"
                    class="btn-secondary">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
