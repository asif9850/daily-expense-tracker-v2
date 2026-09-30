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

/* Get Budget Month */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT period_start
    FROM budgets
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $id,
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$budget = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$budget) {
    header("Location: index.php");
    exit;
}

$selected_month = date(
    "Y-m",
    strtotime($budget["period_start"])
);

/* Delete Budget */

$stmt = mysqli_prepare(
    $conn,
    "
    DELETE FROM budgets
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
    "DELETE FROM notifications WHERE user_id = ? AND type = 'budget' AND source_id = ?"
);
if ($notif_stmt) {
    mysqli_stmt_bind_param($notif_stmt, "ii", $user_id, $id);
    mysqli_stmt_execute($notif_stmt);
    mysqli_stmt_close($notif_stmt);
}

invalidate_notification_sync();

header(
    "Location: index.php?month=" .
    urlencode($selected_month) .
    "&success=" . urlencode("Budget deleted successfully.")
);

exit;
