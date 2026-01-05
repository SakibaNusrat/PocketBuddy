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
if ($result->num_rows == 0) {
    die("User data not found. Please ensure the email is correct and the user exists in the database.");
}
$user = $result->fetch_assoc();
$userID = $user['userID'];

// Add Pocket Money Logic
if (isset($_POST['allocate_pocket_money'])) {
    $childName = $_POST['child_name'];
    $amount = $_POST['amount'];
    $frequency = $_POST['frequency'];

    // Fetch current budget
    $budgetQuery = "SELECT Amount FROM Budget WHERE userID = ?";
    $stmt = $db->prepare($budgetQuery);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    $budget = $result->fetch_assoc();
    $currentBudget = $budget['Amount'];

    // Deduct the pocket money from the current budget
    $newBudget = $currentBudget - $amount;
    $updateBudgetQuery = "UPDATE Budget SET Amount = ? WHERE userID = ?";
    $stmt = $db->prepare($updateBudgetQuery);
    $stmt->bind_param("di", $newBudget, $userID);
    $stmt->execute();

    // Insert pocket money record
    $pocketMoneyQuery = "INSERT INTO PocketMoney (userID, ChildName, Amount, Frequency) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($pocketMoneyQuery);
    $stmt->bind_param("isds", $userID, $childName, $amount, $frequency);
    $stmt->execute();
    $stmt->close();
}

// Fetch all pocket money allocations for the current user
$pocketMoneyQuery = "SELECT * FROM PocketMoney WHERE userID = ?";
$stmt = $db->prepare($pocketMoneyQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$pocketMoney = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Allocate Pocket Money</title>
    <style>
        /* Global Styles */
        * {
            margin: 0px;
            padding: 0px;
        }

        body {
            font-size: 120%;
            background: linear-gradient(135deg, #F8F8FF 25%, #f2f2f2 75%);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        /* Container Styles */
        .container {
            max-width: 450px;
            width: 100%;
            padding: 40px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            min-height: 350px;
            margin: 20px;
        }

        /* Header Styles */
        .header {
            color: white;
            background: #641e16;
            text-align: center;
            border: 1px solid #641e16;
            border-radius: 10px 10px 0px 0px;
            padding: 20px;
        }

        .header h3 {
            display: inline;
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
            background-color: rgb(164, 75, 66);
            color: white;
        }

        /* Table Styles */
        .table-container {
            margin-top: 20px;
            text-align: left;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #641e16;
            color: white;
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
    </style>
    <script>
        function closePage() {
            window.location.href = 'index.php'; // Replace with your desired URL
        }
    </script>
</head>
<body>
    <div class="container">
       <!-- Close Button -->
       <button class="close-btn" onclick="closePage()">×</button>
        <div class="header">
            <h3>Allocate Pocket Money</h3>
            
        </div>

        <div class="form-container">
        
          
   
            <form method="POST">
                <div class="input-group">
                    <label for="child_name">Child Name</label>
                    <input type="text" id="child_name" name="child_name" required placeholder="Enter child's name">
                </div>

                <div class="input-group">
                    <label for="amount">Amount</label>
                    <input type="number" step="0.01" id="amount" name="amount" required placeholder="Enter the amount">
                </div>

                <div class="input-group">
                    <label for="frequency">Frequency</label>
                    <select id="frequency" name="frequency" required>
                        <option value="">Select Frequency</option>
                        <option value="Daily">Daily</option>
                        <option value="Weekly">Weekly</option>
                        <option value="Monthly">Monthly</option>
                    </select>
                </div>

                <button type="submit" name="allocate_pocket_money" class="btn">Allocate Pocket Money</button>
            </form>
        </div>

        <div class="table-container">
            <h3>Pocket Money Allocations</h3>
            <table>
                <tr>
                    <th>Child Name</th>
                    <th>Amount</th>
                    <th>Frequency</th>
                </tr>
                <?php while ($row = $pocketMoney->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['ChildName']; ?></td>
                        <td><?php echo $row['Amount']; ?></td>
                        <td><?php echo $row['Frequency']; ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
