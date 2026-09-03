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
$stmt->close();

$goalData = [];
$goalStmt = null;

if ($user) {
    $userID = $user['userID'];

    // Fetch goals data based on the userID
    $goalQuery = "SELECT goalID, goalName, TargetAmount, current_amount, Startdate, end_date, status FROM Goals WHERE userID = ?";
    $goalStmt = $db->prepare($goalQuery);
    $goalStmt->bind_param("i", $userID);
    $goalStmt->execute();
    $goalData = $goalStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $goalStmt->close();
} else {
    // Handle the case where the user is not found
    $goalData = [];
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketBuddy | Goal Progress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        .container {
            width: 100%;
            max-width: 640px;
        }

        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(166, 67, 44, 0.12);
        }

        .card-header {
            position: relative;
            color: #fff;
            background: linear-gradient(150deg, var(--terracotta) 0%, var(--rust) 100%);
            padding: 24px 28px;
        }

        .card-header::after {
            content: "";
            position: absolute;
            right: -40px;
            top: -40px;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }

        .card-header .icon-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255,255,255,0.16);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .card-header h2 {
            font-family: 'Fraunces', serif;
            font-size: 20px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .card-header p {
            font-size: 13px;
            color: rgba(255,255,255,0.85);
            margin-top: 4px;
            position: relative;
            z-index: 1;
        }

        .close-btn {
            position: absolute;
            top: 18px;
            right: 20px;
            font-size: 20px;
            line-height: 1;
            color: #fff;
            background: rgba(255,255,255,0.14);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            z-index: 2;
        }

        .close-btn:hover {
            background: rgba(255,255,255,0.28);
            transform: scale(1.06);
        }

        .card-body {
            padding: 26px 28px 30px;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            text-align: left;
            margin-bottom: 6px;
            font-size: 13px;
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

        select {
            width: 100%;
            padding: 10px 14px 10px 34px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            border-radius: 9px;
            border: 1.5px solid var(--line);
            background: var(--cream);
            color: var(--ink);
            outline: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237A6E68'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s;
        }

        select:focus {
            border-color: var(--terracotta);
            box-shadow: 0 0 0 3px rgba(193, 90, 62, 0.1);
            background-color: #fff;
        }

        .chart-area {
            min-height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-state {
            text-align: center;
            padding: 40px 10px;
            color: var(--ink-soft);
        }

        .empty-state i {
            font-size: 30px;
            color: var(--terracotta-light);
            margin-bottom: 12px;
            display: block;
        }

        .goal-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }

        .goal-meta .metric {
            text-align: center;
            flex: 1;
        }

        .goal-meta .metric .val {
            font-family: 'Fraunces', serif;
            font-size: 17px;
            font-weight: 600;
        }

        .goal-meta .metric .lbl {
            font-size: 11.5px;
            color: var(--ink-soft);
            margin-top: 2px;
        }

        .goal-meta .metric.remaining .val {
            color: var(--terracotta);
        }

        .goal-meta .metric.saved .val {
            color: #2f8b4a;
        }

        /* ===== Animations ===== */
        .card {
            opacity: 0;
            transform: translateY(14px);
            animation: cardIn 0.5s ease forwards;
        }

        @keyframes cardIn {
            to { opacity: 1; transform: translateY(0); }
        }

        .chart-area {
            position: relative;
        }

        .chart-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.85);
            text-align: center;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.4s ease 0.5s, transform 0.4s ease 0.5s;
        }

        .chart-center.show {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        .chart-center .pct {
            font-family: 'Fraunces', serif;
            font-size: 26px;
            font-weight: 600;
            color: var(--ink);
        }

        .chart-center .pct-label {
            font-size: 11px;
            color: var(--ink-soft);
            letter-spacing: 0.03em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .goal-meta .metric {
            transition: transform 0.2s ease;
        }

        .goal-meta .metric:hover {
            transform: translateY(-2px);
        }

        #goalSelect {
            transition: transform 0.15s ease;
        }
    </style>
    <script>
        function closePage() {
            window.location.href = 'index.php';
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <button class="close-btn" onclick="closePage()">&times;</button>
                <div class="icon-badge"><i class="fas fa-chart-pie"></i></div>
                <h2>Goal Progress</h2>
                <p>Select a goal to see how close you are to reaching it</p>
            </div>

            <div class="card-body">
                <div class="input-group">
                    <label for="goalSelect">Select Goal</label>
                    <div class="field-wrap">
                        <i class="fas fa-bullseye"></i>
                        <select id="goalSelect" onchange="updateChart()">
                            <option value="">Select a Goal</option>
                            <?php
                            if (!empty($goalData)) {
                                foreach ($goalData as $goal) {
                                    echo '<option value="' . htmlspecialchars($goal['goalID'], ENT_QUOTES, 'UTF-8') . '">'
                                        . htmlspecialchars($goal['goalName'], ENT_QUOTES, 'UTF-8')
                                        . '</option>';
                                }
                            } else {
                                echo '<option value="">No goals found</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="chart-area" id="chartArea">
                    <div class="empty-state" id="emptyState">
                        <i class="fas fa-bullseye"></i>
                        <p>Select a goal above to view its progress chart.</p>
                    </div>
                    <canvas id="goalChart" style="display:none;"></canvas>
                    <div class="chart-center" id="chartCenter">
                        <div class="pct" id="chartPct">0%</div>
                        <div class="pct-label">Saved</div>
                    </div>
                </div>

                <div class="goal-meta" id="goalMeta" style="display:none;">
                    <div class="metric saved">
                        <div class="val" id="savedVal">$0.00</div>
                        <div class="lbl">Saved</div>
                    </div>
                    <div class="metric remaining">
                        <div class="val" id="remainingVal">$0.00</div>
                        <div class="lbl">Remaining</div>
                    </div>
                    <div class="metric">
                        <div class="val" id="targetVal">$0.00</div>
                        <div class="lbl">Target</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pass all the goal data to JavaScript as JSON
        const goals = <?php echo json_encode($goalData); ?>;
        let chart; // Reference to the chart object

        // Animate a number from 0 (or its previous value) up to a target value
        function countUpTo(el, target, prefix = '$', duration = 900) {
            const start = 0;
            const startTime = performance.now();

            function tick(now) {
                const progress = Math.min((now - startTime) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
                const value = start + (target - start) * eased;
                el.textContent = prefix + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }

        function countUpPercent(el, target, duration = 900) {
            const startTime = performance.now();
            function tick(now) {
                const progress = Math.min((now - startTime) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(target * eased) + '%';
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }

        function updateChart() {
            const selectedGoalID = document.getElementById('goalSelect').value;
            const canvas = document.getElementById('goalChart');
            const emptyState = document.getElementById('emptyState');
            const goalMeta = document.getElementById('goalMeta');
            const chartCenter = document.getElementById('chartCenter');

            if (!selectedGoalID) {
                if (chart) chart.destroy();
                canvas.style.display = 'none';
                emptyState.style.display = 'block';
                goalMeta.style.display = 'none';
                chartCenter.classList.remove('show');
                return;
            }

            const selectedGoal = goals.find(goal => goal.goalID == selectedGoalID);
            if (!selectedGoal) {
                return;
            }

            const targetAmount = parseFloat(selectedGoal.TargetAmount);
            const currentAmount = parseFloat(selectedGoal.current_amount);
            const remainingAmount = Math.max(targetAmount - currentAmount, 0);
            const percent = targetAmount > 0 ? Math.min((currentAmount / targetAmount) * 100, 100) : 0;

            countUpTo(document.getElementById('savedVal'), currentAmount);
            countUpTo(document.getElementById('remainingVal'), remainingAmount);
            countUpTo(document.getElementById('targetVal'), targetAmount);
            goalMeta.style.display = 'flex';

            emptyState.style.display = 'none';
            canvas.style.display = 'block';
            chartCenter.classList.remove('show');

            const chartData = {
                labels: ['Saved', 'Remaining'],
                datasets: [{
                    data: [currentAmount, remainingAmount],
                    backgroundColor: ['#C15A3E', '#ECE0DB'],
                    hoverBackgroundColor: ['#A6432C', '#DED0C9'],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            };

            if (chart) chart.destroy();

            const ctx = canvas.getContext('2d');
            chart = new Chart(ctx, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 1100,
                        easing: 'easeOutQuart',
                        delay: (context) => context.type === 'data' ? context.dataIndex * 180 : 0,
                        onComplete: () => {
                            chartCenter.classList.add('show');
                            countUpPercent(document.getElementById('chartPct'), Math.round(percent));
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        intersect: true
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { family: 'Poppins', size: 12 },
                                color: '#2B2320',
                                padding: 16,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Goal: ' + selectedGoal.goalName,
                            font: { family: 'Fraunces', size: 15, weight: '600' },
                            color: '#2B2320',
                            padding: { bottom: 14 }
                        },
                        tooltip: {
                            backgroundColor: '#201512',
                            titleColor: '#fff',
                            bodyColor: '#E7DDD8',
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: function (context) {
                                    return context.label + ': $' + context.parsed.toLocaleString(undefined, { minimumFractionDigits: 2 });
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>