<?php
// Central reusable functions and business logic for Daily Expense Tracker V2

require_once __DIR__ . "/db.php";

/**
 * Retrieve user preferences. If none exist, create default preferences.
 */
function get_user_preferences($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, user_id, theme, reminder_days, upcoming_reminders, recurring_reminders, budget_alerts
         FROM user_preferences
         WHERE user_id = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $prefs = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$prefs) {
        $default_theme = "light";
        $default_reminder_days = 3;
        $default_upcoming = 1;
        $default_recurring = 1;
        $default_budget = 1;

        $insert_stmt = mysqli_prepare(
            $conn,
            "INSERT INTO user_preferences (user_id, theme, reminder_days, upcoming_reminders, recurring_reminders, budget_alerts)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE updated_at = NOW()"
        );
        mysqli_stmt_bind_param(
            $insert_stmt,
            "isiiii",
            $user_id,
            $default_theme,
            $default_reminder_days,
            $default_upcoming,
            $default_recurring,
            $default_budget
        );
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);

        $prefs = [
            "user_id" => $user_id,
            "theme" => $default_theme,
            "reminder_days" => $default_reminder_days,
            "upcoming_reminders" => $default_upcoming,
            "recurring_reminders" => $default_recurring,
            "budget_alerts" => $default_budget
        ];
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION["theme"] = $prefs["theme"];
    }

    return $prefs;
}

/**
 * Update user preferences.
 */
function update_user_preferences($conn, $user_id, $theme, $reminder_days, $upcoming_reminders, $recurring_reminders, $budget_alerts)
{
    $theme = in_array($theme, ["light", "dark"], true) ? $theme : "light";
    $reminder_days = in_array((int)$reminder_days, [1, 2, 3], true) ? (int)$reminder_days : 3;
    $upcoming_reminders = $upcoming_reminders ? 1 : 0;
    $recurring_reminders = $recurring_reminders ? 1 : 0;
    $budget_alerts = $budget_alerts ? 1 : 0;

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO user_preferences (user_id, theme, reminder_days, upcoming_reminders, recurring_reminders, budget_alerts)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            theme = VALUES(theme),
            reminder_days = VALUES(reminder_days),
            upcoming_reminders = VALUES(upcoming_reminders),
            recurring_reminders = VALUES(recurring_reminders),
            budget_alerts = VALUES(budget_alerts)"
    );
    mysqli_stmt_bind_param(
        $stmt,
        "isiiii",
        $user_id,
        $theme,
        $reminder_days,
        $upcoming_reminders,
        $recurring_reminders,
        $budget_alerts
    );
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION["theme"] = $theme;
    }

    return $success;
}

/**
 * Insert a notification if it doesn't already exist for this user.
 */
function insert_notification_if_not_exists($conn, $user_id, $type, $source_id, $title, $message, $due_date, $reminder_offset, $notification_key)
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT IGNORE INTO notifications
         (user_id, type, source_id, title, message, due_date, reminder_offset, notification_key, is_read, is_dismissed)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)"
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param(
        $stmt,
        "isisssis",
        $user_id,
        $type,
        $source_id,
        $title,
        $message,
        $due_date,
        $reminder_offset,
        $notification_key
    );
    $executed = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $executed;
}

/**
 * Invalidate notification sync throttle cache (e.g. after adding/editing/deleting expenses, budgets, reminders).
 */
function invalidate_notification_sync()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION["last_notification_sync_time"]);
        unset($_SESSION["last_notification_sync_date"]);
    }
}

/**
 * Scan upcoming, recurring, and budgets to generate eligible notifications.
 * Controlled & throttled to prevent redundant queries on every normal page request.
 */
