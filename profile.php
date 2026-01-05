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

// Fetch user data based on email stored in session
$userEmail = $_SESSION['email'];
$userQuery = "SELECT * FROM USERS WHERE Email = ?";
$stmt = $db->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Update profile logic
if (isset($_POST['update_profile'])) {
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
   
    $contact_number = $_POST['contact_number'];
    $address = $_POST['address'];
    $dob = $_POST['dob'];

    $updateQuery = "UPDATE USERS SET first_name = ?, last_name = ?, Phone = ?, Address = ?, DateOfBirth = ? WHERE Email = ?";
    $stmt = $db->prepare($updateQuery);
    $stmt->bind_param("sssss", $name, $phone, $address, $dob, $userEmail);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to update profile.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
    .custom-btn {
        background-color: #641e16; /* Green color */
        color: white;
        border: none;
        padding: 10px 20px;
        font-size: 16px;
        border-radius: 5px;
    }
    .custom-btn:hover {
        background-color:rgb(223, 66, 49); /* Darker green on hover */
    }
</style>

</head>
<body>
<div class="container mt-5">
    <h2>User Profile</h2>

    <!-- Display success or error messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form method="POST">
          <div class="mb-3">
            <label for="first_Name" class="form-label">First Name</label>
            <input type="text" class="form-control" id="first_Name" name="first_Name" value="<?= htmlspecialchars($user['first_Name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="last_name" class="form-label">Last Name</label>
            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($user['Email']) ?>" readonly>
        </div>
        <div class="mb-3">
            <label for="contact_number" class="form-label">contact_number</label>
            <input type="text" class="form-control" id="contact_number" name="contact_numer" value="<?= htmlspecialchars($user['contact_number']) ?>">
        </div>
        
       <button type="submit" name="update_profile" class="custom-btn">Update Profile</button>

    </form>
</div>

<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>



