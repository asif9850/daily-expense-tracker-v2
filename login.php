<?php

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";

redirect_if_logged_in();

$is_auth_page = true;

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf_token)) {
        $error = "Invalid request.";
    } else {
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($email === "" || $password === "") {
        $error = "Email and password are required.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, name, password_hash FROM users WHERE email = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user["password_hash"])) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];

            require_once __DIR__ . "/includes/functions.php";
            $prefs = get_user_preferences($conn, $user["id"]);
            $authoritative_theme = $prefs["theme"] ?? "light";
            $_SESSION["theme"] = $authoritative_theme;
            setcookie("expense_tracker_theme", $authoritative_theme, [
                "expires" => time() + 31536000,
                "path" => "/",
                "samesite" => "Lax"
            ]);
            sync_user_notifications($conn, $user["id"], true);

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
}
$page_title = "Login";
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

        <h1>Welcome</h1>

        <p class="auth-subtitle">
            Login to manage your expenses.
        </p>

        <?php if (isset($_GET["registered"])): ?>

            <div class="alert alert-success">
                Account created successfully. Please login.
            </div>

        <?php endif; ?>

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

                <label for="email">Email</label>

                <input
                    type="email"
                    id="email"
                    name="email"
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

            <button type="submit" class="btn-primary">
                Login
            </button>

        </form>

        <p class="auth-footer">

            Don't have an account?

            <a href="register.php">Create Account</a>

        </p>

    </div>

</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
