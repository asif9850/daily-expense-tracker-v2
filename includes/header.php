<?php

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/functions.php";

$page_title = $page_title ?? "Daily Expense Tracker";
$page_css = $page_css ?? [];

global $conn;
$current_theme = "light";

// Check client-side cookie (used for guest fallback or client sync)
$cookie_theme = null;
if (isset($_COOKIE["expense_tracker_theme"]) && in_array($_COOKIE["expense_tracker_theme"], ["light", "dark"], true)) {
    $cookie_theme = $_COOKIE["expense_tracker_theme"];
}

if (is_logged_in()) {
    $user_id = get_user_id();
    // Persisted database preference is authoritative for logged-in users
    if ($user_id && isset($conn)) {
        if (!isset($_SESSION["theme"])) {
            $prefs = get_user_preferences($conn, $user_id);
            $_SESSION["theme"] = $prefs["theme"] ?? "light";
        }
    }
    $current_theme = $_SESSION["theme"] ?? "light";

    // Keep cookie synchronized with the authoritative preference
    if ($cookie_theme !== $current_theme) {
        setcookie("expense_tracker_theme", $current_theme, [
            "expires" => time() + 31536000,
            "path" => "/",
            "samesite" => "Lax"
        ]);
    }
} else {
    // For guest users, cookie or default is authoritative
    $current_theme = $cookie_theme ?? "light";
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($current_theme); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>

    <script>
        (function () {
            try {
                var isLoggedIn = <?= json_encode(is_logged_in()); ?>;
                var serverTheme = <?= json_encode($current_theme); ?>;
                window.isLoggedIn = isLoggedIn;
                window.serverTheme = serverTheme;
                if (isLoggedIn) {
                    // For logged-in users, server/database preference is authoritative
                    document.documentElement.setAttribute("data-theme", serverTheme);
                    localStorage.setItem("expense_tracker_theme", serverTheme);
                } else {
                    // For guest users, localStorage or cookie is authoritative
                    var localTheme = localStorage.getItem("expense_tracker_theme");
                    if (localTheme === "dark" || localTheme === "light") {
                        document.documentElement.setAttribute("data-theme", localTheme);
                    } else if (serverTheme) {
                        document.documentElement.setAttribute("data-theme", serverTheme);
                    }
                }
            } catch (e) {}
        })();
    </script>

    <!-- Base & Navbar Core Styles -->
    <link rel="stylesheet" href="/daily-expense-tracker-v2/css/base.css?v=13">
    <link rel="stylesheet" href="/daily-expense-tracker-v2/css/navbar.css?v=13">
    <link rel="stylesheet" href="/daily-expense-tracker-v2/css/charts.css?v=13">

    <!-- Page Specific Stylesheets -->
    <?php foreach ($page_css as $css_file): ?>
        <link rel="stylesheet" href="/daily-expense-tracker-v2/css/<?= htmlspecialchars($css_file); ?>.css?v=13">
    <?php endforeach; ?>

    <!-- Master Theme Overrides (Loaded last to ensure dark mode prevails) -->
    <link rel="stylesheet" href="/daily-expense-tracker-v2/css/theme.css?v=13">
</head>

<body>

<?php
if (empty($is_auth_page)) {
    require_once __DIR__ . "/navbar.php";
}
?>

<main class="main-content">
