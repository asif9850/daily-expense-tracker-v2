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

mysqli_begin_transaction($conn);

try {
    /* Get Upcoming Expense */

    $stmt = mysqli_prepare(
        $conn,
        "
        SELECT
            amount,
            category,
            due_date,
            description,
            status
        FROM upcoming_expenses
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

    $upcoming = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$upcoming) {
        throw new Exception("Upcoming expense not found.");
    }

    if ($upcoming["status"] !== "upcoming") {
        throw new Exception("Expense is already paid.");
    }

    /* Create Actual Expense */

    $source_type = "upcoming";

    $stmt = mysqli_prepare(
        $conn,
        "
        INSERT INTO expenses
        (
            user_id,
            amount,
            category,
            expense_date,
            description,
            source_type,
            upcoming_id
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
        "
    );

    mysqli_stmt_bind_param(
        $stmt,
        "idssssi",
        $user_id,
        $upcoming["amount"],
        $upcoming["category"],
        $upcoming["due_date"],
        $upcoming["description"],
        $source_type,
        $id
    );

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        throw new Exception("Failed to create actual expense.");
    }

    mysqli_stmt_close($stmt);

    /* Mark Upcoming as Paid */

    $stmt = mysqli_prepare(
        $conn,
        "
        UPDATE upcoming_expenses
        SET status = 'paid',
            paid_at = NOW()
        WHERE id = ?
        AND user_id = ?
        AND status = 'upcoming'
        "
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $id,
        $user_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        throw new Exception("Failed to mark expense as paid.");
    }

    if (mysqli_stmt_affected_rows($stmt) !== 1) {
        mysqli_stmt_close($stmt);

        throw new Exception("Expense status was not updated.");
    }

    mysqli_stmt_close($stmt);

    /* Dismiss Active Notifications for Paid Upcoming Expense */

    $dismiss_stmt = mysqli_prepare(
        $conn,
        "UPDATE notifications SET is_dismissed = 1 WHERE user_id = ? AND type = 'upcoming' AND source_id = ?"
    );
    if ($dismiss_stmt) {
        mysqli_stmt_bind_param($dismiss_stmt, "ii", $user_id, $id);
        mysqli_stmt_execute($dismiss_stmt);
        mysqli_stmt_close($dismiss_stmt);
    }

    /* Commit */

    mysqli_commit($conn);
    invalidate_notification_sync();
    header("Location: index.php?paid=1");
    exit;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit;
}
