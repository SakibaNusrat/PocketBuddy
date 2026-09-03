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
$stmt->close();

if (!$user) {
    // No matching user found for this session — force re-login instead of crashing
    session_destroy();
    header("Location: login.php");
    exit();
}

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
    // Types must match column order: userID(i), Month(s), BudgetYear(i), BudgetName(s), Amount(d), IsActive(i)
    $stmt->bind_param("isisdi", $userID, $month, $budgetyear, $budgetname, $amount, $is_active);
    if ($stmt->execute()) {
        $success_message = "Budget added successfully!";
        echo "<script>showToast('$success_message', 'success');</script>";
    } else {
        $error_message = "Error adding Budget: " . $stmt->error;
        echo "<script>showToast('$error_message', 'error');</script>";
    }

    $stmt->close();
}

// Fetch all budgets for the current user (now actually displayed below)
$budgetQuery = "SELECT * FROM Budget WHERE userID = ? ORDER BY BudgetYear DESC, FIELD(Month,'January','February','March','April','May','June','July','August','September','October','November','December') DESC";
$stmt = $db->prepare($budgetQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$budgets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalBudgeted = 0;
foreach ($budgets as $b) {
    $totalBudgeted += (float) $b['Amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketBuddy | Add Budget</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 15px;
        }

        .shell {
            width: 100%;
            max-width: 920px;
            min-height: 560px;
            background: #fff;
            border-radius: 22px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(166, 67, 44, 0.15);
            opacity: 0;
            transform: translateY(14px);
            animation: shellIn 0.5s ease forwards;
        }

        @keyframes shellIn {
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== LEFT: existing budgets panel ===== */
        .preview-panel {
            flex: 0.85;
            background: linear-gradient(165deg, #241713 0%, #3A241D 55%, var(--rust) 100%);
            color: #fff;
            padding: 36px 30px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .preview-panel .top-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .preview-panel .close-btn {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            border: none;
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .preview-panel .close-btn:hover {
            background: rgba(255,255,255,0.22);
        }

        .preview-panel .badge {
            font-size: 11px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
        }

        .preview-panel .icon-ring {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin: 22px 0 14px;
            color: var(--terracotta-light);
        }

        .preview-panel h1 {
            font-family: 'Fraunces', serif;
            font-size: 22px;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 6px;
        }

        .preview-panel p.lead {
            font-size: 13px;
            color: rgba(255,255,255,0.65);
            line-height: 1.6;
        }

        .total-strip {
            display: flex;
            gap: 22px;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid rgba(255,255,255,0.14);
        }

        .total-strip .item .num {
            font-family: 'Fraunces', serif;
            font-size: 19px;
            font-weight: 600;
        }

        .total-strip .item .lbl {
            font-size: 10.5px;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }

        .budget-list {
            margin-top: 20px;
            overflow-y: auto;
            flex: 1;
            padding-right: 4px;
        }

        .budget-list::-webkit-scrollbar {
            width: 5px;
        }

        .budget-list::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
        }

        .budget-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.06);
            margin-bottom: 8px;
            font-size: 12.5px;
        }

        .budget-row .b-left {
            display: flex;
            flex-direction: column;
        }

        .budget-row .b-name {
            font-weight: 600;
            color: #fff;
        }

        .budget-row .b-period {
            color: rgba(255,255,255,0.55);
            font-size: 11px;
            margin-top: 1px;
        }

        .budget-row .b-amount {
            font-weight: 600;
            color: var(--terracotta-light);
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .status-dot.active { background: #5FCB7B; }
        .status-dot.inactive { background: rgba(255,255,255,0.35); }

        .empty-budgets {
            font-size: 12.5px;
            color: rgba(255,255,255,0.55);
            padding: 16px 4px;
        }

        /* ===== RIGHT: form panel ===== */
        .form-panel {
            flex: 1.15;
            padding: 40px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
        }

        .form-panel .form-title {
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--terracotta);
            margin-bottom: 6px;
        }

        .form-panel .form-sub {
            font-size: 13px;
            color: var(--ink-soft);
            margin-bottom: 26px;
        }

        .input-group {
            margin-bottom: 16px;
        }

        .input-row {
            display: flex;
            gap: 14px;
        }

        .input-row .input-group {
            flex: 1;
        }

        .input-group label {
            display: block;
            text-align: left;
            margin-bottom: 6px;
            font-size: 12.5px;
            font-weight: 600;
            color: #4a4a4a;
        }

        .input-group .field-wrap {
            position: relative;
        }

        .input-group .field-wrap i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12.5px;
            color: var(--terracotta-light);
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 10px 14px 10px 34px;
            font-size: 13.5px;
            font-family: 'Poppins', sans-serif;
            border-radius: 9px;
            border: 1.5px solid var(--line);
            background: #fff;
            color: var(--ink);
            outline: none;
            appearance: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-group select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237A6E68'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
        }

        .input-group input:focus,
        .input-group select:focus {
            border-color: var(--terracotta);
            box-shadow: 0 0 0 3px rgba(193, 90, 62, 0.1);
        }

        .input-group input::placeholder {
            color: #b7aca6;
        }

        .add-budget-btn {
            width: 100%;
            border: none;
            padding: 13px 20px;
            font-size: 14.5px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #D97A55, #A6432C);
            border-radius: 10px;
            cursor: pointer;
            margin-top: 6px;
            box-shadow: 0 10px 24px rgba(166, 67, 44, 0.28);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .add-budget-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(166, 67, 44, 0.38);
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            min-width: 260px;
            max-width: 320px;
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            color: #fff;
            display: flex;
            align-items: center;
            box-shadow: 0 12px 28px rgba(43, 35, 32, 0.18);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.4s ease;
        }

        .toast.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .toast.success {
            background: linear-gradient(135deg, #4CAF6D, #2f8b4a);
        }

        .toast.error {
            background: linear-gradient(135deg, var(--terracotta-light), var(--rust));
        }

        .toast .close-btn {
            margin-left: auto;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            color: #fff;
            background: none;
            border: none;
        }

        .toast .close-btn:hover {
            color: #f0f0f0;
        }

        @media (max-width: 820px) {
            .shell {
                flex-direction: column;
                min-height: unset;
            }
            .preview-panel {
                padding: 28px;
                max-height: 280px;
            }
            .form-panel {
                padding: 28px;
            }
        }

        @media (max-width: 480px) {
            .input-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
    <script>
        function closePage() {
            window.location.href = 'index.php';
        }
    </script>
</head>
<body>
    <div class="shell">

        <!-- LEFT: Existing budgets panel -->
        <div class="preview-panel">
            <div class="top-nav">
                <span class="badge">PocketBuddy</span>
                <button class="close-btn" onclick="closePage()">&times;</button>
            </div>

            <div class="icon-ring"><i class="fas fa-wallet"></i></div>
            <h1>Your Budgets</h1>
            <p class="lead">Everything you've set up so far, newest first.</p>

            <div class="total-strip">
                <div class="item">
                    <div class="num">$<?php echo number_format($totalBudgeted, 2); ?></div>
                    <div class="lbl">Total Budgeted</div>
                </div>
                <div class="item">
                    <div class="num"><?php echo count($budgets); ?></div>
                    <div class="lbl">Budgets Created</div>
                </div>
            </div>

            <div class="budget-list">
                <?php if (!empty($budgets)): ?>
                    <?php foreach ($budgets as $b): ?>
                        <div class="budget-row">
                            <div class="b-left">
                                <span class="b-name">
                                    <span class="status-dot <?php echo $b['IsActive'] ? 'active' : 'inactive'; ?>"></span>
                                    <?php echo htmlspecialchars($b['BudgetName'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="b-period"><?php echo htmlspecialchars($b['Month'], ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($b['BudgetYear'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <span class="b-amount">$<?php echo number_format($b['Amount'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-budgets">No budgets yet — add your first one on the right.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: Form panel -->
        <div class="form-panel">
            <div class="form-title">New Budget</div>
            <div class="form-sub">Fill in the details below to create a monthly budget.</div>

            <form method="POST">
                <div class="input-row">
                    <div class="input-group">
                        <label for="month">Month</label>
                        <div class="field-wrap">
                            <i class="fas fa-calendar-alt"></i>
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
                    </div>

                    <div class="input-group">
                        <label for="budgetyear">Budget Year</label>
                        <div class="field-wrap">
                            <i class="fas fa-clock"></i>
                            <input type="number" id="budgetyear" name="budgetyear" required placeholder="e.g., 2026">
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label for="budgetname">Budget Name</label>
                    <div class="field-wrap">
                        <i class="fas fa-tag"></i>
                        <input type="text" id="budgetname" name="budgetname" required placeholder="e.g., Personal Budget">
                    </div>
                </div>

                <div class="input-row">
                    <div class="input-group">
                        <label for="amount">Amount</label>
                        <div class="field-wrap">
                            <i class="fas fa-dollar-sign"></i>
                            <input type="number" step="0.01" id="amount" name="amount" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="is_active">Status</label>
                        <div class="field-wrap">
                            <i class="fas fa-toggle-on"></i>
                            <select id="is_active" name="is_active" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" name="add_budget" class="add-budget-btn">Add Budget</button>
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
                <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }

        <?php if (isset($success_message)): ?>
            showToast('<?php echo $success_message; ?>', 'success');
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            showToast('<?php echo $error_message; ?>', 'error');
        <?php endif; ?>
    </script>
</body>
</html>