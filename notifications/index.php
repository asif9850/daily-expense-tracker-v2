<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

$user_id = get_user_id();

// Synchronize notifications for latest data
sync_user_notifications($conn, $user_id, true);

$filter = $_GET["filter"] ?? "all";
if (!in_array($filter, ["all", "unread", "upcoming", "recurring", "budget"], true)) {
    $filter = "all";
}

$notifications = get_user_notifications($conn, $user_id, $filter, 100);
$unread_total = get_unread_notifications_count($conn, $user_id);

$page_title = "Notification Center";
$page_css = ["notifications"];

require_once __DIR__ . "/../includes/header.php";
$current_url = htmlspecialchars($_SERVER["REQUEST_URI"]);
?>

<div class="notifications-page">

    <div class="notifications-header">
        <div>
            <h1>Notification Center</h1>
            <p>Track your upcoming bills, recurring expenses, and budget alerts.</p>
        </div>

        <?php if (!empty($notifications) || $unread_total > 0): ?>
            <div class="notifications-top-actions">
                <?php if ($unread_total > 0): ?>
                    <form method="POST" action="/daily-expense-tracker-v2/notifications/read.php" class="action-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="action" value="mark_all">
                        <input type="hidden" name="return_url" value="<?= $current_url; ?>">
                        <button type="submit" class="notif-btn notif-btn-primary" title="Mark all notifications as read">
                            ✓ Mark All as Read
                        </button>
                    </form>
                <?php endif; ?>

                <form method="POST" action="/daily-expense-tracker-v2/notifications/dismiss.php" class="action-form" onsubmit="return confirm('Dismiss all current notifications?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="action" value="dismiss_all">
                    <input type="hidden" name="return_url" value="<?= $current_url; ?>">
                    <button type="submit" class="notif-btn notif-btn-danger" title="Clear all notifications from view">
                        ✕ Dismiss All
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation Filter Tabs -->
    <div class="notifications-tabs">
        <a href="?filter=all" class="tab-btn <?= $filter === 'all' ? 'active' : ''; ?>">
            All Notifications
        </a>
        <a href="?filter=unread" class="tab-btn <?= $filter === 'unread' ? 'active' : ''; ?>">
            Unread
            <?php if ($unread_total > 0): ?>
                <span class="badge badge-info" style="font-size: 11px; padding: 1px 6px;"><?= $unread_total; ?></span>
            <?php endif; ?>
        </a>
        <a href="?filter=upcoming" class="tab-btn <?= $filter === 'upcoming' ? 'active' : ''; ?>">
            Upcoming Reminders
        </a>
        <a href="?filter=recurring" class="tab-btn <?= $filter === 'recurring' ? 'active' : ''; ?>">
            Recurring Reminders
        </a>
        <a href="?filter=budget" class="tab-btn <?= $filter === 'budget' ? 'active' : ''; ?>">
            Budget Alerts
        </a>
    </div>

    <!-- Notification Cards List -->
    <?php if (empty($notifications)): ?>
        <div class="empty-notifications-card">
            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                <line x1="2" y1="2" x2="22" y2="22"></line>
            </svg>
            <h3>No notifications here</h3>
            <p>
                <?= $filter === 'unread'
                    ? 'Great news! You have no unread reminders or budget alerts.'
                    : 'There are no active notifications to show right now.'; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notif): ?>
                <?php
                $type = $notif["type"];
                $is_read = (bool) $notif["is_read"];

                // Type class and badge determination
                $card_type_class = "type-" . $type;
                $badge_class = "badge-" . $type;
                $type_label = ucfirst($type);
                $module_url = "";
                $module_btn_label = "";

                if ($type === "upcoming") {
                    $module_url = "/daily-expense-tracker-v2/upcoming/index.php";
                    $module_btn_label = "View Upcoming";
                    $type_label = "Upcoming";
                } elseif ($type === "recurring") {
                    $module_url = "/daily-expense-tracker-v2/recurring/index.php";
                    $module_btn_label = "View Recurring";
                    $type_label = "Recurring";
                } elseif ($type === "budget") {
                    $module_url = "/daily-expense-tracker-v2/budget/index.php";
                    $module_btn_label = "View Budget";
                    if (str_contains(strtolower($notif["title"]), "warning")) {
                        $card_type_class = "type-budget-warning";
                        $badge_class = "badge-budget-warning";
                        $type_label = "Budget Warning";
                    } else {
                        $card_type_class = "type-budget-danger";
                        $badge_class = "badge-budget-danger";
                        $type_label = "Budget Limit";
                    }
                }
                $card = format_notification_card($notif);
                ?>
                <div class="notification-card <?= $card_type_class; ?> <?= !$is_read ? 'is-unread' : ''; ?>">
                    <div class="notification-top">
                        <div class="notification-meta">
                            <span class="notification-type-badge <?= $badge_class; ?>">
                                <?= htmlspecialchars($type_label); ?>
                            </span>
                            <?php if (!empty($card["status_label"])): ?>
                                <span class="notif-status-pill <?= $card["status_class"]; ?>">
                                    <?= $card["status_icon"]; ?> <?= htmlspecialchars($card["status_label"]); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!$is_read): ?>
                                <span class="badge badge-info" style="font-size: 11px;">Unread</span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-time">
                            <?= date("d M Y, h:i A", strtotime($notif["created_at"])); ?>
                        </div>
                    </div>

                    <div class="notification-content">
                        <div class="notif-title-row">
                            <h3 class="notif-item-title"><?= htmlspecialchars($card["item_title"]); ?></h3>
                            <?php if (!empty($card["amount_badge"])): ?>
                                <span class="notif-amount-pill"><?= htmlspecialchars($card["amount_badge"]); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="notif-desc"><?= $card["subtitle"]; ?></div>
                    </div>

                    <div class="notification-actions">
                        <?php if (!empty($module_url)): ?>
                            <a href="<?= htmlspecialchars($module_url); ?>" class="notif-btn notif-btn-primary">
                                <?= htmlspecialchars($module_btn_label); ?> &rarr;
                            </a>
                        <?php endif; ?>

                        <!-- Mark as Read / Unread -->
                        <form method="POST" action="/daily-expense-tracker-v2/notifications/read.php" class="action-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="notification_id" value="<?= (int)$notif["id"]; ?>">
                            <input type="hidden" name="status" value="<?= $is_read ? 0 : 1; ?>">
                            <input type="hidden" name="return_url" value="<?= $current_url; ?>">
                            <button type="submit" class="notif-btn" title="<?= $is_read ? 'Mark as Unread' : 'Mark as Read'; ?>">
                                <?= $is_read ? 'Mark as Unread' : 'Mark as Read'; ?>
                            </button>
                        </form>

                        <!-- Dismiss Notification -->
                        <form method="POST" action="/daily-expense-tracker-v2/notifications/dismiss.php" class="action-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="notification_id" value="<?= (int)$notif["id"]; ?>">
                            <input type="hidden" name="return_url" value="<?= $current_url; ?>">
                            <button type="submit" class="notif-btn notif-btn-danger" title="Dismiss notification">
                                Dismiss
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
