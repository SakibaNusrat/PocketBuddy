<?php
session_start();

/* =====================================================
   1. SSLCommerz SETTINGS  (change these)
   ===================================================== */
define('SSLC_STORE_ID',   'your_store_id');        // sandbox store id
define('SSLC_STORE_PASS', 'your_store_password');  // sandbox store password
define('SSLC_SANDBOX',    true);                   // false when going live
define('PAGE_URL',        'http://localhost/PocketBuddy/bankaccountadd.php');

define('SSLC_INIT_URL', SSLC_SANDBOX
    ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
    : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php');
define('SSLC_VALIDATE_URL', SSLC_SANDBOX
    ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
    : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php');

/* =====================================================
   2. DATABASE + auto-create tables
   ===================================================== */
$db = mysqli_connect('localhost', 'root', '', 'pocketbuddy');
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}

$db->query("CREATE TABLE IF NOT EXISTS BankAccounts (
    accountID     INT AUTO_INCREMENT PRIMARY KEY,
    userID        INT NOT NULL,
    bankName      VARCHAR(100) NOT NULL,
    accountNumber VARCHAR(30)  NOT NULL,
    ifscCode      VARCHAR(20)  NOT NULL,
    accountType   VARCHAR(30)  NOT NULL,
    createdAt     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_account (userID, accountNumber)
)");

$db->query("CREATE TABLE IF NOT EXISTS Payments (
    paymentID  INT AUTO_INCREMENT PRIMARY KEY,
    userID     INT NOT NULL,
    tran_id    VARCHAR(64) NOT NULL UNIQUE,
    amount     DECIMAL(12,2) NOT NULL,
    currency   VARCHAR(5) NOT NULL DEFAULT 'BDT',
    status     ENUM('PENDING','VALID','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    val_id     VARCHAR(64) NULL,
    createdAt  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

/* =====================================================
   3. SSLCommerz RETURN HANDLER (before login check,
      because the browser comes back with a POST)
   ===================================================== */
if (isset($_GET['sslc'])) {
    $s       = $_GET['sslc'];
    $tran_id = $_POST['tran_id'] ?? '';
    $val_id  = $_POST['val_id'] ?? '';
    $result  = 'invalid';

    if ($tran_id !== '') {
        if ($s === 'fail' || $s === 'cancel') {
            $new = ($s === 'fail') ? 'FAILED' : 'CANCELLED';
            $stmt = $db->prepare("UPDATE Payments SET status=? WHERE tran_id=? AND status='PENDING'");
            $stmt->bind_param("ss", $new, $tran_id);
            $stmt->execute();
            $stmt->close();
            $result = $s;

        } elseif ($s === 'success' && $val_id !== '') {
            $stmt = $db->prepare("SELECT amount, status FROM Payments WHERE tran_id = ?");
            $stmt->bind_param("s", $tran_id);
            $stmt->execute();
            $pay = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($pay && $pay['status'] === 'VALID') {
                $result = 'success';
            } elseif ($pay && $pay['status'] === 'PENDING') {
                $url = SSLC_VALIDATE_URL . '?' . http_build_query([
                    'val_id'       => $val_id,
                    'store_id'     => SSLC_STORE_ID,
                    'store_passwd' => SSLC_STORE_PASS,
                    'format'       => 'json',
                ]);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => !SSLC_SANDBOX,
                    CURLOPT_TIMEOUT        => 30,
                ]);
                $res = json_decode(curl_exec($ch), true);
                curl_close($ch);

                if ($res
                    && in_array($res['status'] ?? '', ['VALID', 'VALIDATED'], true)
                    && ($res['tran_id'] ?? '') === $tran_id
                    && abs((float)$res['amount'] - (float)$pay['amount']) < 0.01) {

                    $stmt = $db->prepare("UPDATE Payments SET status='VALID', val_id=? WHERE tran_id=? AND status='PENDING'");
                    $stmt->bind_param("ss", $val_id, $tran_id);
                    $stmt->execute();
                    $stmt->close();
                    $result = 'success';
                }
            }
        }
    }
    header("Location: bankaccountadd.php?pay=" . $result);
    exit();
}

/* =====================================================
   4. LOGIN CHECK + CURRENT USER
   ===================================================== */
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$stmt = $db->prepare("SELECT userID, Email FROM USERS WHERE Email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}
$userID = (int)$user['userID'];

/* =====================================================
   5. ADD BANK ACCOUNT
   ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_bank_account') {
    $bankName      = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $ifscCode      = strtoupper(trim($_POST['ifsc_code'] ?? ''));
    $accountType   = trim($_POST['account_type'] ?? '');
    $allowedTypes  = ['Savings', 'Current', 'Student', 'Other'];

    if ($bankName === '' || $accountNumber === '' || $ifscCode === '' || $accountType === '') {
        $_SESSION['flash'] = ['error', 'All fields are required.'];
    } elseif (!preg_match('/^[0-9]{6,30}$/', $accountNumber)) {
        $_SESSION['flash'] = ['error', 'Account number must be 6-30 digits.'];
    } elseif (!preg_match('/^[A-Z0-9]{4,20}$/', $ifscCode)) {
        $_SESSION['flash'] = ['error', 'Invalid IFSC / routing code.'];
    } elseif (!in_array($accountType, $allowedTypes, true)) {
        $_SESSION['flash'] = ['error', 'Invalid account type.'];
    } else {
        $chk = $db->prepare("SELECT 1 FROM BankAccounts WHERE userID = ? AND accountNumber = ?");
        $chk->bind_param("is", $userID, $accountNumber);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();

        if ($exists) {
            $_SESSION['flash'] = ['error', 'This account is already added.'];
        } else {
            $stmt = $db->prepare("INSERT INTO BankAccounts (userID, bankName, accountNumber, ifscCode, accountType) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $userID, $bankName, $accountNumber, $ifscCode, $accountType);
            try {
                $stmt->execute();
                $_SESSION['flash'] = ['success', 'Bank account added successfully!'];
            } catch (mysqli_sql_exception $e) {
                $_SESSION['flash'] = ['error', 'Could not add account. Please try again.'];
            }
            $stmt->close();
        }
    }
    header("Location: bankaccountadd.php");
    exit();
}

/* =====================================================
   6. START SSLCommerz PAYMENT (opens the real hosted
      SSLCommerz gateway: bKash, Nagad, Rocket, cards, banking)
   ===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay_init') {
    $amount = (float)($_POST['amount'] ?? 0);
    if ($amount < 10 || $amount > 500000) {
        header("Location: bankaccountadd.php?pay=badamount");
        exit();
    }
    $amount  = number_format($amount, 2, '.', '');
    $tran_id = 'PB' . $userID . '_' . time() . '_' . random_int(1000, 9999);

    $stmt = $db->prepare("INSERT INTO Payments (userID, tran_id, amount) VALUES (?, ?, ?)");
    $stmt->bind_param("isd", $userID, $tran_id, $amount);
    $stmt->execute();
    $stmt->close();

    $post = [
        'store_id'         => SSLC_STORE_ID,
        'store_passwd'     => SSLC_STORE_PASS,
        'total_amount'     => $amount,
        'currency'         => 'BDT',
        'tran_id'          => $tran_id,
        'success_url'      => PAGE_URL . '?sslc=success',
        'fail_url'         => PAGE_URL . '?sslc=fail',
        'cancel_url'       => PAGE_URL . '?sslc=cancel',
        'cus_name'         => $user['Email'],
        'cus_email'        => $user['Email'],
        'cus_add1'         => 'Dhaka',
        'cus_city'         => 'Dhaka',
        'cus_country'      => 'Bangladesh',
        'cus_phone'        => '01700000000',
        'shipping_method'  => 'NO',
        'product_name'     => 'PocketBuddy Top-up',
        'product_category' => 'Wallet',
        'product_profile'  => 'non-physical-goods',
    ];

    $ch = curl_init(SSLC_INIT_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($post),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => !SSLC_SANDBOX,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    $data = $response ? json_decode($response, true) : null;

    if ($data && ($data['status'] ?? '') === 'SUCCESS' && !empty($data['GatewayPageURL'])) {
        header("Location: " . $data['GatewayPageURL']);   // real SSLCommerz payment page
        exit();
    }

    $stmt = $db->prepare("UPDATE Payments SET status='FAILED' WHERE tran_id=?");
    $stmt->bind_param("s", $tran_id);
    $stmt->execute();
    $stmt->close();

    $reason = $data['failedreason'] ?? ($curlErr ?: 'Check your store ID / password.');
    $_SESSION['flash'] = ['error', 'Could not start payment: ' . $reason];
    header("Location: bankaccountadd.php");
    exit();
}

/* =====================================================
   7. LOAD DATA FOR THE PAGE
   ===================================================== */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$payMessages = [
    'success'   => ['success', 'Payment successful! Your balance has been updated.'],
    'fail'      => ['error',   'Payment failed. Please try again.'],
    'cancel'    => ['error',   'Payment was cancelled.'],
    'invalid'   => ['error',   'Payment could not be verified.'],
    'badamount' => ['error',   'Enter an amount between 10 and 500000 BDT.'],
];
if (isset($_GET['pay'], $payMessages[$_GET['pay']])) {
    $flash = $payMessages[$_GET['pay']];
}

$stmt = $db->prepare("SELECT bankName, accountNumber, accountType FROM BankAccounts WHERE userID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$accounts = array_reverse($accounts); // newest first
$stmt->close();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM Payments WHERE userID = ? AND status = 'VALID'");
$stmt->bind_param("i", $userID);
$stmt->execute();
$walletTotal = (float)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $db->prepare("SELECT tran_id, amount, status FROM Payments WHERE userID = ? ORDER BY paymentID DESC LIMIT 4");
$stmt->bind_param("i", $userID);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function maskAcc($n) {
    $len = strlen($n);
    return $len > 4 ? str_repeat('•', $len - 4) . substr($n, -4) : $n;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketBuddy | Bank Account</title>
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

        * { margin: 0; padding: 0; box-sizing: border-box; }

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
            min-height: 600px;
            background: #fff;
            border-radius: 22px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(166, 67, 44, 0.15);
            opacity: 0;
            transform: translateY(14px);
            animation: shellIn 0.5s ease forwards;
        }
        @keyframes shellIn { to { opacity: 1; transform: translateY(0); } }

        /* ===== LEFT PANEL ===== */
        .preview-panel {
            flex: 0.85;
            background: linear-gradient(165deg, #241713 0%, #3A241D 55%, var(--rust) 100%);
            color: #fff;
            padding: 36px 30px;
            display: flex;
            flex-direction: column;
        }
        .preview-panel .top-nav { display: flex; align-items: center; justify-content: space-between; }
        .preview-panel .close-btn {
            width: 32px; height: 32px; border-radius: 9px; border: none;
            background: rgba(255,255,255,0.12); color: #fff; font-size: 18px;
            cursor: pointer; transition: background 0.2s;
        }
        .preview-panel .close-btn:hover { background: rgba(255,255,255,0.22); }
        .preview-panel .badge {
            font-size: 11px; letter-spacing: 0.06em; text-transform: uppercase;
            color: rgba(255,255,255,0.6);
        }
        .preview-panel .icon-ring {
            width: 50px; height: 50px; border-radius: 15px;
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.18);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin: 22px 0 14px; color: var(--terracotta-light);
        }
        .preview-panel h1 {
            font-family: 'Fraunces', serif; font-size: 22px; font-weight: 600;
            line-height: 1.3; margin-bottom: 6px;
        }
        .preview-panel p.lead { font-size: 13px; color: rgba(255,255,255,0.65); line-height: 1.6; }

        .total-strip {
            display: flex; gap: 22px; margin-top: 20px; padding-top: 18px;
            border-top: 1px solid rgba(255,255,255,0.14);
        }
        .total-strip .num { font-family: 'Fraunces', serif; font-size: 19px; font-weight: 600; }
        .total-strip .lbl { font-size: 10.5px; color: rgba(255,255,255,0.6); margin-top: 2px; }

        .section-label {
            font-size: 10.5px; letter-spacing: 0.06em; text-transform: uppercase;
            color: rgba(255,255,255,0.5); margin: 18px 0 8px;
        }

        .budget-list { overflow-y: auto; flex: 1; padding-right: 4px; min-height: 90px; }
        .budget-list::-webkit-scrollbar { width: 5px; }
        .budget-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }

        .budget-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 11px 12px; border-radius: 10px;
            background: rgba(255,255,255,0.06); margin-bottom: 8px; font-size: 12.5px;
        }
        .budget-row .b-left { display: flex; flex-direction: column; }
        .budget-row .b-name { font-weight: 600; color: #fff; }
        .budget-row .b-period { color: rgba(255,255,255,0.55); font-size: 11px; margin-top: 1px; }
        .budget-row .b-amount { font-weight: 600; color: var(--terracotta-light); }
        .b-amount.VALID { color: #5FCB7B; }
        .b-amount.FAILED, .b-amount.CANCELLED { color: #FF8A80; }
        .b-amount.PENDING { color: #F5C26B; }

        .empty-budgets { font-size: 12.5px; color: rgba(255,255,255,0.55); padding: 10px 4px; }

        /* ===== RIGHT PANEL ===== */
        .form-panel {
            flex: 1.15; padding: 40px 44px; display: flex; flex-direction: column;
            justify-content: center; overflow-y: auto;
        }
        .form-panel .form-title {
            font-size: 12.5px; font-weight: 600; letter-spacing: 0.05em;
            text-transform: uppercase; color: var(--terracotta); margin-bottom: 6px;
        }
        .form-panel .form-sub { font-size: 13px; color: var(--ink-soft); margin-bottom: 22px; }

        .input-group { margin-bottom: 16px; }
        .input-row { display: flex; gap: 14px; }
        .input-row .input-group { flex: 1; }
        .input-group label {
            display: block; text-align: left; margin-bottom: 6px;
            font-size: 12.5px; font-weight: 600; color: #4a4a4a;
        }
        .field-wrap { position: relative; }
        .field-wrap i {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            font-size: 12.5px; color: var(--terracotta-light);
        }
        .input-group input, .input-group select {
            width: 100%; padding: 10px 14px 10px 34px; font-size: 13.5px;
            font-family: 'Poppins', sans-serif; border-radius: 9px;
            border: 1.5px solid var(--line); background: #fff; color: var(--ink);
            outline: none; appearance: none; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-group select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%237A6E68'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center;
        }
        .input-group input:focus, .input-group select:focus {
            border-color: var(--terracotta); box-shadow: 0 0 0 3px rgba(193, 90, 62, 0.1);
        }
        .input-group input::placeholder { color: #b7aca6; }

        .add-budget-btn {
            width: 100%; border: none; padding: 13px 20px; font-size: 14.5px; font-weight: 600;
            color: #fff; background: linear-gradient(135deg, #D97A55, #A6432C);
            border-radius: 10px; cursor: pointer; margin-top: 6px;
            box-shadow: 0 10px 24px rgba(166, 67, 44, 0.28);
            transition: transform 0.15s, box-shadow 0.15s; font-family: 'Poppins', sans-serif;
        }
        .add-budget-btn:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(166, 67, 44, 0.38); }

        /* Add-money mini form (inside dark panel) */
        .pay-mini { display: flex; gap: 8px; margin-top: 4px; }
        .pay-mini input {
            flex: 1; min-width: 0; padding: 10px 12px; font-size: 13px;
            font-family: 'Poppins', sans-serif; border-radius: 9px;
            border: 1.5px solid rgba(255,255,255,0.18); background: rgba(255,255,255,0.08);
            color: #fff; outline: none;
        }
        .pay-mini input::placeholder { color: rgba(255,255,255,0.45); }
        .pay-mini input:focus { border-color: var(--terracotta-light); }
        .pay-mini button {
            border: none; padding: 10px 14px; border-radius: 9px; cursor: pointer;
            font-size: 12.5px; font-weight: 600; color: #fff; font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #D97A55, #A6432C); white-space: nowrap;
        }
        .pay-mini button:hover { filter: brightness(1.08); }
        .gateway-note { font-size: 10.5px; color: rgba(255,255,255,0.5); margin-top: 7px; }
        .gateway-note i { margin-right: 4px; color: #5FCB7B; }

        /* Toast */
        .toast {
            position: fixed; bottom: 24px; right: 24px; min-width: 260px; max-width: 340px;
            padding: 14px 18px; border-radius: 10px; font-size: 14px;
            font-family: 'Poppins', sans-serif; color: #fff; display: flex; align-items: center;
            box-shadow: 0 12px 28px rgba(43, 35, 32, 0.18); z-index: 1000;
            opacity: 0; visibility: hidden; transform: translateY(20px); transition: all 0.4s ease;
        }
        .toast.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .toast.success { background: linear-gradient(135deg, #4CAF6D, #2f8b4a); }
        .toast.error { background: linear-gradient(135deg, var(--terracotta-light), var(--rust)); }
        .toast .close-btn {
            margin-left: auto; cursor: pointer; font-size: 16px; font-weight: bold;
            color: #fff; background: none; border: none; padding-left: 10px;
        }

        @media (max-width: 820px) {
            .shell { flex-direction: column; min-height: unset; }
            .preview-panel { padding: 28px; max-height: 520px; }
            .form-panel { padding: 28px; }
        }
        @media (max-width: 480px) {
            .input-row { flex-direction: column; gap: 0; }
        }
    </style>
    <script>
        function closePage() { window.location.href = 'index.php'; }
    </script>
</head>
<body>
    <div class="shell">

        <!-- LEFT: balance, add money, saved accounts -->
        <div class="preview-panel">
            <div class="top-nav">
                <span class="badge">PocketBuddy</span>
                <button class="close-btn" onclick="closePage()">&times;</button>
            </div>

            <div class="icon-ring"><i class="fas fa-university"></i></div>
            <h1>Your Bank Accounts</h1>
            <p class="lead">Saved accounts and wallet balance, all in one place.</p>

            <div class="total-strip">
                <div class="item">
                    <div class="num">৳<?php echo number_format($walletTotal, 2); ?></div>
                    <div class="lbl">Wallet Balance</div>
                </div>
                <div class="item">
                    <div class="num"><?php echo count($accounts); ?></div>
                    <div class="lbl">Accounts Saved</div>
                </div>
            </div>

            <div class="section-label">Add Money</div>
            <form method="POST" class="pay-mini">
                <input type="hidden" name="action" value="pay_init">
                <input type="number" name="amount" min="10" max="500000" step="0.01" placeholder="Amount (BDT)" required>
                <button type="submit"><i class="fas fa-lock"></i> Pay with SSLCommerz</button>
            </form>
            <div class="gateway-note"><i class="fas fa-shield-alt"></i>bKash, Nagad, Rocket, cards &amp; net banking on the SSLCommerz secure page</div>

            <div class="section-label">Saved Accounts</div>
            <div class="budget-list">
                <?php if (!empty($accounts)): ?>
                    <?php foreach ($accounts as $a): ?>
                        <div class="budget-row">
                            <div class="b-left">
                                <span class="b-name"><?php echo htmlspecialchars($a['bankName'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="b-period"><?php echo htmlspecialchars($a['accountType'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <span class="b-amount"><?php echo htmlspecialchars(maskAcc($a['accountNumber']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-budgets">No bank accounts yet — add your first one on the right.</div>
                <?php endif; ?>

                <?php if (!empty($payments)): ?>
                    <div class="section-label">Recent Payments</div>
                    <?php foreach ($payments as $p): ?>
                        <div class="budget-row">
                            <div class="b-left">
                                <span class="b-name"><?php echo htmlspecialchars($p['tran_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="b-period"><?php echo htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <span class="b-amount <?php echo htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8'); ?>">৳<?php echo number_format((float)$p['amount'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: add bank account form -->
        <div class="form-panel">
            <div class="form-title">New Bank Account</div>
            <div class="form-sub">Fill in the details below to save a bank account.</div>

            <form method="POST">
                <input type="hidden" name="action" value="add_bank_account">

                <div class="input-group">
                    <label for="bank_name">Bank Name</label>
                    <div class="field-wrap">
                        <i class="fas fa-university"></i>
                        <input type="text" id="bank_name" name="bank_name" required placeholder="e.g., Dutch-Bangla Bank">
                    </div>
                </div>

                <div class="input-group">
                    <label for="account_number">Account Number</label>
                    <div class="field-wrap">
                        <i class="fas fa-hashtag"></i>
                        <input type="text" id="account_number" name="account_number" required
                               inputmode="numeric" pattern="[0-9]{6,30}" title="6-30 digits" placeholder="e.g., 1234567890">
                    </div>
                </div>

                <div class="input-row">
                    <div class="input-group">
                        <label for="ifsc_code">IFSC / Routing Code</label>
                        <div class="field-wrap">
                            <i class="fas fa-code-branch"></i>
                            <input type="text" id="ifsc_code" name="ifsc_code" required placeholder="e.g., 090261725">
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="account_type">Account Type</label>
                        <div class="field-wrap">
                            <i class="fas fa-tag"></i>
                            <select id="account_type" name="account_type" required>
                                <option value="">Select Type</option>
                                <option value="Savings">Savings</option>
                                <option value="Current">Current</option>
                                <option value="Student">Student</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="add-budget-btn">Add Account</button>
            </form>
        </div>
    </div>

    <script>
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            const span = document.createElement('span');
            span.textContent = message;
            const btn = document.createElement('button');
            btn.className = 'close-btn';
            btn.innerHTML = '&times;';
            btn.onclick = () => toast.remove();
            toast.append(span, btn);
            document.body.appendChild(toast);
            requestAnimationFrame(() => toast.classList.add('show'));
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 500);
            }, 4500);
        }

        <?php if ($flash): ?>
            showToast(<?php echo json_encode($flash[1]); ?>, <?php echo json_encode($flash[0]); ?>);
            if (location.search.includes('pay=')) history.replaceState(null, '', location.pathname);
        <?php endif; ?>
    </script>
</body>
</html>