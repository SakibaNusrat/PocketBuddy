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

// Add Budget Logic
if (isset($_POST['add_budget'])) {
    $month = $_POST['month'];
    $budgetyear = $_POST['budgetyear'];
    $budgetname = $_POST['budgetname'];
    $amount = $_POST['amount'];
    $is_active = $_POST['is_active'];

    $query = "INSERT INTO Budget (userID, Month, BudgetYear, BudgetName, Amount, IsActive) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("issdsi", $userID, $month, $budgetyear, $budgetname, $amount, $is_active);
    if ($stmt->execute()) {
        $success_message = "Budget added successfully!";
        echo "<script>showToast('$success_message', 'success');</script>";
    } else {
        $error_message = "Error adding Budget: " . $stmt->error;
        echo "<script>showToast('$error_message', 'error');</script>";
    }
    
    $stmt->close();
}

// Fetch all budgets for the current user
$budgetQuery = "SELECT * FROM Budget WHERE userID = ?";
$stmt = $db->prepare($budgetQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$budgets = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Budget</title>
    <style>
        * {
            margin: 0px;
            padding: 0px;
        }
        body {
            font-size: 120%;
            background: #F8F8FF;
        }

        .container {
            width: 50%;
            margin: 50px auto;
        }

        .header {
            color: white;
            background: #641e16;
            text-align: center;
            border: 1px solid #641e16;
            border-bottom: none;
            border-radius: 10px 10px 0px 0px;
            padding: 20px;
            position: relative;
        }

        .header h3 {
            display: inline;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 18px;
            color: white;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: color 0.3s, transform 0.3s;
        }

        .close-btn:hover {
            color: rgb(255, 249, 250);
            transform: scale(1.2);
        }

        .form-container {
            padding: 20px;
            background: white;
            border: 1px solid #641e16;
            border-radius: 0px 0px 10px 10px;
        }

        .input-group {
            margin: 10px 0px 10px 0px;
        }

        .input-group label {
            display: block;
            text-align: left;
            margin: 3px;
        }

        .input-group input,
        .input-group select {
            height: 30px;
            width: 93%;
            padding: 5px 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid gray;
        }

        .btn {
            padding: 10px;
            font-size: 15px;
            color: white;
            background: #641e16;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: rgb(239, 24, 52);
            color: white;
        }

        a {
            color: #641e16;
            text-decoration: none;
        }

        a:hover {
            color:#641e16;
            text-decoration: underline;
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
    background-color:rgb(28, 193, 66); /* Green for success */
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
    <script>
        function closePage() {
            window.location.href = 'index.php'; // Replace with your desired URL
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h3>Add Budget</h3>
            <button class="close-btn" onclick="closePage()">×</button> <!-- Cross button -->
        </div>

        <div class="form-container">
            <form method="POST">
                <div class="input-group">
                    <label for="month">Month</label>
                    <select id="month" name="month" required>
                        <option value="">Select Month</option>
                        <option value="January">January</option>
                        <option value="February">February</option>
                        <option value="March">March</option>
                        <option value="April">April</option>
                        <option value="May">May</option>
                        <option value="June">June</option>
                        <option value="July">July</option>
                        <option value="August">August</option>
                        <option value="September">September</option>
                        <option value="October">October</option>
                        <option value="November">November</option>
                        <option value="December">December</option>
                    </select>
                </div>

                <div class="input-group">
                    <label for="budgetyear">Budget Year</label>
                    <input type="number" id="budgetyear" name="budgetyear" required placeholder="e.g., 2025">
                </div>

                <div class="input-group">
                    <label for="budgetname">Budget Name</label>
                    <input type="text" id="budgetname" name="budgetname" required placeholder="e.g., Personal Budget">
                </div>

                <div class="input-group">
                    <label for="amount">Amount</label>
                    <input type="number" step="0.01" id="amount" name="amount" required placeholder="Enter the amount">
                </div>

                <div class="input-group">
                    <label for="is_active">Status</label>
                    <select id="is_active" name="is_active" required>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

               

                <button type="submit" name="add_budget" class="btn">Add Budget</button>
            </form>
        </div>
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



