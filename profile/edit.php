<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_login();

$page_title = "Edit Profile";
$page_css = ["profile", "forms"];

$user_id = get_user_id();

$errors = [];
$success = false;

/* Get Current User */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT name, email
    FROM users
    WHERE id = ?
    LIMIT 1
    "
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$user) {
    die("User not found.");
}

$name = $user["name"];
$email = $user["email"];

/* Update Profile */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf_token)) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");

    if ($name === "") {
        $errors[] = "Name is required.";
    }

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            "
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
            LIMIT 1
            "
        );

        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $existing_user = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if ($existing_user) {
            $errors[] = "This email address is already in use.";
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            "
            UPDATE users
            SET name = ?, email = ?
            WHERE id = ?
            "
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssi",
            $name,
            $email,
            $user_id
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION["user_name"] = $name;

        $success = true;
    }
    }
}

require_once __DIR__ . "/../includes/header.php";
?>

<div class="profile-page">

    <div class="page-header">
        <div>
            <h1>Edit Profile</h1>
            <p>Update your account information.</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>

        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="alert alert-success">
            Profile updated successfully.
        </div>

    <?php endif; ?>

    <div class="profile-card">

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token()); ?>">

            <div class="form-group">
                <label for="name">Name</label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= htmlspecialchars($name); ?>"
                    required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($email); ?>"
                    required>
            </div>

            <div class="profile-actions">

                <button type="submit" class="btn-primary">
                    Save Changes
                </button>

                <a href="index.php" class="btn-secondary">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
