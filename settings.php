<?php
session_start();

// Redirect to login if the user is not logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Connect to the database
$db = mysqli_connect('localhost', 'root', '', 'pocketbuddy');
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch user data
$userEmail = $_SESSION['email'];
$query = "SELECT * FROM USERS WHERE Email = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

// Update password
if (isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($newPassword === $confirmPassword) {
        if (password_verify($currentPassword, $userData['Password'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateQuery = "UPDATE USERS SET Password = ? WHERE Email = ?";
            $stmt = $db->prepare($updateQuery);
            $stmt->bind_param("ss", $hashedPassword, $userEmail);
            $stmt->execute();
            $message = "Password updated successfully!";
        } else {
            $error = "Current password is incorrect.";
        }
    } else {
        $error = "New passwords do not match.";
    }
}

// Update other settings (e.g., username, notifications)
if (isset($_POST['update_settings'])) {
    $username = $_POST['username'];
    $notifications = isset($_POST['notifications']) ? 1 : 0;

    $updateQuery = "UPDATE USERS SET Username = ?, NotificationsEnabled = ? WHERE Email = ?";
    $stmt = $db->prepare($updateQuery);
    $stmt->bind_param("sis", $username, $notifications, $userEmail);
    $stmt->execute();
    $message = "Settings updated successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
    .custom-btn-password {
        background-color: #641e16; /* Green color */
        color: white;
        border: none;
        padding: 10px 20px;
        font-size: 16px;
        border-radius: 5px;
    }
    .custom-btn-password:hover {
        background-color:rgb(213, 64, 48); /* Darker green on hover */
    }
</style>

</head>
<body>
<div class="container mt-4">
    <h2>Settings</h2>

    <?php if (isset($message)): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Update Password Section -->
    <div class="card mb-4">
        <div class="card-header">Change Password</div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="update_password" class="custom-btn-password">Update Password</button>

            </form>
        </div>
    </div>
    
     <!-- Delete Data and Account Section -->
    <div class="card">
        <div class="card-header">Delete Your Data and Account</div>
        <div class="card-body">
            <form method="POST" onsubmit="return confirm('Are you sure you want to delete your account and all your data? This action cannot be undone.');">
                <p class="text-danger">Warning: Deleting your account will permanently remove all your data!</p>
                <button type="submit" name="delete_data" class="btn btn-danger mt-3">Delete Data and Account</button>
            </form>
        </div>
    </div>
    



    

<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>


