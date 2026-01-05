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
$userQuery = "SELECT userID FROM USERS WHERE Email = ?";
$stmt = $db->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$userID = $user['userID'];

// Check if user exists
if ($user) {
    $userID = $user['userID'];
} else {
    die("User not found. Please log in again.");
}

// Handle form submission for adding a bank account
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bank_account') {
    $bankName = trim($_POST['bank_name']);
    $accountNumber = trim($_POST['account_number']);
    $ifscCode = trim($_POST['ifsc_code']);
    $accountType = trim($_POST['account_type']);

    // Validate required fields
    if (empty($bankName) || empty($accountNumber) || empty($ifscCode) || empty($accountType)) {
        $message = "All fields are required.";
    } else {
        // Prepare and execute the insert query
        $query = "INSERT INTO BankAccounts (userID, bankName, accountNumber, ifscCode, accountType) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->bind_param("issss", $userID, $bankName, $accountNumber, $ifscCode, $accountType);

        if ($stmt->execute()) {
            $success_message = "Subscription added successfully!";
            echo "<script>showToast('$success_message', 'success');</script>";
        } else {
            $error_message = "Error adding Subscription: " . $stmt->error;
            echo "<script>showToast('$error_message', 'error');</script>";
        }
        $stmt->close();

        
    }
}

// Fetch all bank accounts for the user
$accountQuery = "SELECT * FROM BankAccounts WHERE userID = ?";
$stmt = $db->prepare($accountQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$accountResult = $stmt->get_result();

$accounts = [];
while ($account = $accountResult->fetch_assoc()) {
    $accounts[] = $account; // Store each account's details
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Account</title>
    <style>
      * {
            margin: 0px;
            padding: 0px;
        }

       
        body {

              

            background: #F8F8FF;

}        } 
        /* Center align the bank account text */
.account-box {
    text-align: center; /* Center the text horizontally */
    padding: 15px; /* Add some spacing inside the box */
    margin: 10px auto; /* Center the box within the container */
    border: 1px solid #ddd; /* Optional: Add a border for clarity */
    border-radius: 6px; /* Rounded corners for better appearance */
    background-color:rgb(245, 216, 216); /* Light background for contrast */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
}

/* Bank account details text */
.account-box p {
    margin: 8px 0; /* Add space between lines of text */
    font-size: 1.1em; /* Increase text size slightly */
    color: #333; /* Darker text color for better readability */
    font-weight: 500; /* Slightly bold text for emphasis */
}

    /* General container for the page */
.container {
    width: 90%;
    max-width: 700px;
    margin: 50px auto;
    padding: 25px;
   
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(73, 73, 73, 0.1);
}

/* Form Group styling */
.form-group {
    margin-bottom: 20px;
}

/* Label styling */
label {
    font-weight: bold;
    font-size: 1.1em;
    color: #333;
    margin-bottom: 8px;
    display: block;
}

/* Input field styling */
.form-input {
    width: 100%;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #ddd;
    background-color: #f9f9f9;
    font-size: 1em;
    box-sizing: border-box;
    transition: border-color 0.3s ease;
}

.form-input:focus {
    border-color: #4CAF50;
    outline: none;
    background-color: #ffffff;
}

/* Submit button styling */
.submit-btn {
    padding: 12px 30px;
    background-color: #641e16;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1.1em;
    transition: background-color 0.3s ease;
}

.submit-btn:hover {
    background-color: #45a049;
}

/* Add spacing between elements */
form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Error message styling */
.error-message {
    color: red;
    font-size: 0.9em;
    margin-bottom: 15px;
}


        /* Success or error message */
        .message {
            color: green;
            font-size: 18px;
            margin-bottom: 20px;
        }

        /* Box to display saved account info */
        .account-box {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color:rgb(239, 209, 209);
            margin-bottom: 20px;
        }

        .account-box h3 {
            margin-top: 0;
            font-size: 20px;
        }

        .account-box p {
            margin: 5px 0;
        }

        /* Close Button Styles */
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px; /* Moves the button to the top-right corner */
            background: #641e16;
            color: white;
            font-size: 18px;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .close-btn:hover {
            background-color: rgb(164, 75, 66);
        }
        .toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    min-width: 250px;
    max-width: 300px;
    padding: 15px 20px;
    border-radius: 5px;
    font-size: 16px;
    color: #fff;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(100%);
    transition: all 0.5s ease;
}

.toast.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.toast.success {
    background-color:rgb(135, 235, 159); /* Green for success */
}

.toast.error {
    background-color:rgb(244, 99, 113); /* Red for error */
}

.toast .close-btn {
    margin-left: auto;
    cursor: pointer;
    font-size: 18px;
    font-weight: bold;
    color: #fff;
    background: none;
    border: none;
}
.toast .close-btn:hover {
    color: #ccc;
}
    </style>
</head>
<body>

    <!-- Cross button to go back to index.php -->
    <button class="close-btn" onclick="window.location.href='index.php'">&times;</button>

    <div class="container">
        <h2>Bank Accounts</h2>

        <?php if (!empty($message)) { echo "<p class='message'>$message</p>"; } ?>

        <?php if (count($accounts) > 0): ?>
            <h3>Saved Bank Accounts</h3>
            <?php foreach ($accounts as $account): ?>
                <div class="account-box">
                    <p><strong>Bank Name:</strong> <?php echo htmlspecialchars($account['bankName']); ?></p>
                    <p><strong>Account Number:</strong> <?php echo htmlspecialchars($account['accountNumber']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No bank accounts found.</p>
        <?php endif; ?>
       
        <h3>Add a New Bank Account</h3>
        <form method="POST" class="form-style">
            <input type="hidden" name="action" value="add_bank_account">

            <div class="form-group">
                <label for="bank_name">Bank Name:</label>
                <input type="text" id="bank_name" name="bank_name" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="account_number">Account Number:</label>
                <input type="text" id="account_number" name="account_number" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="ifsc_code">IFSC Code:</label>
                <input type="text" id="ifsc_code" name="ifsc_code" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="account_type">Account Type:</label>
                <input type="text" id="account_type" name="account_type" class="form-input" required>
            </div>

            <button type="submit" class="submit-btn">Add Account</button>
        </form>
    </div>
       
<!-- JavaScript for Toast Notification -->
<script>
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast ${type} show`;
            toast.innerHTML = `
                ${message}
                <button class="close-btn" onclick="this.parentElement.remove()">×</button>
            `;
            document.body.appendChild(toast);

            // Auto-remove the toast after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 500); // Allow the animation to finish
            }, 3000);
        }

        // Example: You can trigger the toast message with a success or error
        <?php if (isset($success_message)): ?>
            showToast('<?php echo $success_message; ?>', 'success');
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            showToast('<?php echo $error_message; ?>', 'error');
        <?php endif; ?>
    </script>
</body>
</html>




