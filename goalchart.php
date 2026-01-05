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

// Fetch userID based on the logged-in user's email
$userEmail = $_SESSION['email'];
$userQuery = "SELECT userID FROM USERS WHERE Email = ?";
$stmt = $db->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    $userID = $user['userID'];

    // Fetch goals data based on the userID
    $goalQuery = "SELECT goalID, goalName, TargetAmount, current_amount, Startdate, end_date, status FROM Goals WHERE userID = ?";
    $goalStmt = $db->prepare($goalQuery);
    $goalStmt->bind_param("i", $userID);
    $goalStmt->execute();
    $goalData = $goalStmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // Handle the case where the user is not found
    $goalData = [];
    echo "User not found!";
}

// Close the prepared statements
$stmt->close();
$goalStmt->close();
$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Progress Chart</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.1.4/Chart.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background-color: #f9f9fa;
        }

        .container {
            padding: 20px;
        }

        .card {
            background: #fff;
            border-radius: .25rem;
            box-shadow: 0 1px 3px #641e16;
        }

        .card-header {
            padding: .75rem 1.25rem;
            background-color:rgb(228, 149, 141);
            border-bottom: 1px solid #fff;
        }

        .card-body {
            height: 420px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        canvas {
            width: 100%;
            height: auto;
        }

        select {
            margin-bottom: 20px;
            padding: 8px;
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
    <button class="close-btn" onclick="closePage()">×</button> <!-- Cross button -->
        <div class="card">
            <div class="card-header">Select Goal to See Progress</div>
            <div class="card-body">
                <!-- Dropdown to select a goal -->
                <select id="goalSelect" onchange="updateChart()">
                    <option value="">Select a Goal</option>
                    <?php
                    // Ensure $goalData is populated before generating the dropdown
                    if (!empty($goalData)) {
                        foreach ($goalData as $goal) {
                            // Generate each option dynamically
                            echo '<option value="' . htmlspecialchars($goal['goalID'], ENT_QUOTES, 'UTF-8') . '">'
                                . htmlspecialchars($goal['goalName'], ENT_QUOTES, 'UTF-8') 
                                . '</option>';
                        }
                    } else {
                        // Fallback message if no goals are available
                        echo '<option value="">No goals found</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>
</body>

<script>
    // Pass all the goal data to JavaScript as JSON
    const goals = <?php echo json_encode($goalData); ?>;
    let chart; // Reference to the chart object

    function updateChart() {
        const selectedGoalID = document.getElementById('goalSelect').value;

        if (!selectedGoalID) {
            if (chart) chart.destroy();
            alert('Please select a valid goal.');
            return;
        }

        // Find the selected goal's data
        const selectedGoal = goals.find(goal => goal.goalID == selectedGoalID);

        if (!selectedGoal) {
            alert('Goal not found!');
            return;
        }

        const targetAmount = parseFloat(selectedGoal.TargetAmount);
        const currentAmount = parseFloat(selectedGoal.current_amount);
        const remainingAmount = targetAmount - currentAmount;

        // Prepare data for the pie chart
        const chartData = {
            labels: ['Current Amount', 'Remaining Amount'],
            datasets: [{
                data: [currentAmount, remainingAmount],
                backgroundColor: ['#4CAF50', '#FF5722'],
            }]
        };

        // Destroy the previous chart if it exists
        if (chart) chart.destroy();

        // Create a new chart
        const ctx = document.getElementById('goalChart').getContext('2d');
        chart = new Chart(ctx, {
            type: 'pie',
            data: chartData,
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: `Goal Progress: ${selectedGoal.goalName}`
                }
            }
        });
    }
</script>

<!-- Add the canvas element for the chart -->
<canvas id="goalChart"></canvas>


    <canvas id="goalChart"></canvas>
</body>
</html>




