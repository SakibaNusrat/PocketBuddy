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

// Fetch Budget Data
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
$stmt->close();

// Add Bill Logic
if (isset($_POST['add_bill'])) {
    $category = $_POST['bill_category'];
    $description = $_POST['bill_description'];
    $amount = $_POST['bill_amount'];
    $dueDate = $_POST['bill_due_date'];
    $budgetID = $_POST['Month']; // Using the selected Budget ID from the form

    // Fetch the month and year based on selected budgetID
    $checkBudgetQuery = "SELECT Month, budgetyear FROM budget WHERE BudgetID = ?";
    $checkStmt = $db->prepare($checkBudgetQuery);
    $checkStmt->bind_param("i", $budgetID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // Insert the bill with the correct budget month and year
        $query = "INSERT INTO Bills (userID, Category, Description, Amount, DueDate, BudgetID) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->bind_param("issdsi", $userID, $category, $description, $amount, $dueDate, $budgetID);
        if ($stmt->execute()) {
            $success_message = "Bill added successfully!";
            echo "<script>showToast('$success_message', 'success');</script>";
        } else {
            $error_message = "Error adding bill: " . $stmt->error;
            echo "<script>showToast('$error_message', 'error');</script>";
        }
        $stmt->close();
    } else {
        $error_message = "Invalid budget month selected.";
        echo "<script>showToast('$error_message', 'error');</script>";
    }
    $checkStmt->close();
}

