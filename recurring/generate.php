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

/* Get Active Recurring Expense */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        id,
        amount,
        category,
        description,
        frequency,
        next_date,
        end_date
    FROM recurring_expenses
    WHERE id = ?
    AND user_id = ?
    AND status = 'active'
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

$recurring = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$recurring) {
    header("Location: index.php");
    exit;
}

/* Check End Date */

if (
    !empty($recurring["end_date"]) &&
    $recurring["next_date"] > $recurring["end_date"]
) {
    $stmt = mysqli_prepare(
        $conn,
        "
        UPDATE recurring_expenses
        SET status = 'ended'
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

    header("Location: index.php");
    exit;
}

/* Duplicate Protection */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT id
    FROM expenses
    WHERE user_id = ?
    AND recurring_id = ?
    AND expense_date = ?
    LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "iis",
    $user_id,
    $id,
    $recurring["next_date"]
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$already_generated = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if ($already_generated) {
    header("Location: index.php");
    exit;
}

/* Generate Expense */

mysqli_begin_transaction($conn);

try {
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
            recurring_id
        )
        VALUES (?, ?, ?, ?, ?, 'recurring', ?)
        "
    );

    mysqli_stmt_bind_param(
        $stmt,
        "idsssi",
        $user_id,
        $recurring["amount"],
        $recurring["category"],
        $recurring["next_date"],
        $recurring["description"],
        $id
    );

    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    /* Calculate Next Date */

    $next_date = new DateTime($recurring["next_date"]);

    switch ($recurring["frequency"]) {
        case "daily":
            $next_date->modify("+1 day");
            break;

        case "weekly":
            $next_date->modify("+1 week");
            break;

        case "monthly":
            $next_date->modify("+1 month");
            break;

        case "yearly":
            $next_date->modify("+1 year");
            break;

        default:
            throw new Exception("Invalid recurring frequency.");
    }

    $new_next_date = $next_date->format("Y-m-d");

    /* End Date Handling */

    if (
        !empty($recurring["end_date"]) &&
        $new_next_date > $recurring["end_date"]
    ) {
        $stmt = mysqli_prepare(
            $conn,
            "
            UPDATE recurring_expenses
            SET
                next_date = ?,
                status = 'ended'
            WHERE id = ?
            AND user_id = ?
            "
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sii",
            $new_next_date,
            $id,
            $user_id
        );
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "
            UPDATE recurring_expenses
            SET next_date = ?
            WHERE id = ?
            AND user_id = ?
            "
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sii",
            $new_next_date,
            $id,
            $user_id
        );
    }

    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    // Dismiss active notifications for this recurring payment cycle
    $cycle_due_date = $recurring["next_date"];
    $dismiss_notif = mysqli_prepare(
        $conn,
        "UPDATE notifications SET is_dismissed = 1 WHERE user_id = ? AND type = 'recurring' AND source_id = ? AND due_date = ?"
    );
    if ($dismiss_notif) {
        mysqli_stmt_bind_param($dismiss_notif, "iis", $user_id, $id, $cycle_due_date);
        mysqli_stmt_execute($dismiss_notif);
        mysqli_stmt_close($dismiss_notif);
    }

    mysqli_commit($conn);
    invalidate_notification_sync();
    header("Location: index.php?generated=1");
    exit;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log("Failed to generate recurring expense: " . $e->getMessage());
    header("Location: index.php?error=" . urlencode("Failed to generate expense."));
    exit;
}
