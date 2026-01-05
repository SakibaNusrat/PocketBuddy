<?php
session_start();

// Initializing variables
$first_name = "";
$last_name = "";
$email = "";
$password_1 = "";
$password_2 = "";
$contact_number = "";
$errors = array();


// Connect to the database
$db = mysqli_connect('localhost', 'root', '', 'pocketbuddy');
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}




// REGISTER USER
if (isset($_POST['reg_user'])) {
    $first_name = mysqli_real_escape_string($db, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($db, $_POST['last_name']);
    $email = mysqli_real_escape_string($db, $_POST['email']);
    $password_1 = mysqli_real_escape_string($db, $_POST['password_1']);
    $password_2 = mysqli_real_escape_string($db, $_POST['password_2']);
    $contact_number = mysqli_real_escape_string($db, $_POST['contact_number']);

    // Form validation
    if (empty($first_name)) { array_push($errors, "First name is required"); }
    if (empty($last_name)) { array_push($errors, "Last name is required"); }
    if (empty($email)) { array_push($errors, "Email is required"); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { array_push($errors, "Invalid email format"); }
    if (empty($password_1)) { array_push($errors, "Password is required"); }
    if ($password_1 != $password_2) { array_push($errors, "Passwords do not match"); }
    if (!empty($contact_number) && !preg_match('/^\d{11}$/', $contact_number)) {
        array_push($errors, "Contact number must be exactly 11 digits");
    }

    // Check if email already exists
    $user_check_query = "SELECT * FROM USERS WHERE email=? LIMIT 1";
    $stmt = $db->prepare($user_check_query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if ($user) {
        array_push($errors, "Email already exists");
    }
    $stmt->close();

    // Register user if no errors
    if (count($errors) == 0) {
        $password = password_hash($password_1, PASSWORD_DEFAULT);

        $query = empty($contact_number) 
            ? "INSERT INTO USERS (first_name, last_name, email, password) VALUES (?, ?, ?, ?)" 
            : "INSERT INTO USERS (first_name, last_name, email, password, contact_number) VALUES (?, ?, ?, ?, ?)";

        $stmt = $db->prepare($query);
        if (empty($contact_number)) {
            $stmt->bind_param("ssss", $first_name, $last_name, $email, $password);
        } else {
            $stmt->bind_param("sssss", $first_name, $last_name, $email, $password, $contact_number);
        }

        if ($stmt->execute()) {
            $_SESSION['email'] = $email;
            $_SESSION['success'] = "You are now logged in";
            header('Location: index.php');
            exit();
        } else {
            array_push($errors, "Registration failed. Please try again.");
        }
        $stmt->close();
    }
}
?>
















  

