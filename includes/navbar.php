<?php
$user_id = get_user_id();
$unread_notifications_count = 0;

if ($user_id && isset($conn)) {
    sync_user_notifications($conn, $user_id);
    $unread_notifications_count = get_unread_notifications_count($conn, $user_id);
}

$current_uri = $_SERVER["REQUEST_URI"] ?? "";
?>
<nav class="navbar" data-csrf-token="<?= htmlspecialchars(csrf_token()); ?>">

    <div class="navbar-container">

        <a href="/daily-expense-tracker-v2/dashboard.php" class="brand">
            Daily Expense Tracker
        </a>

        <!-- Mobile Quick Actions -->
        <div class="navbar-actions-mobile">
            <button
                type="button"
                class="theme-toggle-btn mobile-theme-btn"
                aria-label="Toggle dark mode"
                title="Toggle light/dark theme">
                <svg class="theme-icon-sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <svg class="theme-icon-moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>

            <a href="/daily-expense-tracker-v2/notifications/index.php" class="nav-bell-link mobile-bell <?= strpos($current_uri, '/notifications/') !== false ? 'active' : ''; ?>" aria-label="Notifications (<?= $unread_notifications_count; ?> unread)">
                <svg class="bell-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if ($unread_notifications_count > 0): ?>
                    <span class="notification-badge"><?= $unread_notifications_count > 99 ? '99+' : $unread_notifications_count; ?></span>
                <?php endif; ?>
            </a>

            <button
                type="button"
                class="menu-toggle"
                id="menu-toggle"
                aria-label="Open navigation"
                aria-expanded="false">
                ☰
            </button>
        </div>

        <!-- Main Navigation Links -->
        <div class="nav-links" id="nav-links">

            <a href="/daily-expense-tracker-v2/dashboard.php" class="<?= strpos($current_uri, '/dashboard.php') !== false ? 'active' : ''; ?>">
                Dashboard
            </a>

            <a href="/daily-expense-tracker-v2/expenses/index.php" class="<?= strpos($current_uri, '/expenses/') !== false ? 'active' : ''; ?>">
                Expenses
            </a>

            <a href="/daily-expense-tracker-v2/upcoming/index.php" class="<?= strpos($current_uri, '/upcoming/') !== false ? 'active' : ''; ?>">
                Upcoming
            </a>

            <a href="/daily-expense-tracker-v2/recurring/index.php" class="<?= strpos($current_uri, '/recurring/') !== false ? 'active' : ''; ?>">
                Recurring
            </a>

            <a href="/daily-expense-tracker-v2/budget/index.php" class="<?= strpos($current_uri, '/budget/') !== false ? 'active' : ''; ?>">
                Budget
            </a>

            <a href="/daily-expense-tracker-v2/analytics/index.php" class="<?= strpos($current_uri, '/analytics/') !== false ? 'active' : ''; ?>">
                Analytics
            </a>

            <a href="/daily-expense-tracker-v2/profile/index.php" class="<?= strpos($current_uri, '/profile/') !== false ? 'active' : ''; ?>">
                Profile
            </a>

            <!-- Notification Bell Icon (Desktop) -->
            <a href="/daily-expense-tracker-v2/notifications/index.php" class="nav-bell-link desktop-bell <?= strpos($current_uri, '/notifications/') !== false ? 'active' : ''; ?>" aria-label="Notifications (<?= $unread_notifications_count; ?> unread)" title="Notification Center">
                <svg class="bell-icon" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if ($unread_notifications_count > 0): ?>
                    <span class="notification-badge" id="nav-unread-badge">
                        <?= $unread_notifications_count > 99 ? '99+' : $unread_notifications_count; ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Theme Toggle (Desktop) -->
            <button
                type="button"
                class="theme-toggle-btn desktop-theme-btn"
                aria-label="Toggle dark mode"
                title="Toggle light/dark theme">
                <svg class="theme-icon-sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <svg class="theme-icon-moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>

            <form method="POST" action="/daily-expense-tracker-v2/logout.php" class="logout-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrf_token()); ?>">
                <button type="submit" class="logout-btn" title="Sign out of your account" aria-label="Logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Logout</span>
                </button>
            </form>
        </div>

    </div>

</nav>
