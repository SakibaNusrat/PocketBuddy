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
if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
    $query = "SELECT first_name FROM users WHERE email = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $first_Name = $row['first_name'];
    }
    $stmt->close();
}
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
                    <a class="icon-btn" href="#"><i class="fas fa-bell"></i><span class="dot"></span></a>
                    <a class="icon-btn" href="message.php"><i class="fas fa-envelope"></i></a>

                    <div class="dropdown">
                        <a class="user-chip dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="avatar"><?php echo strtoupper(substr($first_Name, 0, 1)); ?></span>
                            <span class="username"><?php echo htmlspecialchars($first_Name); ?></span>
                        </a>
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
                    <p>Here's a snapshot of your budgets, goals, and upcoming bills for this month.</p>
                </div>
                <button class="btn btn-add"><i class="fas fa-plus mr-2"></i>Add Expense</button>
            </div>

            <!-- Stat Cards -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Total Balance</span>
                        <span class="stat-icon"><i class="fas fa-wallet"></i></span>
                    </div>
                    <div class="stat-value">$4,280.50</div>
                    <span class="stat-delta"><i class="fas fa-arrow-up"></i> 3.2% this month</span>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Monthly Budget</span>
                        <span class="stat-icon"><i class="fas fa-chart-pie"></i></span>
                    </div>
                    <div class="stat-value">$1,850.00</div>
                    <span class="stat-delta down"><i class="fas fa-arrow-down"></i> 62% used</span>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Savings Goal</span>
                        <span class="stat-icon"><i class="fas fa-bullseye"></i></span>
                    </div>
                    <div class="stat-value">$920.00</div>
                    <span class="stat-delta"><i class="fas fa-arrow-up"></i> 46% of $2,000</span>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Upcoming Bills</span>
                        <span class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                    </div>
                    <div class="stat-value">$340.00</div>
                    <span class="stat-delta down"><i class="fas fa-clock"></i> Due in 5 days</span>
                </div>
            </div>

            <!-- Panels -->
            <div class="panel-row">
                <div class="panel">
                    <div class="panel-header">
                        <h2>Spending Overview</h2>
                        <a href="budgetchart.php">View report</a>
                    </div>
                    <div class="placeholder-chart">Spending chart will appear here</div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Recent Transactions</h2>
                        <a href="expense.php">View all</a>
                    </div>
                    <ul class="tx-list">
                        <li>
                            <div class="tx-left">
                                <span class="tx-icon"><i class="fas fa-utensils"></i></span>
                                <div>
                                    <div class="tx-name">Grocery Store</div>
                                    <div class="tx-date">Today, 10:24 AM</div>
                                </div>
                            </div>
                            <span class="tx-amount neg">-$64.20</span>
                        </li>
                        <li>
                            <div class="tx-left">
                                <span class="tx-icon"><i class="fas fa-briefcase"></i></span>
                                <div>
                                    <div class="tx-name">Salary Deposit</div>
                                    <div class="tx-date">Yesterday, 9:00 AM</div>
                                </div>
                            </div>
                            <span class="tx-amount pos">+$2,400.00</span>
                        </li>
                        <li>
                            <div class="tx-left">
                                <span class="tx-icon"><i class="fas fa-bolt"></i></span>
                                <div>
                                    <div class="tx-name">Electricity Bill</div>
                                    <div class="tx-date">Sep 1, 6:12 PM</div>
                                </div>
                            </div>
                            <span class="tx-amount neg">-$88.40</span>
                        </li>
                        <li>
                            <div class="tx-left">
                                <span class="tx-icon"><i class="fas fa-hand-holding-heart"></i></span>
                                <div>
                                    <div class="tx-name">Charity Donation</div>
                                    <div class="tx-date">Aug 29, 2:45 PM</div>
                                </div>
                            </div>
                            <span class="tx-amount neg">-$25.00</span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
            });
        });
    </script>
</body>

</html>