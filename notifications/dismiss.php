<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

$user_id = get_user_id();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /daily-expense-tracker-v2/notifications/index.php");
    exit;
}

$csrf_token = $_POST["csrf_token"] ?? "";
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    if (!empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest") {
        header("Content-Type: application/json");
        echo json_encode(["success" => false, "message" => "Invalid CSRF token."]);
        exit;
    }
    die("Invalid security token.");
}

$action = $_POST["action"] ?? "";
$return_url = $_POST["return_url"] ?? "/daily-expense-tracker-v2/notifications/index.php";

// Sanitize return URL to prevent open redirect
if (!str_starts_with($return_url, "/daily-expense-tracker-v2/")) {
    $return_url = "/daily-expense-tracker-v2/notifications/index.php";
}

if ($action === "dismiss_all") {
    dismiss_all_notifications($conn, $user_id);
} else {
    $notification_id = isset($_POST["notification_id"]) ? (int)$_POST["notification_id"] : 0;
    if ($notification_id > 0) {
        dismiss_notification($conn, $user_id, $notification_id);
    }
}

if (!empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest") {
    $unread_count = get_unread_notifications_count($conn, $user_id);
    header("Content-Type: application/json");
    echo json_encode(["success" => true, "unread_count" => $unread_count]);
    exit;
}

header("Location: " . $return_url);
exit;
