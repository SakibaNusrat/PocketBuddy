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
$userQuery = "SELECT userID FROM USERS WHERE Email = ?";
$stmt = $db->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check if user exists
if ($user) {
    $userID = $user['userID'];
} else {
    die("User not found.");
}
// Fetch donation Data
$budgetQuery = "SELECT BudgetID, Month, budgetyear FROM budget WHERE userID = ?";
$stmt = $db->prepare($budgetQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$budgetResult = $stmt->get_result();

$budgetData = [];
if ($budgetResult->num_rows > 0) {
    while ($row = $budgetResult->fetch_assoc()) {
        $budgetData[] = $row;
    }
}
// Add Donation Logic
if (isset($_POST['add_donation'])) {
    $organizationName = $_POST['organization_name'];  // Changed from category to organization_name
    $amount = $_POST['donation_amount'];
    $donationDate = $_POST['donation_date'];
    $budgetID = $_POST['Month'];

    $query = "INSERT INTO Donation (userID, OrganizationName, Amount, Date, budgetID) 
              VALUES (?, ?, ?, ?, ?)";
    // Bind parameters: i = integer, s = string, d = double
    $stmt = $db->prepare($query);
    $stmt->bind_param("isdsd", $userID, $organizationName, $amount, $donationDate, $budgetID);
    
    if ($stmt->execute()) {
        $success_message = "Donation added successfully!";
        echo "<script>showToast('$success_message', 'success');</script>";
    } else {
        $error_message = "Error adding donation: " . $stmt->error;
        echo "<script>showToast('$error_message', 'error');</script>";
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Donation</title>
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
            color: rgb(221, 218, 218);
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
            color: #641e16;
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
    <script>
        function closePage() {
            window.location.href = 'index.php'; // Replace with your desired URL
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h3>Add Donation</h3>
            <button class="close-btn" onclick="closePage()">×</button> <!-- Cross button -->
        </div>

        <div class="form-container">
            
            <form method="POST">
                <div class="input-group">
                    <label for="organization_name">Organization Name</label>
                    <input type="text" class="form-control" id="organization_name" name="organization_name" required>
                </div>
                <div class="input-group">
                    <label for="donation_amount">Amount</label>
                    <input type="number" step="0.01" class="form-control" id="donation_amount" name="donation_amount" required>
                </div>
                <div class="input-group">
                    <label for="donation_date">Donation Date</label>
                    <input type="date" class="form-control" id="donation_date" name="donation_date" required>
                </div>
                <div class="input-group">
                    <label for="budget_month">Budget Month</label>
                    <select id="budget_month" name="Month" required>
                        <option value="">Select Budget Month</option>
                        <?php
                        if (!empty($budgetData)) {
                            foreach ($budgetData as $budget) {
                                echo '<option value="' . htmlspecialchars($budget['BudgetID'], ENT_QUOTES, 'UTF-8') . '">'
                                    . htmlspecialchars($budget['Month'], ENT_QUOTES, 'UTF-8') . ' '
                                    . htmlspecialchars($budget['budgetyear'], ENT_QUOTES, 'UTF-8') . 
                                    '</option>';
                            }
                        } else {
                            echo '<option value="">No budgets found</option>';
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" name="add_donation" class="btn">Add Donation</button>
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


