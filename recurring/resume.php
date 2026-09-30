<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

$user_id = get_user_id();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$csrf_token = $_POST["csrf_token"] ?? "";

if (!verify_csrf_token($csrf_token)) {
    die("Invalid request.");
}

$id = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "
    UPDATE recurring_expenses
    SET status = 'active'
    WHERE id = ?
    AND user_id = ?
    AND status = 'paused'
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

invalidate_notification_sync();

header("Location: index.php?resumed=1");
exit;
