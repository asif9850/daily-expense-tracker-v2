<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

$user_id = get_user_id();

/* Only POST Requests Allowed */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

/* CSRF Protection */

$csrf_token = $_POST["csrf_token"] ?? "";

if (!verify_csrf_token($csrf_token)) {
    die("Invalid request.");
}

/* Get Recurring Expense ID */

$id = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: index.php");
    exit;
}

/* Delete Recurring Expense */

$stmt = mysqli_prepare(
    $conn,
    "
    DELETE FROM recurring_expenses
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
    "DELETE FROM notifications WHERE user_id = ? AND type = 'recurring' AND source_id = ?"
);
if ($notif_stmt) {
    mysqli_stmt_bind_param($notif_stmt, "ii", $user_id, $id);
    mysqli_stmt_execute($notif_stmt);
    mysqli_stmt_close($notif_stmt);
}

invalidate_notification_sync();

/* Redirect */

header("Location: index.php");
exit;
