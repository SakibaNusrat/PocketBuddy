<?php
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$db = mysqli_connect('localhost', 'root', '', 'pocketbuddy');
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}

$userEmail = $_SESSION['email'];
$stmt = $db->prepare("SELECT userID FROM USERS WHERE Email = ?");
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$userID = $user['userID'];
$stmt->close();

// Detect whether an optional StartDate column exists, so progress bars
// work if you've added one, and degrade gracefully if you haven't.
// Optional: ALTER TABLE SavingsChallenge ADD COLUMN StartDate DATE NULL;
$hasStartDate = $db->query("SHOW COLUMNS FROM SavingsChallenge LIKE 'StartDate'")->num_rows > 0;

$success_message = null;
$error_message   = null;

// ---------------------------------------------------------
// Add Challenge
// ---------------------------------------------------------
if (isset($_POST['add_challenge'])) {
    $challengeName = trim($_POST['challenge_name']);
    $description   = trim($_POST['description']);
    $duration      = (int) $_POST['duration'];
    $savingAmount  = (float) $_POST['saving_amount'];
    $status        = 'Pending';

    if ($challengeName === '' || $duration <= 0 || $savingAmount <= 0) {
        $error_message = "Please fill in every field correctly.";
    } else {
        if ($hasStartDate) {
            $query = "INSERT INTO SavingsChallenge (UserID, chllangeName, Description, Duration, savingAmount, Status, StartDate)
                       VALUES (?, ?, ?, ?, ?, ?, CURDATE())";
        } else {
            $query = "INSERT INTO SavingsChallenge (UserID, chllangeName, Description, Duration, savingAmount, Status)
                       VALUES (?, ?, ?, ?, ?, ?)";
        }
        $stmt = $db->prepare($query);
        $stmt->bind_param("issdis", $userID, $challengeName, $description, $duration, $savingAmount, $status);
        if ($stmt->execute()) {
            $success_message = "Challenge added successfully!";
        } else {
            $error_message = "Error adding challenge: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ---------------------------------------------------------
// Mark Complete / Delete
// ---------------------------------------------------------
if (isset($_POST['complete_challenge'])) {
    $id = (int) $_POST['ChallengeID'];
    $stmt = $db->prepare("UPDATE SavingsChallenge SET Status='Completed' WHERE ChallengeID = ? AND UserID = ?");
    $stmt->bind_param("ii", $id, $userID);
    $stmt->execute();
    $stmt->close();
    $success_message = "Nice work — challenge marked complete!";
}

if (isset($_POST['delete_challenge'])) {
    $id = (int) $_POST['ChallengeID'];
    $stmt = $db->prepare("DELETE FROM SavingsChallenge WHERE ChallengeID = ? AND UserID = ?");
    $stmt->bind_param("ii", $id, $userID);
    $stmt->execute();
    $stmt->close();
    $success_message = "Challenge removed.";
}

// ---------------------------------------------------------
// Fetch challenges
// ---------------------------------------------------------
$cols = $hasStartDate
    ? "ChallengeID, chllangeName, Description, Duration, savingAmount, Status, StartDate"
    : "ChallengeID, chllangeName, Description, Duration, savingAmount, Status";
$stmt = $db->prepare("SELECT $cols FROM SavingsChallenge WHERE UserID = ? ORDER BY FIELD(Status,'Active','Pending','Completed'), ChallengeID DESC");
$stmt->bind_param("i", $userID);
$stmt->execute();
$res = $stmt->get_result();

$challenges = [];
$completedCount = 0;
$totalSavingsTarget = 0;
while ($row = $res->fetch_assoc()) {
    if ($hasStartDate && $row['StartDate']) {
        $start = new DateTime($row['StartDate']);
        $today = new DateTime('today');
        $daysPassed = max(0, (int) $start->diff($today)->format('%a'));
        $row['percent'] = $row['Duration'] > 0 ? min(100, round(($daysPassed / $row['Duration']) * 100)) : 0;
        $row['daysLeft'] = max(0, $row['Duration'] - $daysPassed);
    } else {
        $row['percent'] = null;
        $row['daysLeft'] = null;
    }
    if ($row['Status'] === 'Completed') $completedCount++;
    $totalSavingsTarget += (float) $row['savingAmount'];
    $challenges[] = $row;
}
$stmt->close();
$challengeCount = count($challenges);

// ---------------------------------------------------------
// AI Coach: suggests a next challenge + a short tip.
// Uses the Anthropic API if ANTHROPIC_API_KEY is set in the
// server environment; otherwise falls back to a rule-based
// suggestion so the feature always works.
// ---------------------------------------------------------
function getAISuggestion($challengeCount, $completedCount, $challenges) {
    $avgAmount   = 0;
    $avgDuration = 0;
    if ($challengeCount > 0) {
        $sumAmount = $sumDuration = 0;
        foreach ($challenges as $c) {
            $sumAmount   += (float) $c['savingAmount'];
            $sumDuration += (int) $c['Duration'];
        }
        $avgAmount   = $sumAmount / $challengeCount;
        $avgDuration = $sumDuration / $challengeCount;
    }

    $fallbackAmount   = $avgAmount > 0 ? round($avgAmount * 1.15, -1) : 1000;
    $fallbackDuration = $avgDuration > 0 ? (int) round($avgDuration * 1.1) : 30;
    $fallbackName     = $fallbackDuration <= 14 ? "No-Spend Sprint" : ($fallbackDuration <= 45 ? "30-Day Save More Challenge" : "Long-Haul Savings Goal");
    $fallbackTip = $completedCount === 0
        ? "Start small — a short, low-pressure challenge builds the habit before you go big."
        : "You've finished {$completedCount} challenge(s) already. Try raising the target by about 15% this time.";

    $apiKey = getenv('ANTHROPIC_API_KEY');
    if (!$apiKey) {
        return [
            'tip' => $fallbackTip,
            'name' => $fallbackName,
            'amount' => $fallbackAmount,
            'duration' => $fallbackDuration,
            'source' => 'heuristic',
        ];
    }

    $prompt = "A user has completed {$completedCount} of {$challengeCount} savings challenges. "
            . "Their average past challenge was ৳{$avgAmount} over {$avgDuration} days. "
            . "Reply with ONLY a JSON object (no markdown, no prose) with keys: "
            . "tip (one encouraging sentence, under 20 words), name (short challenge name), "
            . "amount (a realistic number, no currency symbol), duration (integer days).";

    $payload = json_encode([
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 200,
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $data = json_decode($response, true);
        $text = $data['content'][0]['text'] ?? null;
        if ($text) {
            $clean = trim(str_replace(['```json', '```'], '', $text));
            $parsed = json_decode($clean, true);
            if (is_array($parsed) && isset($parsed['tip'], $parsed['name'], $parsed['amount'], $parsed['duration'])) {
                $parsed['source'] = 'ai';
                return $parsed;
            }
        }
    }

    return [
        'tip' => $fallbackTip,
        'name' => $fallbackName,
        'amount' => $fallbackAmount,
        'duration' => $fallbackDuration,
        'source' => 'heuristic',
    ];
}

$aiSuggestion = getAISuggestion($challengeCount, $completedCount, $challenges);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Savings Challenges · PocketBuddy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
        --maroon-deep: #2b0e0a;
        --maroon: #641e16;
        --maroon-light: #8a3327;
        --brick: #b5502f;
        --cream: #f8f6f2;
        --ink: #2b1a16;
        --muted: #8f7d76;
        --overdue: #c0392b;
        --due-soon: #c9762f;
        --ok: #3f7a5c;
        --border: #e7e0da;
    }

    body { font-family: 'Inter', system-ui, sans-serif; background: var(--cream); color: var(--ink); }
    h1, h2, h3, .brand { font-family: 'Fraunces', serif; }

    .layout {
        display: grid;
        grid-template-columns: minmax(300px, 38%) 1fr;
        max-width: 1100px;
        margin: 48px auto;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 30px 60px -25px rgba(43, 14, 10, 0.35);
    }

    /* ---------- LEFT: active challenges ---------- */
    .overview {
        background: linear-gradient(165deg, var(--maroon-deep) 0%, var(--maroon) 62%, var(--brick) 100%);
        color: #f2e9e4;
        padding: 40px 34px;
        display: flex;
        flex-direction: column;
        min-height: 640px;
    }

    .brand { font-size: 13px; letter-spacing: 0.06em; color: #d9b8ab; margin-bottom: 22px; }
    .overview h1 { font-size: 30px; font-weight: 600; margin-bottom: 6px; }
    .overview p.sub { font-size: 14px; color: #d9c0b6; margin-bottom: 22px; }

    .stats {
        display: flex; gap: 28px; padding-bottom: 20px;
        border-bottom: 1px solid rgba(255,255,255,0.15); margin-bottom: 20px;
    }
    .stats .num { font-size: 26px; font-weight: 600; font-family: 'Fraunces', serif; }
    .stats .label { font-size: 12px; color: #d9c0b6; margin-top: 2px; }

    .list { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding-right: 4px; }

    .card {
        background: rgba(255,255,255,0.07);
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 12px;
        padding: 14px 16px;
    }
    .card-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .c-name { font-weight: 600; font-size: 15px; }
    .c-desc { font-size: 12px; color: #d9c0b6; margin-top: 3px; }
    .c-amount { font-family: 'Fraunces', serif; font-size: 17px; font-weight: 600; }

    .bar-track {
        margin-top: 10px; height: 6px; border-radius: 4px;
        background: rgba(255,255,255,0.15); overflow: hidden;
    }
    .bar-fill { height: 100%; background: linear-gradient(90deg, var(--brick), #e0a37b); }

    .card-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }

    .pill { display: inline-block; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 999px; }
    .pill.Pending   { background: rgba(201, 118, 47, 0.9); color: #fff; }
    .pill.Active    { background: rgba(63, 122, 92, 0.9);  color: #fff; }
    .pill.Completed { background: rgba(255,255,255,0.2);   color: #fff; }

    .card-actions { display: flex; gap: 10px; }
    .link-btn {
        background: none; border: none; color: #e8c9bd; font-size: 12px;
        cursor: pointer; text-decoration: underline; padding: 0;
    }
    .link-btn:hover { color: #fff; }

    .empty-state { color: #d9c0b6; font-size: 14px; padding: 20px 0; }

    /* ---------- RIGHT: form + AI coach ---------- */
    .form-panel { background: #fff; padding: 44px 40px; }
    .form-eyebrow { color: var(--brick); font-weight: 600; font-size: 13px; margin-bottom: 6px; }
    .form-panel h2 { font-size: 24px; margin-bottom: 6px; }
    .form-panel > p.lede { color: var(--muted); font-size: 14px; margin-bottom: 22px; }

    .ai-card {
        background: #fbeee7;
        border: 1px solid #ecd3c4;
        border-radius: 12px;
        padding: 16px 18px;
        margin-bottom: 24px;
    }
    .ai-card .ai-label {
        font-size: 12px; font-weight: 700; color: var(--brick);
        margin-bottom: 6px; display: flex; align-items: center; gap: 6px;
    }
    .ai-card p { font-size: 14px; color: var(--ink); margin-bottom: 10px; }
    .ai-use-btn {
        background: none; border: 1px solid var(--brick); color: var(--brick);
        font-size: 13px; font-weight: 600; padding: 7px 14px; border-radius: 6px; cursor: pointer;
    }
    .ai-use-btn:hover { background: var(--brick); color: #fff; }

    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .field { margin-bottom: 16px; }
    .field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--ink); }
    .field input, .field textarea {
        width: 100%; padding: 10px 12px; font-size: 15px; border-radius: 8px;
        border: 1px solid var(--border); background: #fff; color: var(--ink); font-family: inherit;
    }
    .field input { height: 42px; }
    .field textarea { height: 70px; resize: vertical; }
    .field input:focus, .field textarea:focus {
        outline: none; border-color: var(--brick); box-shadow: 0 0 0 3px rgba(181, 80, 47, 0.15);
    }

    .btn {
        width: 100%; margin-top: 6px; padding: 13px; font-size: 15px; font-weight: 600;
        color: #fff; background: linear-gradient(120deg, var(--brick), var(--maroon-light));
        border: none; border-radius: 8px; cursor: pointer; transition: filter 0.2s ease;
    }
    .btn:hover { filter: brightness(1.08); }

    a.back-link { display: inline-block; margin-top: 18px; font-size: 13px; color: var(--muted); text-decoration: none; }
    a.back-link:hover { color: var(--brick); }

    .toast {
        position: fixed; bottom: 24px; right: 24px; min-width: 240px; max-width: 320px;
        padding: 14px 18px; border-radius: 8px; font-size: 14px; color: #fff;
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.18); z-index: 1000;
        opacity: 0; visibility: hidden; transform: translateY(12px); transition: all 0.4s ease;
    }
    .toast.show { opacity: 1; visibility: visible; transform: translateY(0); }
    .toast.success { background: var(--ok); }
    .toast.error   { background: var(--overdue); }
    .toast .close-btn { background: none; border: none; color: #fff; font-size: 16px; cursor: pointer; line-height: 1; }

    @media (max-width: 800px) {
        .layout { grid-template-columns: 1fr; margin: 0; border-radius: 0; }
        .row { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<div class="layout">

    <div class="overview">
        <div class="brand">POCKETBUDDY</div>
        <h1>Savings Challenges</h1>
        <p class="sub">Small goals, tracked until they're done.</p>

        <div class="stats">
            <div>
                <div class="num"><?php echo $challengeCount; ?></div>
                <div class="label">Total challenges</div>
            </div>
            <div>
                <div class="num"><?php echo $completedCount; ?></div>
                <div class="label">Completed</div>
            </div>
            <div>
                <div class="num">৳<?php echo number_format($totalSavingsTarget, 0); ?></div>
                <div class="label">Target, combined</div>
            </div>
        </div>

        <div class="list">
            <?php if (empty($challenges)): ?>
                <div class="empty-state">No challenges yet — add one on the right, or use the AI Coach suggestion to get started.</div>
            <?php else: ?>
                <?php foreach ($challenges as $c): ?>
                    <div class="card">
                        <div class="card-top">
                            <div>
                                <div class="c-name"><?php echo htmlspecialchars($c['chllangeName'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php if (!empty($c['Description'])): ?>
                                    <div class="c-desc"><?php echo htmlspecialchars($c['Description'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="c-amount">৳<?php echo number_format($c['savingAmount'], 0); ?></div>
                        </div>

                        <?php if ($c['percent'] !== null): ?>
                            <div class="bar-track"><div class="bar-fill" style="width: <?php echo $c['percent']; ?>%;"></div></div>
                        <?php endif; ?>

                        <div class="card-meta">
                            <span class="pill <?php echo htmlspecialchars($c['Status'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($c['Status'], ENT_QUOTES, 'UTF-8'); ?><?php echo $c['daysLeft'] !== null && $c['Status'] !== 'Completed' ? ' · ' . $c['daysLeft'] . 'd left' : ''; ?>
                            </span>
                            <div class="card-actions">
                                <?php if ($c['Status'] !== 'Completed'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="ChallengeID" value="<?php echo (int) $c['ChallengeID']; ?>">
                                        <button type="submit" name="complete_challenge" class="link-btn">Mark done</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this challenge?');">
                                    <input type="hidden" name="ChallengeID" value="<?php echo (int) $c['ChallengeID']; ?>">
                                    <button type="submit" name="delete_challenge" class="link-btn">Remove</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-panel">
        <div class="form-eyebrow">New challenge</div>
        <h2>Add a savings challenge</h2>
        <p class="lede">Set a target and PocketBuddy tracks it until you're done.</p>

        <div class="ai-card">
            <div class="ai-label">✦ AI Coach<?php echo $aiSuggestion['source'] === 'heuristic' ? '' : ' — live suggestion'; ?></div>
            <p><?php echo htmlspecialchars($aiSuggestion['tip'], ENT_QUOTES, 'UTF-8'); ?><br>
               Try: <strong><?php echo htmlspecialchars($aiSuggestion['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
               — ৳<?php echo htmlspecialchars($aiSuggestion['amount'], ENT_QUOTES, 'UTF-8'); ?>
               over <?php echo htmlspecialchars($aiSuggestion['duration'], ENT_QUOTES, 'UTF-8'); ?> days.
            </p>
            <button type="button" class="ai-use-btn"
                onclick="fillFromAI(<?php echo json_encode($aiSuggestion['name']); ?>, <?php echo json_encode($aiSuggestion['amount']); ?>, <?php echo json_encode($aiSuggestion['duration']); ?>)">
                Use this suggestion
            </button>
        </div>

        <form method="POST">
            <div class="field">
                <label for="challenge_name">Challenge name</label>
                <input type="text" id="challenge_name" name="challenge_name" placeholder="e.g., No-Spend Weekends" required>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="What's the rule of this challenge?"></textarea>
            </div>
            <div class="row">
                <div class="field">
                    <label for="duration">Duration (days)</label>
                    <input type="number" id="duration" name="duration" min="1" required>
                </div>
                <div class="field">
                    <label for="saving_amount">Target amount</label>
                    <input type="number" step="0.01" min="0.01" id="saving_amount" name="saving_amount" required>
                </div>
            </div>
            <button type="submit" name="add_challenge" class="btn">Add challenge</button>
        </form>
        <a href="index.php" class="back-link">← Back to dashboard</a>
    </div>
</div>

<script>
function fillFromAI(name, amount, duration) {
    document.getElementById('challenge_name').value = name;
    document.getElementById('saving_amount').value = amount;
    document.getElementById('duration').value = duration;
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span></span><button class="close-btn" aria-label="Close">×</button>`;
    toast.querySelector('span').textContent = message;
    toast.querySelector('.close-btn').onclick = () => toast.remove();
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

<?php if ($success_message): ?>
    showToast(<?php echo json_encode($success_message); ?>, 'success');
<?php endif; ?>
<?php if ($error_message): ?>
    showToast(<?php echo json_encode($error_message); ?>, 'error');
<?php endif; ?>
</script>
</body>
</html>