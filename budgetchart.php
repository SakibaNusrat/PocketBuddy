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
$first_Name = "Guest";
$userID = null;

$userQuery = "SELECT userID, first_name FROM USERS WHERE Email = ?";
$stmt = $db->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    // No matching user found for this session — force re-login
    session_destroy();
    header("Location: login.php");
    exit();
}

$userID = $user['userID'];
$first_Name = $user['first_name'];

// ===============================
// Budget + Total Spending Data
// ===============================

$labels = [];
$budgetData = [];
$spentData = [];

$totalBudget = 0;
$totalSpent = 0;

// month wise order
$monthOrder = "
    CASE Month
        WHEN 'January' THEN 1
        WHEN 'February' THEN 2
        WHEN 'March' THEN 3
        WHEN 'April' THEN 4
        WHEN 'May' THEN 5
        WHEN 'June' THEN 6
        WHEN 'July' THEN 7
        WHEN 'August' THEN 8
        WHEN 'September' THEN 9
        WHEN 'October' THEN 10
        WHEN 'November' THEN 11
        WHEN 'December' THEN 12
    END
";

// Check if goals table exists
$goalsTableExists = false;
$tableCheck = $db->query("SHOW TABLES LIKE 'goals'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $goalsTableExists = true;
}

// Fetch all active budgets
$budgetQuery = "
    SELECT BudgetID, Month, budgetyear, Amount
    FROM Budget
    WHERE userID = ?
    AND IsActive = 1
    ORDER BY budgetyear ASC, $monthOrder ASC
";

