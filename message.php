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
$userQuery = "SELECT userID FROM users WHERE Email = ?";
$stmt = $db->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$userID = $user['userID'];

// Fetch all users for the dropdown (to send messages)
$usersQuery = "SELECT userID, CONCAT(first_Name, ' ', last_Name) AS FullName FROM users WHERE userID != ?";
$stmt = $db->prepare($usersQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$users = $stmt->get_result();

// Sending a message
if (isset($_POST['send_message'])) {
    $receiverID = $_POST['receiver_id'];
    $messageText = $_POST['message_text'];

    $query = "INSERT INTO Messages (userID, receiverID, messageText) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("iis", $userID, $receiverID, $messageText);
    if ($stmt->execute()) {
        $success_message = "Message sent successfully!";
        echo "<script>showToast('$success_message', 'success');</script>";
    } else {
        $error_message = "Error sending message: " . $stmt->error;
        echo "<script>showToast('$error_message', 'error');</script>";
    }
    $stmt->close();
}

// Fetch messages sent to or from the current user
$messagesQuery = "
    SELECT 
        m.messageID, 
        m.messageText, 
        m.timestamp, 
        u1.first_Name AS sender, 
        u2.first_Name AS receiver 
    FROM 
        Messages m
    JOIN users u1 ON m.userID = u1.userID
    JOIN users u2 ON m.receiverID = u2.userID
    WHERE 
        m.userID = ? OR m.receiverID = ?
    ORDER BY 
        m.timestamp DESC";
$stmt = $db->prepare($messagesQuery);
$stmt->bind_param("ii", $userID, $userID);
$stmt->execute();
$messages = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>

    <link rel="stylesheet" href="your_custom_css_file.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <style>
        body {
            background-color: #f4f6f9;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 960px;
            margin-top: 30px;
        }

        .btn-custom {
            background-color: #641e16;
            color: #fff;
            border-radius: 5px;
        }

        .btn-custom:hover {
            background-color: #641e16;
        }

        .message-box {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .message-box h3 {
            font-size: 1.5em;
            color: #333;
        }

        .message-box .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.5em;
            background: transparent;
            border: none;
            color: #333;
            cursor: pointer;
        }

        .message-box .close-btn:hover {
            color: #007bff;
        }

        .form-group label {
            font-weight: bold;
            color: #333;
        }

        .table-striped tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }

        .table-striped tbody tr:hover {
            background-color: #e0f7fa;
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
            background-color: rgb(135, 235, 159);
        }

        .toast.error {
            background-color: rgb(244, 99, 113);
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
    
    <script>
        function closePage() {
            window.location.href = 'index.php'; // Replace with your desired URL
        }
    </script>
</head>
<body>

<div class="container">
    <div class="message-box">
        <button class="close-btn" onclick="closePage()">×</button>
        <h2 class="text-center">Messaging System</h2>

        <div class="row mt-4">
            <div class="col-md-12">
                <h3>Send a Message</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="receiver_id">Send To</label>
                        <select name="receiver_id" id="receiver_id" class="form-control" required>
                            <option value="">Select a User</option>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <option value="<?= $user['userID'] ?>"><?= $user['FullName'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="message_text">Message</label>
                        <textarea name="message_text" id="message_text" class="form-control" rows="4" required></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn btn-custom">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast Message -->
<div id="toast" class="toast">
    <span id="toast-message"></span>
    <button class="close-btn" onclick="closeToast()">×</button>
</div>

<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
    function closeMessageBox() {
        const messageBox = document.querySelector('.message-box');
        messageBox.style.display = 'none';
    }

    function showToast(message, type) {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');
        toastMessage.textContent = message;
        toast.classList.add(type, 'show');

        setTimeout(function() {
            toast.classList.remove('show', type);
        }, 3000); // Hide toast after 3 seconds
    }

    function closeToast() {
        const toast = document.getElementById('toast');
        toast.classList.remove('show');
    }
</script>
</body>
</html>







