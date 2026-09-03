<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Connect to the database
$db = mysqli_connect('localhost', 'root', '', 'pocketbuddy');
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch the user's first name based on their email
$first_Name = "Guest"; // Default value
$userID = null;
if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
    $query = "SELECT userID, first_name FROM users WHERE email = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $first_Name = $row['first_name'];
        $userID = $row['userID'];
    }
    $stmt->close();
}

// ===============================
// Selected Month / Year (defaults to current month, but user can pick any past month)
// ===============================

$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$nowMonthNum = (int) date('n');
$nowYear = (int) date('Y');

// Read requested month/year from query string, fall back to current month
$selectedMonthNum = isset($_GET['month']) ? (int) $_GET['month'] : $nowMonthNum;
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : $nowYear;

// Validate — if garbage/out-of-range values come in, fall back to current month
if ($selectedMonthNum < 1 || $selectedMonthNum > 12) {
    $selectedMonthNum = $nowMonthNum;
}
if ($selectedYear < 2000 || $selectedYear > $nowYear + 1) {
    $selectedYear = $nowYear;
}

$currentMonthName = $monthNames[$selectedMonthNum]; // used in Budget queries (Month column is stored as name)
$currentYear = $selectedYear;
$isCurrentMonth = ($selectedMonthNum === $nowMonthNum && $selectedYear === $nowYear);

// Build a simple list of months to offer in the dropdown: current month + past 11 months
$monthOptions = [];
for ($i = 0; $i < 12; $i++) {
    $m = $nowMonthNum - $i;
    $y = $nowYear;
    while ($m < 1) {
        $m += 12;
        $y -= 1;
    }
    $monthOptions[] = ['num' => $m, 'year' => $y, 'label' => $monthNames[$m] . ' ' . $y];
}

// ===============================
// Dashboard Stat Data (REAL, scoped to the selected month)
// ===============================

// ---- Monthly Budget (selected month's active budget total) ----
$monthlyBudget = 0;
$budgetIDs = []; // budgetIDs for the selected month, needed for expense lookups

$mbQuery = "SELECT BudgetID, Amount FROM Budget 
            WHERE userID = ? AND Month = ? AND budgetyear = ? AND IsActive = 1";
$stmt = $db->prepare($mbQuery);
$stmt->bind_param("isi", $userID, $currentMonthName, $currentYear);
$stmt->execute();
$mbResult = $stmt->get_result();
while ($row = $mbResult->fetch_assoc()) {
    $monthlyBudget += (float)$row['Amount'];
    $budgetIDs[] = (int)$row['BudgetID'];
}
$stmt->close();

// ---- Monthly Spent (expenses tied to the selected month's budgets) ----
$monthlySpent = 0;
if (!empty($budgetIDs)) {
    $placeholders = implode(',', array_fill(0, count($budgetIDs), '?'));
    $types = 'i' . str_repeat('i', count($budgetIDs));
    $expQuery = "SELECT COALESCE(SUM(Amount),0) AS total FROM Expense 
                 WHERE userID = ? AND budgetID IN ($placeholders)";
    $stmt = $db->prepare($expQuery);
    $stmt->bind_param($types, $userID, ...$budgetIDs);
    $stmt->execute();
    $expRow = $stmt->get_result()->fetch_assoc();
    $monthlySpent = (float)$expRow['total'];
    $stmt->close();
}
$budgetUsedPct = $monthlyBudget > 0 ? round(($monthlySpent / $monthlyBudget) * 100) : 0;