function sync_user_notifications($conn, $user_id, $force = false)
{
    if (!$user_id) {
        return;
    }

    $now = time();
    $today = date("Y-m-d");
    $last_sync = (int) ($_SESSION["last_notification_sync_time"] ?? 0);
    $last_user = (int) ($_SESSION["last_notification_sync_user"] ?? 0);
    $last_date = (string) ($_SESSION["last_notification_sync_date"] ?? "");

    // Throttle automatic scans to once every 120 seconds unless forced, user switched, or calendar day rolled over
    if (!$force && $last_user === (int)$user_id && $last_date === $today && ($now - $last_sync) < 120) {
        return;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION["last_notification_sync_time"] = $now;
        $_SESSION["last_notification_sync_user"] = (int)$user_id;
        $_SESSION["last_notification_sync_date"] = $today;
    }

    $prefs = get_user_preferences($conn, $user_id);
    $lead_days = (int) ($prefs["reminder_days"] ?? 3);

    // 1. Upcoming Expense Reminders
    if (!empty($prefs["upcoming_reminders"])) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, amount, category, description, due_date
             FROM upcoming_expenses
             WHERE user_id = ? AND status = 'upcoming' AND due_date <= DATE_ADD(?, INTERVAL ? DAY)"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isi", $user_id, $today, $lead_days);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $due_date = $row["due_date"];
                $item_name = htmlspecialchars($row["category"] . ($row["description"] ? " - " . $row["description"] : ""));
                $formatted_amount = "₹" . number_format($row["amount"], 2);

                if ($due_date < $today) {
                    // Overdue: Only for unpaid active upcoming items
                    $key = "upcoming_" . $row["id"] . "_" . $due_date . "_overdue";
                    $title = "Upcoming Expense Overdue";
                    $message = "Your upcoming expense for " . $item_name . " (" . $formatted_amount . ") was due on " . date("d M Y", strtotime($due_date)) . ".";
                    insert_notification_if_not_exists($conn, $user_id, "upcoming", $row["id"], $title, $message, $due_date, 0, $key);

                    // Supersede earlier advance/today alerts for this item & due date
                    $sup_stmt = mysqli_prepare($conn, "UPDATE notifications SET is_dismissed = 1 WHERE user_id = ? AND type = 'upcoming' AND source_id = ? AND due_date = ? AND notification_key != ? AND is_dismissed = 0");
                    if ($sup_stmt) {
                        mysqli_stmt_bind_param($sup_stmt, "iiss", $user_id, $row["id"], $due_date, $key);
                        mysqli_stmt_execute($sup_stmt);
                        mysqli_stmt_close($sup_stmt);
                    }
                } elseif ($due_date === $today) {
                    // Due Today: Only for unpaid active upcoming items
                    $key = "upcoming_" . $row["id"] . "_" . $due_date . "_today";
                    $title = "Upcoming Expense Due Today";
                    $message = "Your upcoming expense for " . $item_name . " (" . $formatted_amount . ") is due today.";
                    insert_notification_if_not_exists($conn, $user_id, "upcoming", $row["id"], $title, $message, $due_date, 0, $key);

                    // Supersede earlier advance alerts for this item & due date
                    $sup_stmt = mysqli_prepare($conn, "UPDATE notifications SET is_dismissed = 1 WHERE user_id = ? AND type = 'upcoming' AND source_id = ? AND due_date = ? AND notification_key != ? AND is_dismissed = 0");
                    if ($sup_stmt) {
                        mysqli_stmt_bind_param($sup_stmt, "iiss", $user_id, $row["id"], $due_date, $key);
                        mysqli_stmt_execute($sup_stmt);
                        mysqli_stmt_close($sup_stmt);
                    }
                } else {
                    // Within lead window: Single advance reminder per due date (no repeated lead3/lead2/lead1 notifications)
                    $days_diff = (int) round((strtotime($due_date) - strtotime($today)) / 86400);
                    if ($days_diff > 0 && $days_diff <= $lead_days) {
                        $key = "upcoming_" . $row["id"] . "_" . $due_date . "_advance";
                        $title = "Upcoming Expense Due Soon";
                        $day_str = $days_diff === 1 ? "tomorrow" : "in {$days_diff} days";
                        $message = "Your upcoming expense for " . $item_name . " (" . $formatted_amount . ") is due " . $day_str . " (" . date("d M Y", strtotime($due_date)) . ").";
                        insert_notification_if_not_exists($conn, $user_id, "upcoming", $row["id"], $title, $message, $due_date, $days_diff, $key);
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 2. Recurring Expense Reminders
    if (!empty($prefs["recurring_reminders"])) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, amount, category, description, next_date, end_date
             FROM recurring_expenses
             WHERE user_id = ? AND status = 'active' AND next_date <= DATE_ADD(?, INTERVAL ? DAY)"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isi", $user_id, $today, $lead_days);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $next_date = $row["next_date"];
                // Check end_date if set
                if (!empty($row["end_date"]) && $next_date > $row["end_date"]) {
                    continue;
                }

                $item_name = htmlspecialchars($row["category"] . ($row["description"] ? " - " . $row["description"] : ""));
                $formatted_amount = "₹" . number_format($row["amount"], 2);

                if ($next_date < $today) {
                    // Recurring Overdue: Only for active recurring expenses
                    $key = "recurring_" . $row["id"] . "_" . $next_date . "_overdue";
                    $title = "Recurring Expense Overdue";
                    $message = "Recurring payment for " . $item_name . " (" . $formatted_amount . ") was due on " . date("d M Y", strtotime($next_date)) . ".";
                    insert_notification_if_not_exists($conn, $user_id, "recurring", $row["id"], $title, $message, $next_date, 0, $key);

                    // Supersede earlier advance/today alerts for this item & cycle date
                    $sup_stmt = mysqli_prepare($conn, "UPDATE notifications SET is_dismissed = 1 WHERE user_id = ? AND type = 'recurring' AND source_id = ? AND due_date = ? AND notification_key != ? AND is_dismissed = 0");
                    if ($sup_stmt) {
                        mysqli_stmt_bind_param($sup_stmt, "iiss", $user_id, $row["id"], $next_date, $key);
                        mysqli_stmt_execute($sup_stmt);
                        mysqli_stmt_close($sup_stmt);
                    }
                } elseif ($next_date === $today) {
                    // Recurring Due Today: Only for active recurring expenses
                    $key = "recurring_" . $row["id"] . "_" . $next_date . "_today";
                    $title = "Recurring Expense Due Today";
                    $message = "Recurring payment for " . $item_name . " (" . $formatted_amount . ") is due today.";
                    insert_notification_if_not_exists($conn, $user_id, "recurring", $row["id"], $title, $message, $next_date, 0, $key);

                    // Supersede earlier advance alerts for this item & cycle date
                    $sup_stmt = mysqli_prepare($conn, "UPDATE notifications SET is_dismissed = 1 WHERE user_id = ? AND type = 'recurring' AND source_id = ? AND due_date = ? AND notification_key != ? AND is_dismissed = 0");
                    if ($sup_stmt) {
                        mysqli_stmt_bind_param($sup_stmt, "iiss", $user_id, $row["id"], $next_date, $key);
                        mysqli_stmt_execute($sup_stmt);
                        mysqli_stmt_close($sup_stmt);
                    }
                } else {
                    // Within lead window: Single advance reminder per recurring due date
                    $days_diff = (int) round((strtotime($next_date) - strtotime($today)) / 86400);
                    if ($days_diff > 0 && $days_diff <= $lead_days) {
                        $key = "recurring_" . $row["id"] . "_" . $next_date . "_advance";
                        $title = "Recurring Expense Due Soon";
                        $day_str = $days_diff === 1 ? "tomorrow" : "in {$days_diff} days";
                        $message = "Recurring payment for " . $item_name . " (" . $formatted_amount . ") is due " . $day_str . " (" . date("d M Y", strtotime($next_date)) . ").";
                        insert_notification_if_not_exists($conn, $user_id, "recurring", $row["id"], $title, $message, $next_date, $days_diff, $key);
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 3. Budget Alerts (Threshold-crossing logic for 80%, 100%, and >100%)
    if (!empty($prefs["budget_alerts"])) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, amount, period_start, period_end
             FROM budgets
             WHERE user_id = ? AND ? BETWEEN period_start AND period_end"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "is", $user_id, $today);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($budget = mysqli_fetch_assoc($result)) {
                if ((float)$budget["amount"] <= 0) {
                    continue;
                }

                $budget_id = (int)$budget["id"];
                $budget_limit = (float) $budget["amount"];
                $period_start = $budget["period_start"];
                $period_end = $budget["period_end"];
                $month_label = date("F Y", strtotime($period_start));

                $exp_stmt = mysqli_prepare(
                    $conn,
                    "SELECT COALESCE(SUM(amount), 0) AS total_spent
                     FROM expenses
                     WHERE user_id = ? AND expense_date BETWEEN ? AND ?"
                );
                if ($exp_stmt) {
                    mysqli_stmt_bind_param($exp_stmt, "iss", $user_id, $period_start, $period_end);
                    mysqli_stmt_execute($exp_stmt);
                    $exp_result = mysqli_stmt_get_result($exp_stmt);
                    $exp_row = mysqli_fetch_assoc($exp_result);
                    mysqli_stmt_close($exp_stmt);

                    $spent = (float) ($exp_row["total_spent"] ?? 0);
                    $usage_percent = ($spent / $budget_limit) * 100;

                    // 80% Threshold: triggers when spent crosses or reaches 80%
                    if ($usage_percent >= 80) {
                        $key = "budget_" . $budget_id . "_" . $period_start . "_80";
                        $title = "Budget Warning";
                        $message = "You have used " . round($usage_percent) . "% of your budget for " . $month_label . " (₹" . number_format($spent, 2) . " of ₹" . number_format($budget_limit, 2) . ").";
                        insert_notification_if_not_exists($conn, $user_id, "budget", $budget_id, $title, $message, $period_end, null, $key);
                    }

                    // 100% Threshold: triggers when spent crosses or reaches 100%
                    if ($usage_percent >= 100) {
                        $key = "budget_" . $budget_id . "_" . $period_start . "_100";
                        $title = "Budget Limit Reached";
                        $message = "You have reached 100% of your budget limit for " . $month_label . " (₹" . number_format($budget_limit, 2) . ").";
                        insert_notification_if_not_exists($conn, $user_id, "budget", $budget_id, $title, $message, $period_end, null, $key);
                    }

                    // Above 100% Threshold: triggers when budget is exceeded
                    if ($usage_percent > 100) {
                        $key = "budget_" . $budget_id . "_" . $period_start . "_exceeded";
                        $title = "Budget Exceeded";
                        $over_amount = $spent - $budget_limit;
                        $message = "You have exceeded your monthly budget for " . $month_label . " by ₹" . number_format($over_amount, 2) . " (Total spent: ₹" . number_format($spent, 2) . " / " . round($usage_percent) . "%).";
                        insert_notification_if_not_exists($conn, $user_id, "budget", $budget_id, $title, $message, $period_end, null, $key);
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}

/**
 * Get unread notification count for badge.
 */
function get_unread_notifications_count($conn, $user_id)
{
    if (!$user_id) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS unread_count
         FROM notifications
         WHERE user_id = ? AND is_read = 0 AND is_dismissed = 0"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row["unread_count"] ?? 0);
}

/**
 * Retrieve notifications with filters.
 */
function get_user_notifications($conn, $user_id, $filter = "all", $limit = 50)
{
    $where = "WHERE user_id = ? AND is_dismissed = 0";
    $types = "i";
    $params = [$user_id];

    if ($filter === "unread") {
        $where .= " AND is_read = 0";
    } elseif (in_array($filter, ["upcoming", "recurring", "budget"], true)) {
        $where .= " AND type = ?";
        $types .= "s";
        $params[] = $filter;
    }

    $sql = "SELECT id, type, source_id, title, message, due_date, reminder_offset, notification_key, is_read, is_dismissed, created_at
            FROM notifications
            $where
            ORDER BY is_read ASC, created_at DESC, id DESC
            LIMIT ?";
    $types .= "i";
    $params[] = $limit;

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $items;
}

/**
 * Mark notification as read / unread.
 */
function mark_notification_read_status($conn, $user_id, $notification_id, $is_read = 1)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifications
         SET is_read = ?
         WHERE id = ? AND user_id = ?"
    );
    $status = $is_read ? 1 : 0;
    mysqli_stmt_bind_param($stmt, "iii", $status, $notification_id, $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $success;
}

/**
 * Mark all notifications as read.
 */
function mark_all_notifications_read($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifications
         SET is_read = 1
         WHERE user_id = ? AND is_dismissed = 0 AND is_read = 0"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $success;
}

/**
 * Dismiss a notification (hide from active list).
 */
function dismiss_notification($conn, $user_id, $notification_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifications
         SET is_dismissed = 1
         WHERE id = ? AND user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $success;
}

/**
 * Dismiss all active notifications.
 */
function dismiss_all_notifications($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifications
         SET is_dismissed = 1
         WHERE user_id = ? AND is_dismissed = 0"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $success;
}

/**
 * Format notification data for clean, scannable, user-friendly UI presentation.
 */
function format_notification_card($notif)
{
    $type = $notif["type"] ?? "system";
    $raw_title = $notif["title"] ?? "";
    $raw_message = $notif["message"] ?? "";
    $due_date = $notif["due_date"] ?? null;
    $today = date("Y-m-d");

    $item_title = $raw_title;
    $amount_badge = "";
    $status_label = "";
    $status_class = "status-info";
    $status_icon = "";
    $subtitle = "";

    if ($type === "upcoming" || $type === "recurring") {
        // Extract item name and amount if embedded in standard message
        if (preg_match('/(?:Your upcoming expense|Recurring payment) for (.+?) \(([^\)]+)\)/i', $raw_message, $m)) {
            $item_title = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $amount_badge = $m[2];
        } else {
            $item_title = $raw_title;
        }

        if ($due_date) {
            $due_time = strtotime($due_date);
            $today_time = strtotime($today);
            $diff_days = (int) round(($due_time - $today_time) / 86400);

            if ($diff_days < 0) {
                $days_ago = abs($diff_days);
                $status_label = "Overdue";
                $status_class = "status-danger";
                $status_icon = "⚠️";
                $ago_text = $days_ago === 1 ? "1 day overdue" : "{$days_ago} days overdue";
                $subtitle = "Was due on <strong>" . date("d M Y", $due_time) . "</strong> • <span class='text-danger'>{$ago_text}</span>";
            } elseif ($diff_days === 0) {
                $status_label = "Due Today";
                $status_class = "status-warning";
                $status_icon = "⚡";
                $subtitle = "Payment is due <strong>today (" . date("d M Y", $due_time) . ")</strong>";
            } else {
                $status_label = $diff_days === 1 ? "Due Tomorrow" : "Due in {$diff_days} days";
                $status_class = "status-primary";
                $status_icon = "🕒";
                $subtitle = "Scheduled for <strong>" . date("d M Y", $due_time) . "</strong>";
            }
        } else {
            $subtitle = htmlspecialchars($raw_message);
        }
    } elseif ($type === "budget") {
        $status_icon = "📊";
        $item_title = $raw_title;

        if (stripos($raw_title, "exceeded") !== false) {
            $status_label = "Budget Exceeded";
            $status_class = "status-danger";
            $status_icon = "🚨";
            if (preg_match('/by (₹?[0-9,.]+).*?\(Total spent:\s*(₹?[0-9,.]+)\s*\/\s*(\d+%)\)/i', $raw_message, $m)) {
                $amount_badge = $m[3] . " Used";
                $subtitle = "Exceeded by <strong>{$m[1]}</strong> • Total spent: <strong>{$m[2]}</strong>";
            } else {
                $subtitle = htmlspecialchars($raw_message);
            }
        } elseif (stripos($raw_title, "limit reached") !== false || stripos($raw_message, "100%") !== false) {
            $status_label = "100% Limit";
            $status_class = "status-danger";
            $status_icon = "🛑";
            if (preg_match('/\((₹?[0-9,.]+)\)/i', $raw_message, $m)) {
                $amount_badge = "100% Limit";
                $subtitle = "Reached full budget limit of <strong>{$m[1]}</strong>";
            } else {
                $subtitle = htmlspecialchars($raw_message);
            }
        } else {
            $status_label = "Budget Warning";
            $status_class = "status-warning";
            $status_icon = "⚠️";
            if (preg_match('/used (\d+%) of your budget for (.+?) \((₹?[0-9,.]+) of (₹?[0-9,.]+)\)/i', $raw_message, $m)) {
                $amount_badge = $m[1] . " Used";
                $subtitle = "Used <strong>{$m[3]}</strong> of <strong>{$m[4]}</strong> budget for {$m[2]}";
            } else {
                $subtitle = htmlspecialchars($raw_message);
            }
        }
    } else {
        $subtitle = htmlspecialchars($raw_message);
    }

    return [
        "item_title" => $item_title,
        "amount_badge" => $amount_badge,
        "status_label" => $status_label,
        "status_class" => $status_class,
        "status_icon" => $status_icon,
        "subtitle" => $subtitle
    ];
}

/**
 * Format currency amount with Rupee symbol and 2 decimal places.
 */
function format_currency($amount, $show_symbol = true)
{
    return ($show_symbol ? "₹" : "") . number_format((float)$amount, 2);
}

/**
 * Log SQL error details internally without exposing sensitive database structures to end-users.
 */
function check_sql_errors($conn, $context = '')
{
    if ($conn && mysqli_error($conn)) {
        error_log("[DailyExpenseTracker SQL Error" . ($context ? " in $context" : "") . "] " . mysqli_error($conn));
        return true;
    }
    return false;
}
