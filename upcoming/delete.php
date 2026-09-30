<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$csrf_token = $_POST["csrf_token"] ?? "";
$id = (int) ($_POST["id"] ?? 0);

if (!verify_csrf_token($csrf_token) || $id <= 0) {
    header("Location: index.php");
    exit;
}

$user_id = get_user_id();

$stmt = mysqli_prepare(
    $conn,
    "
    DELETE FROM upcoming_expenses
    WHERE id = ?
    AND user_id = ?
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $id,
    $user_id
);

mysqli_stmt_execute($stmt);

mysqli_stmt_close($stmt);

$notif_stmt = mysqli_prepare(
    $conn,
    "DELETE FROM notifications WHERE user_id = ? AND type = 'upcoming' AND source_id = ?"
);
if ($notif_stmt) {
    mysqli_stmt_bind_param($notif_stmt, "ii", $user_id, $id);
    mysqli_stmt_execute($notif_stmt);
    mysqli_stmt_close($notif_stmt);
}

invalidate_notification_sync();

header("Location: index.php");
exit;