// ---- Total Balance (lifetime: all budgets minus all spending — always overall, not month-scoped) ----
$totalBalance = 0;
$tbQuery = "SELECT COALESCE(SUM(Amount),0) AS total FROM Budget WHERE userID = ? AND IsActive = 1";
$stmt = $db->prepare($tbQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$tbRow = $stmt->get_result()->fetch_assoc();
$lifetimeBudget = (float)$tbRow['total'];
$stmt->close();

$tsQuery = "SELECT COALESCE(SUM(Amount),0) AS total FROM Expense WHERE userID = ?";
$stmt = $db->prepare($tsQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$tsRow = $stmt->get_result()->fetch_assoc();
$lifetimeSpent = (float)$tsRow['total'];
$stmt->close();

$totalBalance = $lifetimeBudget - $lifetimeSpent;

// ---- Savings Goal (sum current_amount / sum TargetAmount, active goals — always overall) ----
$savedAmount = 0;
$targetAmount = 0;

$check = $db->query("SHOW TABLES LIKE 'goals'");
if ($check && $check->num_rows > 0) {
    $gQuery = "SELECT COALESCE(SUM(current_amount),0) AS saved, COALESCE(SUM(TargetAmount),0) AS target 
               FROM goals WHERE userID = ? AND status != 'Completed'";
    $stmt = $db->prepare($gQuery);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $gRow = $stmt->get_result()->fetch_assoc();
    $savedAmount = (float)$gRow['saved'];
    $targetAmount = (float)$gRow['target'];
    $stmt->close();
}
$savingsPct = $targetAmount > 0 ? round(($savedAmount / $targetAmount) * 100) : 0;

// ---- Upcoming Bills (due within next 7 days — only meaningful for the current month, kept as-is) ----
$upcomingBillsTotal = 0;
$nearestDueInDays = null;

$check = $db->query("SHOW TABLES LIKE 'bills'");
if ($check && $check->num_rows > 0) {
    // Check if DueDate column exists
    $colCheck = $db->query("SHOW COLUMNS FROM bills LIKE 'DueDate'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $bQuery = "SELECT COALESCE(SUM(Amount),0) AS total, MIN(DueDate) AS nearest 
                   FROM bills 
                   WHERE userID = ? AND DueDate >= CURDATE() AND DueDate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $stmt = $db->prepare($bQuery);
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $bRow = $stmt->get_result()->fetch_assoc();
        $upcomingBillsTotal = (float)$bRow['total'];
        if ($bRow['nearest']) {
            $nearestDueInDays = (new DateTime())->diff(new DateTime($bRow['nearest']))->days;
        }
        $stmt->close();
    }
}

// ---- Category-wise Spending (for the Spending Overview chart, selected month's budgets) ----
$categorySpending = []; // ['Category' => totalAmount, ...]
if (!empty($budgetIDs)) {
    $placeholders = implode(',', array_fill(0, count($budgetIDs), '?'));
    $types = 'i' . str_repeat('i', count($budgetIDs));
    $catQuery = "SELECT Category, COALESCE(SUM(Amount),0) AS total 
                 FROM Expense 
                 WHERE userID = ? AND budgetID IN ($placeholders)
                 GROUP BY Category
                 ORDER BY total DESC";
    $stmt = $db->prepare($catQuery);
    $stmt->bind_param($types, $userID, ...$budgetIDs);
    $stmt->execute();
    $catResult = $stmt->get_result();
    while ($row = $catResult->fetch_assoc()) {
        $catName = $row['Category'] ?: 'Other';
        $categorySpending[$catName] = (float) $row['total'];
    }
    $stmt->close();
}

// ---- Recent Transactions (last 5 expenses within the selected month's budgets) ----
$recentTransactions = [];
if (!empty($budgetIDs)) {
    $placeholders = implode(',', array_fill(0, count($budgetIDs), '?'));
    $types = 'i' . str_repeat('i', count($budgetIDs));
    $txQuery = "SELECT Amount, Category, Date 
                FROM Expense 
                WHERE userID = ? AND budgetID IN ($placeholders)
                ORDER BY Date DESC 
                LIMIT 5";
    $stmt = $db->prepare($txQuery);
    $stmt->bind_param($types, $userID, ...$budgetIDs);
    $stmt->execute();
    $txResult = $stmt->get_result();
    while ($row = $txResult->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
    $stmt->close();
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketBuddy | Dashboard</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
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

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--paper);
            color: var(--ink);
            font-size: 15px;
        }

        h1, h2, h3, .brand-mark {
            font-family: 'Fraunces', serif;
        }

        a,
        a:hover,
        a:focus {
            color: inherit;
            text-decoration: none;
            transition: all 0.2s;
        }

        /* ===== Layout ===== */
        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
            min-height: 100vh;
        }

        /* ===== Sidebar ===== */
        #sidebar {
            min-width: 260px;
            max-width: 260px;
            background: #201512;
            color: #E7DDD8;
            transition: margin-left 0.3s;
        }

        #sidebar.active {
            margin-left: -260px;
        }

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

        #sidebar ul li a i {
            width: 16px;
            text-align: center;
            color: #B79C8F;
        }

        #sidebar ul li a:hover {
            background: rgba(255,255,255,0.06);
            color: #fff;
        }

        #sidebar ul li.active > a {
            background: var(--terracotta);
            color: #fff;
        }

        #sidebar ul li.active > a i {
            color: #fff;
        }

        ul ul {
            list-style: none;
            padding-left: 0;
            margin: 4px 0 8px;
        }

        ul ul a {
            font-size: 13px !important;
            padding-left: 42px !important;
            color: #C7B8B1;
        }

        /* ===== Content ===== */
        #content {
            width: 100%;
            padding: 28px 34px 40px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .topbar .btn-toggle {
            border: 1px solid var(--line);
            background: #fff;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            color: var(--rust);
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 360px;
            margin-left: 18px;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ink-soft);
            font-size: 13px;
        }

        .search-box input {
            width: 100%;
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 8px;
            padding: 9px 14px 9px 36px;
            font-size: 13px;
            outline: none;
        }

        .search-box input:focus {
            border-color: var(--terracotta);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .icon-btn {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ink-soft);
            font-size: 14px;
        }

        .icon-btn .dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--terracotta);
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px 6px 6px;
            border-radius: 30px;
            border: 1px solid var(--line);
            background: #fff;
            cursor: pointer;
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

        .user-chip .username {
            font-size: 13px;
            font-weight: 500;
        }

        .dropdown-menu {
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 12px 28px rgba(43, 35, 32, 0.1);
            font-size: 13px;
        }

        .dropdown-item i {
            color: var(--terracotta);
            width: 16px;
            margin-right: 4px;
        }

        .dropdown-item:active,
        .dropdown-item:hover {
            background: var(--cream);
        }

        /* ===== Month selector ===== */
        .month-select-wrap {
            position: relative;
        }

        .month-select-wrap select {
            appearance: none;
            -webkit-appearance: none;
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 8px;
            padding: 9px 32px 9px 14px;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink);
            cursor: pointer;
            outline: none;
        }

        .month-select-wrap select:focus {
            border-color: var(--terracotta);
        }

        .month-select-wrap::after {
            content: "\f078";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            font-size: 10px;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ink-soft);
            pointer-events: none;
        }

        .current-month-badge {
            font-size: 11px;
            background: var(--cream);
            color: var(--rust);
            padding: 2px 9px;
            border-radius: 20px;
            font-weight: 600;
            margin-left: 8px;
            vertical-align: middle;
        }

        /* ===== Welcome banner ===== */
        .welcome-banner {
            background: linear-gradient(150deg, var(--terracotta) 0%, var(--rust) 100%);
            border-radius: 16px;
            padding: 34px 36px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 26px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::after {
            content: "";
            position: absolute;
            right: -60px;
            top: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }

        .welcome-banner h1 {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .welcome-banner p {
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: rgba(255,255,255,0.85);
            margin: 0;
            max-width: 420px;
        }

        .welcome-banner .btn-add {
            background: #fff;
            color: var(--rust);
            border: none;
            padding: 11px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .welcome-banner .btn-add:hover {
            background: var(--cream);
        }

        /* ===== Stat cards ===== */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
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

        .stat-card .stat-label {
            font-size: 12.5px;
            color: var(--ink-soft);
        }

        .stat-card .stat-value {
            font-family: 'Fraunces', serif;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .stat-card .stat-delta {
            font-size: 12px;
            color: #3F8F5C;
        }

        .stat-card .stat-delta.down {
            color: var(--terracotta);
        }

        /* ===== Panels ===== */
        .panel-row {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 20px;
        }

        .panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 24px;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .panel-header h2 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .panel-header a {
            font-size: 12.5px;
            color: var(--terracotta);
            font-weight: 500;
        }

        .placeholder-chart {
            height: 200px;
            border-radius: 10px;
            background: repeating-linear-gradient(
                135deg,
                var(--cream),
                var(--cream) 10px,
                #fff 10px,
                #fff 20px
            );
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ink-soft);
            font-size: 13px;
            border: 1px dashed var(--line);
        }

        .chart-wrap {
            height: 240px;
            position: relative;
        }

        .chart-legend {
            list-style: none;
            padding: 0;
            margin: 14px 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
        }

        .chart-legend li {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--ink-soft);
        }

        .chart-legend .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
        }

        .tx-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tx-list li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
        }

        .tx-list li:last-child {
            border-bottom: none;
        }

        .tx-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .tx-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--cream);
            color: var(--rust);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        .tx-name {
            font-size: 13.5px;
            font-weight: 500;
        }

        .tx-date {
            font-size: 11.5px;
            color: var(--ink-soft);
        }

        .tx-amount {
            font-size: 13.5px;
            font-weight: 600;
        }

        .tx-amount.neg {
            color: var(--terracotta);
        }

        .tx-amount.pos {
            color: #3F8F5C;
        }

        /* Transaction category icons */
        .tx-icon .fa-utensils { color: var(--terracotta); }
        .tx-icon .fa-shopping-bag { color: #6B4F8A; }
        .tx-icon .fa-car { color: #3A7B8C; }
        .tx-icon .fa-home { color: #8B6F4C; }
        .tx-icon .fa-film { color: #A65A7A; }
        .tx-icon .fa-heart { color: #C15A3E; }
        .tx-icon .fa-receipt { color: var(--ink-soft); }

        @media (max-width: 992px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .panel-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            #sidebar {
                margin-left: -260px;
            }
            #sidebar.active {
                margin-left: 0;
            }
            #content {
                padding: 20px;
            }
            .stat-grid {
                grid-template-columns: 1fr;
            }
            .search-box {
                display: none;
            }
            .month-select-wrap {
                display: none;
            }
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
                <li class="active"><a href="index.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="budgetchart.php"><i class="fas fa-chart-pie"></i> Budget Chart</a></li>
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
                <button type="button" id="sidebarCollapse" class="btn btn-toggle">
                    <i class="fas fa-align-left"></i>
                </button>

                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search transactions, goals, bills...">
                </div>

                <div class="topbar-right">
                    <!-- Month / Year selector: defaults to current month, user can switch to any past month -->
                    <form method="get" class="month-select-wrap" id="monthForm">
                        <select name="month_year" onchange="
                            var v = this.value.split('-');
                            window.location.href = 'index.php?month=' + v[0] + '&year=' + v[1];
                        ">
                            <?php foreach ($monthOptions as $opt): ?>
                                <option value="<?php echo $opt['num'] . '-' . $opt['year']; ?>"
                                    <?php echo ($opt['num'] === $selectedMonthNum && $opt['year'] === $selectedYear) ? 'selected' : ''; ?>>
                                    <?php echo $opt['label']; ?><?php echo ($opt['num'] === $nowMonthNum && $opt['year'] === $nowYear) ? ' (Current)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <a class="icon-btn" href="#"><i class="fas fa-bell"></i><span class="dot"></span></a>
                    <a class="icon-btn" href="message.php"><i class="fas fa-envelope"></i></a>

                    <!-- Avatar + Name (click goes to profile.php) + Dropdown chevron -->
                    <div class="dropdown">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <a class="user-chip" href="profile.php" style="padding-right:8px; border-right:0;">
                                <span class="avatar"><?php echo strtoupper(substr($first_Name, 0, 1)); ?></span>
                                <span class="username"><?php echo htmlspecialchars($first_Name); ?></span>
                            </a>
                            <a href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                               style="padding:4px 8px; color:var(--ink-soft);">
                                <i class="fas fa-chevron-down" style="font-size:11px;"></i>
                            </a>
                        </div>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div>
                    <h1>Welcome back, <?php echo htmlspecialchars($first_Name); ?></h1>
                    <p>
                        Here's a snapshot of your budgets, goals, and upcoming bills for
                        <?php echo $currentMonthName . ' ' . $currentYear; ?>.
                        <?php if (!$isCurrentMonth): ?>
                            <span class="current-month-badge">Viewing past month</span>
                        <?php endif; ?>
                    </p>
                </div>
                <button class="btn btn-add"><i class="fas fa-plus mr-2"></i>Add Expense</button>
            </div>

            <!-- Stat Cards with REAL DATA -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Total Balance</span>
                        <span class="stat-icon"><i class="fas fa-wallet"></i></span>
                    </div>
                    <div class="stat-value">$<?php echo number_format($totalBalance, 2); ?></div>
                    <span class="stat-delta <?php echo $totalBalance < 0 ? 'down' : ''; ?>">
                        <i class="fas fa-arrow-<?php echo $totalBalance < 0 ? 'down' : 'up'; ?>"></i>
                        <?php echo $totalBalance < 0 ? 'Over budget' : 'On track'; ?>
                    </span>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label"><?php echo $currentMonthName; ?> Budget</span>
                        <span class="stat-icon"><i class="fas fa-chart-pie"></i></span>
                    </div>
                    <div class="stat-value">$<?php echo number_format($monthlyBudget, 2); ?></div>
                    <span class="stat-delta <?php echo $budgetUsedPct > 80 ? 'down' : ''; ?>">
                        <i class="fas fa-arrow-down"></i> <?php echo $budgetUsedPct; ?>% used
                    </span>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Savings Goal</span>
                        <span class="stat-icon"><i class="fas fa-bullseye"></i></span>
                    </div>
                    <div class="stat-value">$<?php echo number_format($savedAmount, 2); ?></div>
                    <span class="stat-delta">
                        <i class="fas fa-arrow-up"></i> <?php echo $savingsPct; ?>% of $<?php echo number_format($targetAmount, 2); ?>
                    </span>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Upcoming Bills</span>
                        <span class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                    </div>
                    <div class="stat-value">$<?php echo number_format($upcomingBillsTotal, 2); ?></div>
                    <span class="stat-delta down">
                        <i class="fas fa-clock"></i>
                        <?php echo $nearestDueInDays !== null ? "Due in {$nearestDueInDays} days" : "None due soon"; ?>
                    </span>
                </div>
            </div>

            <!-- Panels -->
            <div class="panel-row">
                <div class="panel">
                    <div class="panel-header">
                        <h2>Spending Overview — <?php echo $currentMonthName; ?></h2>
                        <a href="budgetchart.php">View report</a>
                    </div>
                    <?php if (!empty($categorySpending)): ?>
                        <div class="chart-wrap">
                            <canvas id="spendingChart"></canvas>
                        </div>
                        <ul class="chart-legend" id="spendingLegend"></ul>
                    <?php else: ?>
                        <div class="placeholder-chart">No spending recorded for <?php echo $currentMonthName . ' ' . $currentYear; ?> yet</div>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2><?php echo $isCurrentMonth ? 'Recent' : $currentMonthName; ?> Transactions</h2>
                        <a href="expense.php">View all</a>
                    </div>
                    <ul class="tx-list">
                        <?php if (!empty($recentTransactions)): ?>
                            <?php foreach ($recentTransactions as $tx): ?>
                            <li>
                                <div class="tx-left">
                                    <span class="tx-icon">
                                        <?php
                                        // Map category to icon
                                        $category = strtolower($tx['Category'] ?? '');
                                        $icon = 'fa-receipt';
                                        if (strpos($category, 'food') !== false || strpos($category, 'restaurant') !== false || strpos($category, 'grocery') !== false) {
                                            $icon = 'fa-utensils';
                                        } elseif (strpos($category, 'transport') !== false || strpos($category, 'car') !== false || strpos($category, 'fuel') !== false) {
                                            $icon = 'fa-car';
                                        } elseif (strpos($category, 'rent') !== false || strpos($category, 'house') !== false || strpos($category, 'home') !== false) {
                                            $icon = 'fa-home';
                                        } elseif (strpos($category, 'shopping') !== false || strpos($category, 'clothing') !== false) {
                                            $icon = 'fa-shopping-bag';
                                        } elseif (strpos($category, 'entertain') !== false || strpos($category, 'movie') !== false) {
                                            $icon = 'fa-film';
                                        } elseif (strpos($category, 'donation') !== false || strpos($category, 'charity') !== false) {
                                            $icon = 'fa-heart';
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </span>
                                    <div>
                                        <div class="tx-name"><?php echo htmlspecialchars($tx['Category'] ?? 'Transaction'); ?></div>
                                        <div class="tx-date"><?php echo date('M d, g:i A', strtotime($tx['Date'])); ?></div>
                                    </div>
                                </div>
                                <span class="tx-amount neg">-$<?php echo number_format($tx['Amount'], 2); ?></span>
                            </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>
                                <div class="tx-left">
                                    <span class="tx-icon"><i class="fas fa-info-circle"></i></span>
                                    <div>
                                        <div class="tx-name">No transactions for this month</div>
                                        <div class="tx-date">Try a different month, or start adding expenses</div>
                                    </div>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
            });
        });

        // Category-wise spending for the selected month, passed from PHP
        var categorySpending = <?php echo json_encode($categorySpending, JSON_NUMERIC_CHECK); ?>;

        var chartColors = [
            '#C15A3E', '#6B4F8A', '#3A7B8C', '#8B6F4C',
            '#A65A7A', '#3F8F5C', '#A6432C', '#D97A55',
            '#7A6E68', '#C9A66B'
        ];

        var canvasEl = document.getElementById('spendingChart');
        if (canvasEl && categorySpending && Object.keys(categorySpending).length > 0) {
            var labels = Object.keys(categorySpending);
            var values = Object.values(categorySpending);
            var colors = labels.map(function (_, i) { return chartColors[i % chartColors.length]; });

            new Chart(canvasEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var value = ctx.raw || 0;
                                    var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                    var pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return ctx.label + ': $' + value.toFixed(2) + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // Build a custom legend below the chart (matches PocketBuddy's own styling)
            var legendEl = document.getElementById('spendingLegend');
            if (legendEl) {
                labels.forEach(function (label, i) {
                    var li = document.createElement('li');
                    var dot = document.createElement('span');
                    dot.className = 'dot';
                    dot.style.background = colors[i];
                    li.appendChild(dot);
                    li.appendChild(document.createTextNode(label + ' — $' + values[i].toFixed(2)));
                    legendEl.appendChild(li);
                });
            }
        }
    </script>
</body>

</html>