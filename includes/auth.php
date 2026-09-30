<?php
// Central authentication/session helpers.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        "httponly" => true,
        "samesite" => "Lax",
        "secure" => !empty($_SERVER["HTTPS"])
    ]);

    session_start();
}

/* Require Login */

function require_login()
{
    if (!isset($_SESSION["user_id"])) {
        header("Location: /daily-expense-tracker-v2/login.php");
        exit;
    }
}

/* Check Login Status */

function is_logged_in()
{
    return isset($_SESSION["user_id"]);
}

/* Get Logged-in User ID */

function get_user_id()
{
    return $_SESSION["user_id"] ?? null;
}

function redirect_if_logged_in()
{
    if (is_logged_in()) {
        header("Location: /daily-expense-tracker-v2/dashboard.php");
        exit;
    }
}

function csrf_token()
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function verify_csrf_token($token)
{
    return isset($_SESSION["csrf_token"])
        && hash_equals($_SESSION["csrf_token"], $token);
}
