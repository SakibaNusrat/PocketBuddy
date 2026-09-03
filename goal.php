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

// Fetch existing / unfinished Goals (current_amount < TargetAmount) for this user
$goalsQuery = "SELECT GoalID, goalName, Description, TargetAmount, current_amount, StartDate 
               FROM Goals 
               WHERE userID = ? AND current_amount < TargetAmount
               ORDER BY StartDate DESC";
$stmt = $db->prepare($goalsQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$goalsResult = $stmt->get_result();

$existingGoals = [];
if ($goalsResult && $goalsResult->num_rows > 0) {
    while ($row = $goalsResult->fetch_assoc()) {
        $existingGoals[] = $row;
    }
}

// ===== Add NEW Goal Logic =====
if (isset($_POST['add_goal'])) {
    $goalName = $_POST['goal_name'];
    $description = !empty($_POST['description']) ? $_POST['description'] : "";
    $targetAmount = $_POST['target_amount'];
    $currentAmount = !empty($_POST['current_amount']) ? $_POST['current_amount'] : 0;
    $startDate = $_POST['start_date'];
    $budgetID = $_POST['Month'];

    $query = "INSERT INTO Goals (userID, goalName, Description, TargetAmount, current_amount, StartDate, budgetID) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("issddsi", $userID, $goalName, $description, $targetAmount, $currentAmount, $startDate, $budgetID);

    if ($stmt->execute()) {
        $success_message = "Goal added successfully!";
        echo "<script>showToast('$success_message', 'success');</script>";
    } else {
        $error_message = "Error adding Goal: " . $stmt->error;
        echo "<script>showToast('$error_message', 'error');</script>";
    }
    $stmt->close();

    // Refresh goals list after insert
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ===== Allocate money to an EXISTING Goal =====
if (isset($_POST['allocate_goal'])) {
    $goalID = intval($_POST['goal_id']);
    $allocateAmount = floatval($_POST['allocate_amount']);

    // Make sure the goal belongs to this user
    $checkQuery = "SELECT current_amount, TargetAmount FROM Goals WHERE GoalID = ? AND userID = ?";
    $stmt = $db->prepare($checkQuery);
    $stmt->bind_param("ii", $goalID, $userID);
    $stmt->execute();
    $checkResult = $stmt->get_result();
    $goalRow = $checkResult->fetch_assoc();
    $stmt->close();

    if ($goalRow) {
        $newAmount = $goalRow['current_amount'] + $allocateAmount;
        // Don't let it exceed the target
        if ($newAmount > $goalRow['TargetAmount']) {
            $newAmount = $goalRow['TargetAmount'];
        }

        $updateQuery = "UPDATE Goals SET current_amount = ? WHERE GoalID = ? AND userID = ?";
        $stmt = $db->prepare($updateQuery);
        $stmt->bind_param("dii", $newAmount, $goalID, $userID);

        if ($stmt->execute()) {
            $success_message = "Amount allocated to your goal successfully!";
            echo "<script>showToast('$success_message', 'success');</script>";
        } else {
            $error_message = "Error allocating amount: " . $stmt->error;
            echo "<script>showToast('$error_message', 'error');</script>";
        }
        $stmt->close();
    } else {
        $error_message = "Goal not found.";
        echo "<script>showToast('$error_message', 'error');</script>";
    }

    // Refresh goals list after update
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketBuddy | Add Goal</title>
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
            max-width: 960px;
            min-height: 560px;
            background: #fff;
            border-radius: 22px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(166, 67, 44, 0.15);
        }

        /* ===== LEFT: preview / info panel ===== */
        .preview-panel {
            flex: 0.85;
            background: linear-gradient(165deg, #241713 0%, #3A241D 55%, var(--rust) 100%);
            color: #fff;
            padding: 40px 34px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
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

        .preview-panel .heading {
            margin-top: 34px;
        }

        .preview-panel .icon-ring {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 18px;
            color: var(--terracotta-light);
        }

        .preview-panel h1 {
            font-family: 'Fraunces', serif;
            font-size: 28px;
            font-weight: 600;
            line-height: 1.25;
            margin-bottom: 10px;
        }

        .preview-panel p.lead {
            font-size: 13.5px;
            color: rgba(255,255,255,0.7);
            line-height: 1.6;
            max-width: 260px;
        }

        .checklist {
            list-style: none;
            margin-top: 30px;
        }

        .checklist li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: rgba(255,255,255,0.75);
            margin-bottom: 14px;
        }

        .checklist li i {
            color: var(--terracotta-light);
            margin-top: 2px;
            font-size: 12px;
        }

        .preview-panel .stat-strip {
            display: flex;
            gap: 22px;
            border-top: 1px solid rgba(255,255,255,0.14);
            padding-top: 20px;
        }

        .stat-strip .item .num {
            font-family: 'Fraunces', serif;
            font-size: 20px;
            font-weight: 600;
        }

        .stat-strip .item .lbl {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }

        /* ===== RIGHT: form panel ===== */
        .form-panel {
            flex: 1.15;
            padding: 36px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
            max-height: 100vh;
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
            margin-bottom: 20px;
        }

        /* ===== Tabs ===== */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 22px;
            background: var(--cream);
            padding: 5px;
            border-radius: 10px;
        }

        .tab-btn {
            flex: 1;
            padding: 9px 10px;
            border: none;
            background: transparent;
            border-radius: 7px;
            font-family: 'Poppins', sans-serif;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink-soft);
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn.active {
            background: #fff;
            color: var(--rust);
            box-shadow: 0 4px 10px rgba(166, 67, 44, 0.12);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-group textarea {
            min-height: 64px;
            resize: vertical;
            padding-left: 14px;
        }

        .input-group select {
            appearance: none;
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

        .add-goal-btn {
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

        .add-goal-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(166, 67, 44, 0.38);
        }

        /* ===== Existing Goals list ===== */
        .goal-list {
            max-height: 220px;
            overflow-y: auto;
            margin-bottom: 18px;
            border: 1.5px solid var(--line);
            border-radius: 10px;
            padding: 6px;
        }

        .goal-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s;
        }

        .goal-item:hover {
            background: var(--cream);
        }

        .goal-item.selected {
            background: rgba(193, 90, 62, 0.08);
            border: 1px solid var(--terracotta-light);
        }

        .goal-item input[type="radio"] {
            width: auto;
            accent-color: var(--rust);
        }

        .goal-item .goal-info {
            flex: 1;
        }

        .goal-item .goal-info .name {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }

        .goal-item .goal-info .progress-text {
            font-size: 11.5px;
            color: var(--ink-soft);
            margin-top: 2px;
        }

        .goal-item .progress-bar {
            width: 100%;
            height: 5px;
            background: var(--line);
            border-radius: 4px;
            margin-top: 5px;
            overflow: hidden;
        }

        .goal-item .progress-bar .fill {
            height: 100%;
            background: linear-gradient(135deg, #D97A55, #A6432C);
        }

        .no-goals {
            font-size: 12.5px;
            color: var(--ink-soft);
            text-align: center;
            padding: 18px 10px;
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
                padding: 30px;
            }
            .checklist {
                display: none;
            }
            .form-panel {
                padding: 30px;
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
            window.location.href = 'index.php'; // Change this to the desired URL
        }

        function switchTab(tab) {
            document.getElementById('tab-new').classList.remove('active');
            document.getElementById('tab-existing').classList.remove('active');
            document.getElementById('content-new').classList.remove('active');
            document.getElementById('content-existing').classList.remove('active');

            document.getElementById('tab-' + tab).classList.add('active');
            document.getElementById('content-' + tab).classList.add('active');
        }

        function selectGoal(el, goalID) {
            document.querySelectorAll('.goal-item').forEach(i => i.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('goal_id_input').value = goalID;
            el.querySelector('input[type="radio"]').checked = true;
        }
    </script>
</head>
<body>
    <div class="shell">

        <!-- LEFT: Preview / context panel -->
        <div class="preview-panel">
            <div>
                <div class="top-nav">
                    <span class="badge">PocketBuddy</span>
                    <button class="close-btn" onclick="closePage()">&times;</button>
                </div>

                <div class="heading">
                    <div class="icon-ring"><i class="fas fa-bullseye"></i></div>
                    <h1>Turn a plan into a goal you can track</h1>
                    <p class="lead">Create a new goal, or top up one you already started, right from your budget.</p>
                </div>

                <ul class="checklist">
                    <li><i class="fas fa-check-circle"></i> Link a new goal to an existing budget month</li>
                    <li><i class="fas fa-check-circle"></i> Or allocate more money to a goal in progress</li>
                    <li><i class="fas fa-check-circle"></i> Track progress from your dashboard</li>
                </ul>
            </div>

            <div class="stat-strip">
                <div class="item">
                    <div class="num"><?php echo count($budgetData); ?></div>
                    <div class="lbl">Budgets Available</div>
                </div>
                <div class="item">
                    <div class="num"><?php echo count($existingGoals); ?></div>
                    <div class="lbl">Goals In Progress</div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Form panel -->
        <div class="form-panel">
            <div class="form-title">Goals</div>
            <div class="form-sub">Create a brand new goal, or allocate funds to one you've already set up.</div>

            <!-- Success or Error Message -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button type="button" id="tab-new" class="tab-btn active" onclick="switchTab('new')">New Goal</button>
                <button type="button" id="tab-existing" class="tab-btn" onclick="switchTab('existing')">Add to Existing Goal</button>
            </div>

            <!-- ===== NEW GOAL FORM ===== -->
            <div id="content-new" class="tab-content active">
                <form method="POST">
                    <div class="input-group">
                        <label for="goal_name">Goal Name</label>
                        <div class="field-wrap">
                            <i class="fas fa-flag"></i>
                            <input type="text" id="goal_name" name="goal_name" placeholder="e.g. Emergency Fund" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Add an optional note about this goal"></textarea>
                    </div>

                    <div class="input-row">
                        <div class="input-group">
                            <label for="target_amount">Target Amount</label>
                            <div class="field-wrap">
                                <i class="fas fa-dollar-sign"></i>
                                <input type="number" step="0.01" id="target_amount" name="target_amount" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="input-group">
                            <label for="current_amount">Current Amount</label>
                            <div class="field-wrap">
                                <i class="fas fa-coins"></i>
                                <input type="number" step="0.01" id="current_amount" name="current_amount" placeholder="0.00" value="">
                            </div>
                        </div>
                    </div>

                    <div class="input-row">
                        <div class="input-group">
                            <label for="start_date">Start Date</label>
                            <div class="field-wrap">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" id="start_date" name="start_date" required>
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
                    </div>

                    <button type="submit" name="add_goal" class="add-goal-btn">Add Goal</button>
                </form>
            </div>

            <!-- ===== ALLOCATE TO EXISTING GOAL FORM ===== -->
            <div id="content-existing" class="tab-content">
                <form method="POST">
                    <div class="input-group">
                        <label>Select a Goal</label>
                        <div class="goal-list">
                            <?php if (!empty($existingGoals)): ?>
                                <?php foreach ($existingGoals as $goal):
                                    $pct = $goal['TargetAmount'] > 0
                                        ? min(100, round(($goal['current_amount'] / $goal['TargetAmount']) * 100))
                                        : 0;
                                ?>
                                    <div class="goal-item" onclick="selectGoal(this, <?php echo (int)$goal['GoalID']; ?>)">
                                        <input type="radio" name="goal_id_radio" value="<?php echo (int)$goal['GoalID']; ?>">
                                        <div class="goal-info">
                                            <div class="name"><?php echo htmlspecialchars($goal['goalName'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="progress-text">
                                                $<?php echo number_format($goal['current_amount'], 2); ?> of $<?php echo number_format($goal['TargetAmount'], 2); ?> (<?php echo $pct; ?>%)
                                            </div>
                                            <div class="progress-bar"><div class="fill" style="width: <?php echo $pct; ?>%;"></div></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-goals">You don't have any unfinished goals yet. Create one first from the "New Goal" tab.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <input type="hidden" id="goal_id_input" name="goal_id" value="">

                    <div class="input-group">
                        <label for="allocate_amount">Amount to Add</label>
                        <div class="field-wrap">
                            <i class="fas fa-dollar-sign"></i>
                            <input type="number" step="0.01" min="0.01" id="allocate_amount" name="allocate_amount" placeholder="0.00" required>
                        </div>
                    </div>

                    <button type="submit" name="allocate_goal" class="add-goal-btn" <?php echo empty($existingGoals) ? 'disabled' : ''; ?>>Allocate to Goal</button>
                </form>
            </div>
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