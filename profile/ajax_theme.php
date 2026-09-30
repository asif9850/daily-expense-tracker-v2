<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

header("Content-Type: application/json");

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$user_id = get_user_id();

// Read POST form data or JSON input
$theme = $_POST["theme"] ?? "";
$csrf = $_POST["csrf_token"] ?? "";

if (!$theme || !$csrf) {
    $raw_input = file_get_contents("php://input");
    $input_data = json_decode($raw_input, true);
    if (is_array($input_data)) {
        $theme = $theme ?: ($input_data["theme"] ?? "light");
        $csrf = $csrf ?: ($input_data["csrf_token"] ?? "");
    }
}
$theme = $theme ?: "light";

if (!verify_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Invalid CSRF token."]);
    exit;
}

$theme = in_array($theme, ["light", "dark"], true) ? $theme : "light";

// Fetch current preferences to keep other settings intact
$prefs = get_user_preferences($conn, $user_id);
update_user_preferences(
    $conn,
    $user_id,
    $theme,
    $prefs["reminder_days"] ?? 3,
    $prefs["upcoming_reminders"] ?? 1,
    $prefs["recurring_reminders"] ?? 1,
    $prefs["budget_alerts"] ?? 1
);

setcookie("expense_tracker_theme", $theme, [
    "expires" => time() + 31536000,
    "path" => "/",
    "samesite" => "Lax"
]);

echo json_encode([
    "success" => true,
    "theme" => $theme
]);
exit;
