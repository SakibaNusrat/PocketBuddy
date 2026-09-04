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
$stmt->close();

$success_message = null;
$error_message = null;

// ---------------------------------------------------------
// Add Subscription
// ---------------------------------------------------------
if (isset($_POST['add_subscription'])) {
    $category    = trim($_POST['subscription_category']);
    $amount      = (float) $_POST['subscription_amount'];
    $payingDate  = $_POST['subscription_paying_date'];
    $renewalDate = $_POST['subscription_renewal_date'];
    $budgetID    = (int) $_POST['Month']; // selected Budget ID from the form

    if ($category === '' || $amount <= 0 || !$payingDate || !$renewalDate || !$budgetID) {
        $error_message = "Please fill in every field correctly.";
    } else {
        // Confirm the chosen budget really belongs to this user
        $checkQuery = "SELECT BudgetID FROM budget WHERE BudgetID = ? AND userID = ?";
        $stmt = $db->prepare($checkQuery);
        $stmt->bind_param("ii", $budgetID, $userID);
        $stmt->execute();
        $checkResult = $stmt->get_result();
        $stmt->close();

        if ($checkResult->num_rows > 0) {
            // FIX: the previous type string ("ssdsds") mismatched the actual
            // column types, which corrupted RenewalDate on insert.
            // Correct order: userID(i), Category(s), Amount(d), PayingDate(s), RenewalDate(s), BudgetID(i)
            $query = "INSERT INTO Subscription (userID, Category, Amount, PayingDate, RenewalDate, BudgetID)
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bind_param("isdssi", $userID, $category, $amount, $payingDate, $renewalDate, $budgetID);
            if ($stmt->execute()) {
                $success_message = "Subscription added successfully!";
            } else {
                $error_message = "Error adding subscription: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Invalid budget month selected.";
        }
    }
}

// ---------------------------------------------------------
// Delete Subscription (kept simple: ownership always checked)
// ---------------------------------------------------------
if (isset($_POST['delete_subscription'])) {
    $subID = (int) $_POST['SubscriptionID'];
    $stmt = $db->prepare("DELETE FROM Subscription WHERE SubscriptionID = ? AND userID = ?");
    $stmt->bind_param("ii", $subID, $userID);
    if ($stmt->execute()) {
        $success_message = "Subscription removed.";
    } else {
        $error_message = "Could not remove subscription: " . $stmt->error;
    }
    $stmt->close();
}

// ---------------------------------------------------------
// Fetch Budget Data (for the dropdown)
// ---------------------------------------------------------
$budgetQuery = "SELECT BudgetID, Month, budgetyear FROM budget WHERE userID = ? ORDER BY budgetyear DESC, BudgetID DESC";
$stmt = $db->prepare($budgetQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$budgetResult = $stmt->get_result();
$budgetData = [];
while ($row = $budgetResult->fetch_assoc()) {
    $budgetData[] = $row;
}
$stmt->close();

// ---------------------------------------------------------
// Fetch Subscriptions + build reminder info
// ---------------------------------------------------------
$subQuery = "SELECT s.SubscriptionID, s.Category, s.Amount, s.PayingDate, s.RenewalDate,
                    b.Month, b.budgetyear
             FROM Subscription s
             LEFT JOIN budget b ON s.BudgetID = b.BudgetID
             WHERE s.userID = ?
             ORDER BY s.RenewalDate ASC";
$stmt = $db->prepare($subQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$subResult = $stmt->get_result();

$subscriptions = [];
$monthlyTotal = 0;
$today = new DateTime('today');

while ($row = $subResult->fetch_assoc()) {
    $renewal = new DateTime($row['RenewalDate']);
    $daysLeft = (int) $today->diff($renewal)->format('%r%a');

    if ($daysLeft < 0) {
        $row['reminder_label'] = 'Overdue';
        $row['reminder_class'] = 'overdue';
    } elseif ($daysLeft === 0) {
        $row['reminder_label'] = 'Due today';
        $row['reminder_class'] = 'due-soon';
    } elseif ($daysLeft <= 3) {
        $row['reminder_label'] = "Due in {$daysLeft}d";
        $row['reminder_class'] = 'due-soon';
    } else {
        $row['reminder_label'] = "Due in {$daysLeft}d";
        $row['reminder_class'] = 'ok';
    }

    $monthlyTotal += (float) $row['Amount'];
    $subscriptions[] = $row;
}
$stmt->close();

$subCount = count($subscriptions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscriptions · PocketBuddy</title>
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

    body {
        font-family: 'Inter', system-ui, sans-serif;
        background: var(--cream);
        color: var(--ink);
        font-size: 16px;
    }

    h1, h2, h3, .brand {
        font-family: 'Fraunces', serif;
    }

    .layout {
        display: grid;
        grid-template-columns: minmax(300px, 38%) 1fr;
        max-width: 1100px;
        margin: 48px auto;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 30px 60px -25px rgba(43, 14, 10, 0.35);
    }

    /* ---------- LEFT: subscriptions overview ---------- */
    .overview {
        background: linear-gradient(165deg, var(--maroon-deep) 0%, var(--maroon) 62%, var(--brick) 100%);
        color: #f2e9e4;
        padding: 40px 34px;
        display: flex;
        flex-direction: column;
        min-height: 620px;
    }

    .brand {
        font-size: 13px;
        letter-spacing: 0.06em;
        color: #d9b8ab;
        margin-bottom: 22px;
    }

    .overview h1 {
        font-size: 30px;
        font-weight: 600;
        margin-bottom: 6px;
    }

    .overview p.sub {
        font-size: 14px;
        color: #d9c0b6;
        margin-bottom: 22px;
    }

    .stats {
        display: flex;
        gap: 28px;
        padding-bottom: 20px;
        border-bottom: 1px solid rgba(255,255,255,0.15);
        margin-bottom: 20px;
    }

    .stats .num {
        font-size: 26px;
        font-weight: 600;
        font-family: 'Fraunces', serif;
    }

    .stats .label {
        font-size: 12px;
        color: #d9c0b6;
        margin-top: 2px;
    }

    .sub-list {
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding-right: 4px;
    }

    .sub-card {
        background: rgba(255,255,255,0.07);
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 12px;
        padding: 14px 16px;
    }

    .sub-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .sub-name {
        font-weight: 600;
        font-size: 15px;
    }

    .sub-meta {
        font-size: 12px;
        color: #d9c0b6;
        margin-top: 3px;
    }

    .sub-amount {
        font-family: 'Fraunces', serif;
        font-size: 17px;
        font-weight: 600;
    }

    .pill {
        display: inline-block;
        margin-top: 8px;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 999px;
    }

    .pill.overdue   { background: rgba(192, 57, 43, 0.9);  color: #fff; }
    .pill.due-soon  { background: rgba(201, 118, 47, 0.9); color: #fff; }
    .pill.ok        { background: rgba(63, 122, 92, 0.9);  color: #fff; }

    .del-form { margin-top: 8px; text-align: right; }
    .del-btn {
        background: none;
        border: none;
        color: #e8c9bd;
        font-size: 12px;
        cursor: pointer;
        text-decoration: underline;
        padding: 0;
    }
    .del-btn:hover { color: #fff; }

    .empty-state {
        color: #d9c0b6;
        font-size: 14px;
        padding: 20px 0;
    }

    /* ---------- RIGHT: add subscription form ---------- */
    .form-panel {
        background: #fff;
        padding: 44px 40px;
    }

    .form-eyebrow {
        color: var(--brick);
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .form-panel h2 {
        font-size: 24px;
        margin-bottom: 6px;
    }

    .form-panel > p {
        color: var(--muted);
        font-size: 14px;
        margin-bottom: 26px;
    }

    .row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .field { margin-bottom: 16px; }

    .field label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--ink);
    }

    .field select,
    .field input {
        width: 100%;
        height: 42px;
        padding: 0 12px;
        font-size: 15px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: #fff;
        color: var(--ink);
    }

    .field select:focus,
    .field input:focus {
        outline: none;
        border-color: var(--brick);
        box-shadow: 0 0 0 3px rgba(181, 80, 47, 0.15);
    }

    .btn {
        width: 100%;
        margin-top: 6px;
        padding: 13px;
        font-size: 15px;
        font-weight: 600;
        color: #fff;
        background: linear-gradient(120deg, var(--brick), var(--maroon-light));
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: filter 0.2s ease;
    }
    .btn:hover { filter: brightness(1.08); }

    a.back-link {
        display: inline-block;
        margin-top: 18px;
        font-size: 13px;
        color: var(--muted);
        text-decoration: none;
    }
    a.back-link:hover { color: var(--brick); }

    /* Toast */
    .toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        min-width: 240px;
        max-width: 320px;
        padding: 14px 18px;
        border-radius: 8px;
        font-size: 14px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.18);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(12px);
        transition: all 0.4s ease;
    }
    .toast.show { opacity: 1; visibility: visible; transform: translateY(0); }
    .toast.success { background: var(--ok); }
    .toast.error   { background: var(--overdue); }
    .toast .close-btn {
        background: none; border: none; color: #fff;
        font-size: 16px; cursor: pointer; line-height: 1;
    }

    @media (max-width: 800px) {
        .layout { grid-template-columns: 1fr; margin: 0; border-radius: 0; }
        .row { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<div class="layout">

    <!-- LEFT: overview + reminders -->
    <div class="overview">
        <div class="brand">POCKETBUDDY</div>
        <h1>Your Subscriptions</h1>
        <p class="sub">Every recurring payment, sorted by what's due next.</p>

        <div class="stats">
            <div>
                <div class="num">৳<?php echo number_format($monthlyTotal, 2); ?></div>
                <div class="label">Total per cycle</div>
            </div>
            <div>
                <div class="num"><?php echo $subCount; ?></div>
                <div class="label">Active subscriptions</div>
            </div>
        </div>

        <div class="sub-list">
            <?php if (empty($subscriptions)): ?>
                <div class="empty-state">No subscriptions yet — add your first one on the right and PocketBuddy will start reminding you before each renewal.</div>
            <?php else: ?>
                <?php foreach ($subscriptions as $sub): ?>
                    <div class="sub-card">
                        <div class="sub-card-top">
                            <div>
                                <div class="sub-name"><?php echo htmlspecialchars($sub['Category'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="sub-meta">
                                    Renews <?php echo htmlspecialchars(date('d M Y', strtotime($sub['RenewalDate'])), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!empty($sub['Month'])): ?>
                                        · <?php echo htmlspecialchars($sub['Month'] . ' ' . $sub['budgetyear'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="sub-amount">৳<?php echo number_format($sub['Amount'], 2); ?></div>
                        </div>
                        <span class="pill <?php echo $sub['reminder_class']; ?>"><?php echo $sub['reminder_label']; ?></span>
                        <form method="POST" class="del-form" onsubmit="return confirm('Remove this subscription?');">
                            <input type="hidden" name="SubscriptionID" value="<?php echo (int) $sub['SubscriptionID']; ?>">
                            <button type="submit" name="delete_subscription" class="del-btn">Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT: add subscription form -->
    <div class="form-panel">
        <div class="form-eyebrow">New subscription</div>
        <h2>Add a subscription</h2>
        <p>Track it once and PocketBuddy will flag it as the renewal date gets close.</p>

        <form method="POST">
            <div class="field">
                <label for="subscription_category">Category</label>
                <select id="subscription_category" name="subscription_category" required>
                    <option value="">Select category</option>
                    <option value="Netflix">Netflix</option>
                    <option value="Spotify">Spotify</option>
                    <option value="Amazon Prime">Amazon Prime</option>
                    <option value="Hoichoi">Hoichoi</option>
                    <option value="Chorki">Chorki</option>
                    <option value="Bongo">Bongo</option>
                </select>
            </div>

            <div class="row">
                <div class="field">
                    <label for="subscription_amount">Amount</label>
                    <input type="number" step="0.01" min="0.01" id="subscription_amount" name="subscription_amount" placeholder="0.00" required>
                </div>
                <div class="field">
                    <label for="budget_month">Budget month</label>
                    <select id="budget_month" name="Month" required>
                        <option value="">Select budget</option>
                        <?php if (!empty($budgetData)): ?>
                            <?php foreach ($budgetData as $budget): ?>
                                <option value="<?php echo htmlspecialchars($budget['BudgetID'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($budget['Month'] . ' ' . $budget['budgetyear'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No budgets found</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="subscription_paying_date">Paying date</label>
                    <input type="date" id="subscription_paying_date" name="subscription_paying_date" required>
                </div>
                <div class="field">
                    <label for="subscription_renewal_date">Renewal date</label>
                    <input type="date" id="subscription_renewal_date" name="subscription_renewal_date" required>
                </div>
            </div>

            <button type="submit" name="add_subscription" class="btn">Add subscription</button>
        </form>
        <a href="index.php" class="back-link">← Back to dashboard</a>
    </div>
</div>

<script>
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