$stmt = $db->prepare($budgetQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$budgets = $stmt->get_result();

while ($budget = $budgets->fetch_assoc()) {

    $budgetID = $budget['BudgetID'];
    $month = $budget['Month'];
    $year = $budget['budgetyear'];

    $labels[] = $month . " " . $year;
    $budgetAmount = (float)$budget['Amount'];

    // Expense under this budget
    $expenseQuery = "
        SELECT COALESCE(SUM(Amount),0) AS totalExpense
        FROM Expense
        WHERE userID = ?
        AND budgetID = ?
    ";

    $expStmt = $db->prepare($expenseQuery);
    $expStmt->bind_param("ii", $userID, $budgetID);
    $expStmt->execute();
    $expenseResult = $expStmt->get_result()->fetch_assoc();
    $expStmt->close();

    $expense = (float)$expenseResult['totalExpense'];

    /*
    ==================================
    GOAL CALCULATION - Actual money saved
    ==================================
    */
    $goalAmount = 0;
    
    if ($goalsTableExists) {
        // Get goals that are active and associated with this budget
        // budgetID already links to the correct budget/month, no need for date filters
        $goalQuery = "
            SELECT 
                GoalID,
                current_amount,
                status
            FROM goals
            WHERE userID = ?
            AND budgetID = ?
            AND status != 'Completed'
        ";
        
        try {
            $goalStmt = $db->prepare($goalQuery);
            if ($goalStmt) {
                // Only 2 parameters now: userID and budgetID
                $goalStmt->bind_param("ii", $userID, $budgetID);
                $goalStmt->execute();
                $goalResult = $goalStmt->get_result();
                
                while ($goal = $goalResult->fetch_assoc()) {
                    // Actual taka already added to this goal (current_amount)
                    // is real money taken from the budget — use it directly.
                    $currentAmount = (float)$goal['current_amount'];
                    $goalAmount += $currentAmount;
                }
                $goalStmt->close();
            }
        } catch (Exception $e) {
            // If there's an error, skip goals
            $goalAmount = 0;
        }
    }

    /*
    ==================================
    ADD BILLS, SUBSCRIPTION, DONATION LATER
    ==================================
    */
    $bills = 0;
    $subscription = 0;
    $donation = 0;

    // Total spending = Expense + Goal + Bills + Subscription + Donation
    $monthSpent = $expense + $goalAmount + $bills + $subscription + $donation;

    $budgetData[] = $budgetAmount;
    $spentData[] = $monthSpent;

    $totalBudget += $budgetAmount;
    $totalSpent += $monthSpent;
}

$stmt->close();

$remainingBudget = $totalBudget - $totalSpent;

$hasData = count($budgetData) > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketBuddy | Budget Chart</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap");

        :root {
            --rust: #A6432C;
            --terracotta: #C15A3E;
            --terracotta-light: #D97A55;
            --cream: #FBF6F3;
            --paper: #FDF3EF;
            --line: #ECE0DB;
            --ink: #2B2320;
            --ink-soft: #7A6E68;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--paper);
            color: var(--ink);
            font-size: 15px;
        }

        h1, h2, h3 { font-family: 'Fraunces', serif; }

        a, a:hover, a:focus {
            color: inherit;
            text-decoration: none;
            transition: all 0.2s;
        }

        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
            min-height: 100vh;
        }

        /* Sidebar */
        #sidebar {
            min-width: 260px;
            max-width: 260px;
            background: #201512;
            color: #E7DDD8;
            transition: margin-left 0.3s;
        }

        #sidebar.active { margin-left: -260px; }

        #sidebar .sidebar-header {
            padding: 28px 24px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        #sidebar .sidebar-header .mark {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: linear-gradient(160deg, var(--terracotta-light), var(--rust));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
            font-size: 14px;
        }

        #sidebar .sidebar-header h3 {
            font-size: 17px;
            margin: 0;
            color: #fff;
            font-weight: 600;
        }

        #sidebar .section-label {
            display: block;
            padding: 22px 24px 8px;
            font-size: 11px;
            letter-spacing: 0.04em;
            color: #9C8D86;
        }

        #sidebar ul.components {
            padding: 4px 12px 20px;
            margin: 0;
            list-style: none;
        }

        #sidebar ul li a {
            padding: 10px 12px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 8px;
            color: #D7CAC4;
        }

        #sidebar ul li a i { width: 16px; text-align: center; color: #B79C8F; }
        #sidebar ul li a:hover { background: rgba(255,255,255,0.06); color: #fff; }
        #sidebar ul li.active > a { background: var(--terracotta); color: #fff; }
        #sidebar ul li.active > a i { color: #fff; }

        /* Content */
        #content {
            width: 100%;
            padding: 28px 34px 40px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 26px;
        }

        .btn-toggle {
            border: 1px solid var(--line);
            background: #fff;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            color: var(--rust);
        }

        .page-title h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .page-title p {
            font-size: 13px;
            color: var(--ink-soft);
            margin: 2px 0 0;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px 6px 6px;
            border-radius: 30px;
            border: 1px solid var(--line);
            background: #fff;
        }

        .user-chip .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(160deg, var(--terracotta-light), var(--rust));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
        }

        .user-chip .username { font-size: 13px; font-weight: 500; }

        /* Stat cards */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 20px 22px;
        }

        .stat-card .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: var(--rust);
            background: var(--cream);
        }

        .stat-card .stat-label { font-size: 12.5px; color: var(--ink-soft); }

        .stat-card .stat-value {
            font-family: 'Fraunces', serif;
            font-size: 24px;
            font-weight: 600;
        }

        /* Panel */
        .panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 26px;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .panel-header h2 { font-size: 16px; font-weight: 600; margin: 0; }

        .panel-header a {
            font-size: 12.5px;
            color: var(--terracotta);
            font-weight: 500;
        }

        .chart-wrap { position: relative; height: 360px; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--ink-soft);
        }

        .empty-state i {
            font-size: 34px;
            color: var(--terracotta-light);
            margin-bottom: 14px;
            display: block;
        }

        .empty-state a {
            display: inline-block;
            margin-top: 16px;
            background: var(--terracotta);
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
        }

        .empty-state a:hover { background: var(--rust); }

        @media (max-width: 992px) {
            .stat-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 768px) {
            #sidebar { margin-left: -260px; }
            #sidebar.active { margin-left: 0; }
            #content { padding: 20px; }
            .stat-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <div class="mark">PB</div>
                <h3>PocketBuddy</h3>
            </div>

            <span class="section-label">Overview</span>
            <ul class="list-unstyled components">
                <li><a href="index.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li class="active"><a href="budgetchart.php"><i class="fas fa-chart-pie"></i> Budget Chart</a></li>
                <li><a href="goalchart.php"><i class="fas fa-bullseye"></i> Goal Chart</a></li>
            </ul>

            <span class="section-label">Manage</span>
            <ul class="list-unstyled components">
                <li><a href="budget.php"><i class="fas fa-wallet"></i> Budget</a></li>
                <li><a href="goal.php"><i class="fas fa-flag"></i> Goal</a></li>
                <li><a href="expense.php"><i class="fas fa-receipt"></i> Expense</a></li>
                <li><a href="donation.php"><i class="fas fa-hand-holding-heart"></i> Donation</a></li>
                <li><a href="subscription.php"><i class="fas fa-sync-alt"></i> Subscription</a></li>
                <li><a href="bills.php"><i class="fas fa-file-invoice-dollar"></i> Bills</a></li>
                <li><a href="savingChallenge.php"><i class="fas fa-trophy"></i> Saving Challenge</a></li>
            </ul>

            <span class="section-label">Accounts</span>
            <ul class="list-unstyled components">
                <li><a href="child_sub_account.php"><i class="fas fa-users"></i> Child Sub Account</a></li>
                <li><a href="bankaccountadd.php"><i class="fas fa-university"></i> Bank Account</a></li>
                <li><a href="#"><i class="fas fa-info-circle"></i> About</a></li>
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <div class="topbar">
                <div style="display:flex; align-items:center; gap:16px;">
                    <button type="button" id="sidebarCollapse" class="btn btn-toggle">
                        <i class="fas fa-align-left"></i>
                    </button>
                    <div class="page-title">
                        <h1>Budget Overview</h1>
                        <p>Compare your monthly budget with actual spending</p>
                    </div>
                </div>

                <div class="user-chip">
                    <span class="avatar"><?php echo strtoupper(substr($first_Name, 0, 1)); ?></span>
                    <span class="username"><?php echo htmlspecialchars($first_Name); ?></span>
                </div>
            </div>

            <?php if ($hasData): ?>
            <!-- Stat Cards with IDs -->
            <div class="stat-grid">
                <!-- Total Budget -->
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Total Budget</span>
                        <span class="stat-icon">
                            <i class="fas fa-wallet"></i>
                        </span>
                    </div>
                    <div class="stat-value" id="statBudget">
                        ৳<?php echo number_format($totalBudget,2); ?>
                    </div>
                </div>

                <!-- Total Spent -->
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Total Spent</span>
                        <span class="stat-icon">
                            <i class="fas fa-receipt"></i>
                        </span>
                    </div>
                    <div class="stat-value" id="statSpent">
                        ৳<?php echo number_format($totalSpent,2); ?>
                    </div>
                </div>

                <!-- Remaining -->
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Remaining Budget</span>
                        <span class="stat-icon">
                            <i class="fas fa-piggy-bank"></i>
                        </span>
                    </div>
                    <div class="stat-value" id="statRemaining">
                        ৳<?php echo number_format($remainingBudget,2); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Chart Panel -->
            <div class="panel">
                <div class="panel-header">
                    <h2>Monthly Budget vs Spending</h2>
                    <div style="display:flex; align-items:center; gap:14px;">
                        <?php if ($hasData): ?>
                        <select id="monthFilter" onchange="updateView()" style="border:1px solid var(--line); border-radius:8px; padding:6px 10px; font-size:12.5px; font-family:'Poppins',sans-serif; color:var(--ink); background:#fff;">
                            <option value="all">All Months</option>
                            <?php foreach ($labels as $i => $lbl): ?>
                                <option value="<?php echo $i; ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <a href="budget.php">Manage budgets</a>
                    </div>
                </div>

                <?php if ($hasData): ?>
                    <div class="chart-wrap">
                        <canvas id="budget-chart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <p>You haven't set any budgets yet. Add one to see your chart here.</p>
                        <a href="budget.php">Add a budget</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
            });
        });

        <?php if ($hasData): ?>
        const allLabels = <?php echo json_encode($labels); ?>;
        const allBudget  = <?php echo json_encode($budgetData); ?>;
        const allSpent   = <?php echo json_encode($spentData); ?>;

        let budgetChart;

        function formatMoney(n) {
            return '৳' + n.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        }

        function renderChart(labels, budgetArr, spentArr) {
            const ctx = document.getElementById('budget-chart').getContext('2d');

            const budgetGradient = ctx.createLinearGradient(0,0,0,360);
            budgetGradient.addColorStop(0,'rgba(193,90,62,0.9)');
            budgetGradient.addColorStop(1,'rgba(193,90,62,0.35)');

            const spentGradient = ctx.createLinearGradient(0,0,0,360);
            spentGradient.addColorStop(0,'rgba(43,35,32,0.85)');
            spentGradient.addColorStop(1,'rgba(43,35,32,0.25)');

            if (budgetChart) budgetChart.destroy();

            budgetChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Budget', data: budgetArr, backgroundColor: budgetGradient, borderColor: '#A6432C', borderWidth: 1.5, borderRadius: 6 },
                        { label: 'Total Spending', data: spentArr, backgroundColor: spentGradient, borderColor: '#2B2320', borderWidth: 1.5, borderRadius: 6 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: {
                            backgroundColor: '#201512',
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatMoney(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, ticks: { callback: function(value){ return '৳'+value; } } }
                    }
                }
            });
        }

        function updateView() {
            const sel = document.getElementById('monthFilter').value;

            if (sel === 'all') {
                const totalB = allBudget.reduce((a,b) => a+b, 0);
                const totalS = allSpent.reduce((a,b) => a+b, 0);
                document.getElementById('statBudget').textContent = formatMoney(totalB);
                document.getElementById('statSpent').textContent = formatMoney(totalS);
                document.getElementById('statRemaining').textContent = formatMoney(totalB - totalS);
                renderChart(allLabels, allBudget, allSpent);
            } else {
                const i = parseInt(sel, 10);
                const b = allBudget[i];
                const s = allSpent[i];
                document.getElementById('statBudget').textContent = formatMoney(b);
                document.getElementById('statSpent').textContent = formatMoney(s);
                document.getElementById('statRemaining').textContent = formatMoney(b - s);
                renderChart([allLabels[i]], [b], [s]);
            }
        }

        // initial render = All Months
        updateView();
        <?php endif; ?>
    </script>
</body>
</html>