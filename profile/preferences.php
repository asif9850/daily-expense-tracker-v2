<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

$user_id = get_user_id();
$success_message = "";
$error_message = "";

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    $csrf = $_POST["csrf_token"] ?? "";
    if (!verify_csrf_token($csrf)) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        $theme = $_POST["theme"] ?? "light";
        $reminder_days = (int) ($_POST["reminder_days"] ?? 3);
        $upcoming_reminders = isset($_POST["upcoming_reminders"]) ? 1 : 0;
        $recurring_reminders = isset($_POST["recurring_reminders"]) ? 1 : 0;
        $budget_alerts = isset($_POST["budget_alerts"]) ? 1 : 0;

        $updated = update_user_preferences(
            $conn,
            $user_id,
            $theme,
            $reminder_days,
            $upcoming_reminders,
            $recurring_reminders,
            $budget_alerts
        );

        if ($updated) {
            $success_message = "Preferences updated successfully.";
            setcookie("expense_tracker_theme", $theme, [
                "expires" => time() + 31536000,
                "path" => "/",
                "samesite" => "Lax"
            ]);
            // Synchronize notifications with the updated preference lead time and toggles
            sync_user_notifications($conn, $user_id, true);
        } else {
            $error_message = "Failed to update preferences. Please try again.";
        }
    }
}

$prefs = get_user_preferences($conn, $user_id);

$page_title = "Preferences & Settings";
$page_css = ["profile", "forms"];

require_once __DIR__ . "/../includes/header.php";
?>

<div class="profile-page">

    <div class="page-header preferences-header">
        <div>
            <h1>Preferences & Settings</h1>
            <p>Customize your theme, reminder timings, and alert preferences.</p>
        </div>
        <a href="index.php" class="btn-secondary" style="font-size: 13px; padding: 7px 14px; white-space: nowrap;">
            &larr; Back to Profile
        </a>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="preferences.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">

            <!-- Appearance Section -->
            <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);">
                <h2 style="font-size: 17px; font-weight: 700; margin-bottom: 6px;">Appearance & Theme</h2>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;">
                    Choose your preferred display mode. Light Mode is default.
                </p>

                <div class="form-group">
                    <label for="theme">Interface Theme</label>
                    <select name="theme" id="theme">
                        <option value="light" <?= ($prefs["theme"] ?? "light") === "light" ? "selected" : ""; ?>>
                            ☀️ Light Mode (Default)
                        </option>
                        <option value="dark" <?= ($prefs["theme"] ?? "light") === "dark" ? "selected" : ""; ?>>
                            🌙 Dark Mode
                        </option>
                    </select>
                </div>
            </div>

            <!-- Reminder Lead Time -->
            <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);">
                <h2 style="font-size: 17px; font-weight: 700; margin-bottom: 6px;">Reminder Timing</h2>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;">
                    Choose how many days before an upcoming or recurring expense due date you wish to receive alerts.
                </p>

                <div class="form-group">
                    <label for="reminder_days">Reminder Lead Time</label>
                    <select name="reminder_days" id="reminder_days">
                        <option value="1" <?= ((int)($prefs["reminder_days"] ?? 3)) === 1 ? "selected" : ""; ?>>
                            1 Day Before Due Date
                        </option>
                        <option value="2" <?= ((int)($prefs["reminder_days"] ?? 3)) === 2 ? "selected" : ""; ?>>
                            2 Days Before Due Date
                        </option>
                        <option value="3" <?= ((int)($prefs["reminder_days"] ?? 3)) === 3 ? "selected" : ""; ?>>
                            3 Days Before Due Date (Default)
                        </option>
                    </select>
                    <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                        * Note: Expenses Due Today and Overdue expenses will always generate in-app alerts when reminders are active.
                    </small>
                </div>
            </div>

            <!-- Alert Notification Toggles -->
            <div style="margin-bottom: 24px;">
                <h2 style="font-size: 17px; font-weight: 700; margin-bottom: 6px;">Notification Modules</h2>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;">
                    Enable or disable specific notification categories.
                </p>

                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                        <input
                            type="checkbox"
                            name="upcoming_reminders"
                            value="1"
                            style="margin-top: 3px; width: 18px; height: 18px; accent-color: var(--color-primary);"
                            <?= !empty($prefs["upcoming_reminders"]) ? "checked" : ""; ?>
                        >
                        <div>
                            <strong style="font-size: 14px; display: block;">Upcoming Expense Reminders</strong>
                            <span style="font-size: 13px; color: var(--text-muted);">
                                Alerts for non-paid upcoming bills within your reminder lead window, on due date, and overdue.
                            </span>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                        <input
                            type="checkbox"
                            name="recurring_reminders"
                            value="1"
                            style="margin-top: 3px; width: 18px; height: 18px; accent-color: var(--color-primary);"
                            <?= !empty($prefs["recurring_reminders"]) ? "checked" : ""; ?>
                        >
                        <div>
                            <strong style="font-size: 14px; display: block;">Recurring Expense Reminders</strong>
                            <span style="font-size: 13px; color: var(--text-muted);">
                                Alerts for active recurring payments based on their next scheduled payment date.
                            </span>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                        <input
                            type="checkbox"
                            name="budget_alerts"
                            value="1"
                            style="margin-top: 3px; width: 18px; height: 18px; accent-color: var(--color-primary);"
                            <?= !empty($prefs["budget_alerts"]) ? "checked" : ""; ?>
                        >
                        <div>
                            <strong style="font-size: 14px; display: block;">Budget Threshold Alerts</strong>
                            <span style="font-size: 13px; color: var(--text-muted);">
                                Alerts at 80% usage (Warning), 100% usage (Limit Reached), and &gt;100% (Budget Exceeded).
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="form-actions" style="border-top: 1px solid var(--border-color); padding-top: 16px;">
                <button type="submit" class="btn-primary" style="width: auto;">
                    Save Preferences
                </button>
                <a href="index.php" class="btn-secondary">
                    Cancel
                </a>
            </div>

        </form>
    </div>

</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
