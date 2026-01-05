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

// Fetch budget data for the user (no filter for IsActive)
$budgetQuery = "SELECT Month, Amount FROM Budget WHERE userID = ?";
$stmt = $db->prepare($budgetQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$budgets = $stmt->get_result();

// Arrays to hold labels (Month) and data (Amount)
$labels = [];
$data = [];

while ($budget = $budgets->fetch_assoc()) {
    $labels[] = $budget['Month'];
    $data[] = $budget['Amount'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Chart</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-4">
    <h2>Budget Chart</h2>

    <!-- Chart Container -->
    <div class="row">
        <div class="col-md-8">
            <canvas id="budget-chart"></canvas>
        </div>
    </div>

    <!-- JavaScript for Chart -->
    <script>
        // Data for the chart
        const dataChart = {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>, // PHP variable for labels (Months)
                datasets: [{
                    label: 'Budget Amounts',
                    data: <?php echo json_encode($data); ?>, // PHP variable for data (Amounts)
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)',
                    ],
                    borderColor: [
                        'rgba(255,99,132,1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        ticks: {
                            color: '#4285F4', // Customize the color of x-axis ticks
                        },
                    },
                    y: {
                        ticks: {
                            color: '#f44242', // Customize the color of y-axis ticks
                        },
                    },
                },
            }
        };

        // Create the chart
        new Chart(
            document.getElementById('budget-chart'), // Target the canvas by its ID
            dataChart // Pass the chart data and options
        );
    </script>

</div>
</body>
</html>
