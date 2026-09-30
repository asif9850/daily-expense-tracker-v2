<?php

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";

redirect_if_logged_in();

$is_auth_page = true;

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf_token)) {
        die("Invalid request.");
    }

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($name === "" || $email === "" || $password === "" || $confirm_password === "") {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Email is already registered.";
        } else {
            mysqli_stmt_close($stmt);

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $name,
                $email,
                $password_hash
            );

            if (mysqli_stmt_execute($stmt)) {
                $new_user_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                require_once __DIR__ . "/includes/functions.php";
                get_user_preferences($conn, $new_user_id);

                header("Location: login.php?registered=1");
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
        }

        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
    }
}

$page_title = "Register";
$page_css = ["forms"];

require_once __DIR__ . "/includes/header.php";

?>

<div class="auth-container">

    <div class="auth-card">

        <div style="display: flex; justify-content: flex-end; margin-bottom: 8px;">
            <button
                type="button"
                class="theme-toggle-btn auth-theme-btn"
                aria-label="Toggle dark mode"
                title="Toggle light/dark theme">
                <svg class="theme-icon-sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <svg class="theme-icon-moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>
        </div>

        <h1>Create Account</h1>

        <p class="auth-subtitle">
            Register to start tracking your expenses.
        </p>

        <?php if ($error !== ""): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token()); ?>">

            <div class="form-group">

                <label for="name">Name</label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    value="<?= htmlspecialchars($_POST["name"] ?? ""); ?>"
                    required>

            </div>

            <div class="form-group">

                <label for="email">Email</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    value="<?= htmlspecialchars($_POST["email"] ?? ""); ?>"
                    required>

            </div>

            <div class="form-group">

                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    required>

            </div>

            <div class="form-group">

                <label for="confirm_password">Confirm Password</label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    required>

            </div>

            <button type="submit" class="btn-primary">
                Create Account
            </button>

        </form>

        <p class="auth-footer">

            Already have an account?

            <a href="login.php">Login</a>

        </p>

    </div>

</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