// Fetch upcoming bills for this user, to actually show them (previously queried nowhere)
$recentQuery = "SELECT Category, Description, Amount, DueDate FROM Bills WHERE userID = ? ORDER BY DueDate ASC LIMIT 8";
$stmt = $db->prepare($recentQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$upcomingBills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalDue = 0;
foreach ($upcomingBills as $b) {
    $totalDue += (float) $b['Amount'];
}

$billIcons = [
    'Gas Bill' => 'fa-fire',
    'Electricity Bill' => 'fa-bolt',
    'Digital Bill' => 'fa-wifi',
    'Water Bill' => 'fa-droplet',
];
$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketBuddy | Add Bill</title>
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

        /* ===== LEFT: upcoming bills panel ===== */
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

        .bill-list {
            margin-top: 20px;
            overflow-y: auto;
            flex: 1;
            padding-right: 4px;
        }

        .bill-list::-webkit-scrollbar {
            width: 5px;
        }

        .bill-list::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
        }

        .bill-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.06);
            margin-bottom: 8px;
            font-size: 12.5px;
        }

        .bill-row.overdue {
            background: rgba(193, 90, 62, 0.28);
        }

        .bill-row .bl-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bill-row .bl-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11.5px;
            color: var(--terracotta-light);
        }

        .bill-row .bl-name {
            font-weight: 600;
            color: #fff;
        }

        .bill-row .bl-date {
            color: rgba(255,255,255,0.55);
            font-size: 11px;
            margin-top: 1px;
        }

        .bill-row .bl-date.overdue-text {
            color: #F2A88C;
        }

        .bill-row .bl-amount {
            font-weight: 600;
            color: var(--terracotta-light);
        }

        .empty-bills {
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
        .input-group select,
        .input-group textarea {
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

        .input-group textarea {
            min-height: 64px;
            resize: vertical;
            padding-left: 14px;
        }

        .input-group select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237A6E68'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
        }

        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            border-color: var(--terracotta);
            box-shadow: 0 0 0 3px rgba(193, 90, 62, 0.1);
        }

        .input-group input::placeholder,
        .input-group textarea::placeholder {
            color: #b7aca6;
        }

        .alert {
            margin-bottom: 16px;
            padding: 11px 14px;
            border-radius: 9px;
            font-size: 12.5px;
        }

        .alert-success {
            color: #2f6b3a;
            background: #e2f3e5;
            border: 1px solid #bfe3c5;
        }

        .alert-danger {
            color: #a13d24;
            background: #fbeae4;
            border: 1px solid #ecc3b4;
        }

        .add-bill-btn {
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

        .add-bill-btn:hover {
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

        <!-- LEFT: Upcoming bills panel -->
        <div class="preview-panel">
            <div class="top-nav">
                <span class="badge">PocketBuddy</span>
                <button class="close-btn" onclick="closePage()">&times;</button>
            </div>

            <div class="icon-ring"><i class="fas fa-file-invoice-dollar"></i></div>
            <h1>Upcoming Bills</h1>
            <p class="lead">Sorted by due date, soonest first.</p>

            <div class="total-strip">
                <div class="item">
                    <div class="num">$<?php echo number_format($totalDue, 2); ?></div>
                    <div class="lbl">Shown Total</div>
                </div>
                <div class="item">
                    <div class="num"><?php echo count($upcomingBills); ?></div>
                    <div class="lbl">Entries</div>
                </div>
            </div>

            <div class="bill-list">
                <?php if (!empty($upcomingBills)): ?>
                    <?php foreach ($upcomingBills as $b): ?>
                        <?php
                        $icon = $billIcons[$b['Category']] ?? 'fa-file-invoice';
                        $isOverdue = $b['DueDate'] < $today;
                        ?>
                        <div class="bill-row <?php echo $isOverdue ? 'overdue' : ''; ?>">
                            <div class="bl-left">
                                <span class="bl-icon"><i class="fas <?php echo $icon; ?>"></i></span>
                                <div>
                                    <div class="bl-name"><?php echo htmlspecialchars($b['Category'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="bl-date <?php echo $isOverdue ? 'overdue-text' : ''; ?>">
                                        <?php echo $isOverdue ? 'Overdue · ' : 'Due '; ?><?php echo htmlspecialchars($b['DueDate'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                            </div>
                            <span class="bl-amount">$<?php echo number_format($b['Amount'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-bills">No bills logged yet — add your first one on the right.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: Form panel -->
        <div class="form-panel">
            <div class="form-title">New Bill</div>
            <div class="form-sub">Log a recurring or one-off bill against a budget month.</div>

            <!-- Success or Error Message -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label for="bill_category">Category</label>
                    <div class="field-wrap">
                        <i class="fas fa-layer-group"></i>
                        <select id="bill_category" name="bill_category" required>
                            <option value="">Select Category</option>
                            <option value="Gas Bill">Gas Bill</option>
                            <option value="Electricity Bill">Electricity Bill</option>
                            <option value="Digital Bill">Digital Bill</option>
                            <option value="Water Bill">Water Bill</option>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <label for="bill_description">Description</label>
                    <textarea id="bill_description" name="bill_description" placeholder="Add an optional note about this bill"></textarea>
                </div>

                <div class="input-row">
                    <div class="input-group">
                        <label for="bill_amount">Amount</label>
                        <div class="field-wrap">
                            <i class="fas fa-dollar-sign"></i>
                            <input type="number" step="0.01" id="bill_amount" name="bill_amount" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="bill_due_date">Due Date</label>
                        <div class="field-wrap">
                            <i class="fas fa-calendar-alt"></i>
                            <input type="date" id="bill_due_date" name="bill_due_date" required>
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label for="budget_month">Budget Month</label>
                    <div class="field-wrap">
                        <i class="fas fa-wallet"></i>
                        <select id="budget_month" name="Month" required>
                            <option value="">Select Budget Month</option>
                            <?php
                            if (!empty($budgetData)) {
                                foreach ($budgetData as $budget) {
                                    echo '<option value="' . htmlspecialchars($budget['BudgetID'], ENT_QUOTES, 'UTF-8') . '">'
                                        . htmlspecialchars($budget['Month'], ENT_QUOTES, 'UTF-8') . ' '
                                        . htmlspecialchars($budget['budgetyear'], ENT_QUOTES, 'UTF-8')
                                        . '</option>';
                                }
                            } else {
                                echo '<option value="">No budgets found</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <button type="submit" name="add_bill" class="add-bill-btn">Add Bill</button>
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