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

// Add Saving Challenge Logic
$challengeAdded = false;
if (isset($_POST['add_challenge'])) {
    $challengeName = $_POST['challenge_name'];
    $description = $_POST['description'];
    $duration = $_POST['duration'];
    $savingAmount = $_POST['saving_amount'];
    $status = 'Pending'; // Default status

    $query = "INSERT INTO SavingsChallenge (UserID, chllangeName, Description, Duration, savingAmount, Status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("issdis", $userID, $challengeName, $description, $duration, $savingAmount, $status);
    $stmt->execute();
    $stmt->close();

    $challengeAdded = true;
}

// Fetch all saving challenges for the current user
$challengeQuery = "SELECT * FROM SavingsChallenge WHERE UserID = ?";
$stmt = $db->prepare($challengeQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$challenges = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Challenge</title>
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
            min-height: 450px;
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
        .input-group textarea {
            height: 30px;
            width: 93%;
            padding: 5px 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid gray;
        }

        .input-group textarea {
            height: 60px;
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
    <button class="close-btn" onclick="closePage()">×</button>
        <div class="header">
            <h3>Savings Challenge</h3>
        </div>

        <!-- Display success message -->
        <?php if ($challengeAdded): ?>
            <div class="alert alert-success">Challenge added successfully!</div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST">
                <div class="input-group">
                    <label for="challenge_name">Challenge Name</label>
                    <input type="text" id="challenge_name" name="challenge_name" required placeholder="Enter challenge name">
                </div>

                <div class="input-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"  placeholder="Describe your challenge"></textarea>
                </div>

                <div class="input-group">
                    <label for="duration">Duration (days)</label>
                    <input type="number" id="duration" name="duration" required placeholder="Enter duration in days">
                </div>

                <div class="input-group">
                    <label for="saving_amount">Saving Amount</label>
                    <input type="number" id="saving_amount" name="saving_amount" required placeholder="Enter savings amount">
                </div>

                <button type="submit" name="add_challenge" class="btn">Add Challenge</button>
            </form>
        </div>

        <div class="table-container">
            <h3>Active Challenges</h3>
            <table>
                <tr>
                    <th>Challenge Name</th>
                    <th>Description</th>
                    <th>Duration</th>
                    <th>Savings</th>
                    <th>Status</th>
                </tr>
                <?php while ($row = $challenges->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['chllangeName']) ?></td>
                        <td><?= htmlspecialchars($row['Description']) ?></td>
                        <td><?= htmlspecialchars($row['Duration']) ?></td>
                        <td><?= htmlspecialchars($row['savingAmount']) ?></td>
                        <td><?= htmlspecialchars($row['Status']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>

