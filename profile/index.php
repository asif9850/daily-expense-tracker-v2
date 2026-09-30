<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

require_login();

$page_title = "Profile";
$page_css = ["profile", "forms"];

$user_id = get_user_id();

$stmt = mysqli_prepare(
    $conn,
    "SELECT name, email, created_at
     FROM users
     WHERE id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$prefs = get_user_preferences($conn, $user_id);

require_once __DIR__ . "/../includes/header.php";
?>

<div class="profile-page">

    <div class="page-header" style="margin-bottom: 20px;">
        <div>
            <h1>Profile</h1>
            <p>Manage your account information and application preferences.</p>
        </div>
    </div>

    <div class="profile-card">

        <div class="profile-row">
            <span>Name</span>
            <strong><?= htmlspecialchars($user["name"]); ?></strong>
        </div>

        <div class="profile-row">
            <span>Email</span>
            <strong><?= htmlspecialchars($user["email"]); ?></strong>
        </div>

        <div class="profile-row">
            <span>Account Created</span>
            <strong>
                <?= date("d M Y", strtotime($user["created_at"])); ?>
            </strong>
        </div>

        <div class="profile-row">
            <span>Active Theme</span>
            <strong>
                <?= ucfirst(htmlspecialchars($prefs["theme"] ?? "light")); ?> Mode
            </strong>
        </div>

        <div class="profile-row">
            <span>Reminder Timing</span>
            <strong>
                <?= (int)($prefs["reminder_days"] ?? 3); ?> days before due date
            </strong>
        </div>

        <div class="profile-actions">
            <a href="edit.php" class="btn-primary">
                Edit Profile
            </a>

            <a href="change_password.php" class="btn-secondary">
                Change Password
            </a>

            <a href="preferences.php" class="btn-secondary" style="border-color: var(--color-primary-subtle); color: var(--color-primary);">
                ⚙️ Preferences & Alerts
            </a>
        </div>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
