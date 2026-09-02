<?php
session_start();

// Initialize variables and errors
$email = "";
$password = "";
$errors = array();

// Database connection
$db = mysqli_connect('localhost', 'root', '', 'pocketbuddy');

// Check connection
if (!$db) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check for login submission
if (isset($_POST['login_user'])) {
    // Get input values
    $email = mysqli_real_escape_string($db, $_POST['email']);
    $password = $_POST['password']; // Don't escape password before verification

    // Validation
    if (empty($email)) {
        array_push($errors, "Email is required");
    }
    if (empty($password)) {
        array_push($errors, "Password is required");
    }

    // If there are no errors, proceed to check the credentials
    if (count($errors) == 0) {
        // Query to get user by email
        $query = "SELECT * FROM users WHERE Email = ?";
        $stmt = mysqli_prepare($db, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            // Check password - supports both bcrypt and MD5
            $password_valid = false;
            
            // Check if password is bcrypt (starts with $2y$)
            if (password_get_info($row['Password'])['algo']) {
                // Bcrypt password verification
                if (password_verify($password, $row['Password'])) {
                    $password_valid = true;
                }
            } else {
                // MD5 password verification (for backward compatibility)
                if (md5($password) === $row['Password']) {
                    $password_valid = true;
                }
            }

            if ($password_valid) {
                // Store user ID in session - THIS IS IMPORTANT for other tables
                $_SESSION['UserID'] = $row['UserID'];
                $_SESSION['email'] = $row['Email'];
                $_SESSION['success'] = "You are now logged in";
                
                // Redirect to index.php after successful login
                header('location: index.php');
                exit();
            } else {
                array_push($errors, "Wrong email/password combination");
            }
        } else {
            array_push($errors, "Wrong email/password combination");
        }
        
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login to PocketBuddy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @import "https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800";
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Petit+Formal+Script&display=swap');

        :root {
            --primary: #3E2723;
            --primary-dark: #241412;
            --primary-light: #f3ece7;
            --accent-orange: #c9962b;
            --text-dark: #201613;
            --text-muted: #837a77;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #eef0ff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .auth-card {
            width: 100%;
            max-width: 880px;
            min-height: 560px;
            background: #fff;
            border-radius: 26px;
            box-shadow: 0 20px 50px rgba(91, 79, 233, 0.18);
            display: flex;
            overflow: hidden;
        }

        /* ============ LEFT: FORM PANEL ============ */
        .form-panel {
            flex: 1 1 52%;
            padding: 52px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-panel h1 {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .form-panel .subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 28px;
        }

        .input-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .input-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 7px;
        }

        .input-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #e5e3f5;
            background: #f9f9ff;
            border-radius: 10px;
            font-size: 0.92rem;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.2s;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
        }

        .forgot-password-link {
            text-align: right;
            margin-bottom: 20px;
        }

        .forgot-password-link a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .forgot-password-link a:hover {
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            background: var(--primary);
            color: #fff;
            padding: 13px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 10px 20px rgba(91, 79, 233, 0.3);
            transition: background 0.2s;
        }

        .btn:hover {
            background: var(--primary-dark);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.82rem;
            margin: 22px 0;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #eae8fb;
        }

        .divider:not(:empty)::before {
            margin-right: 14px;
        }

        .divider:not(:empty)::after {
            margin-left: 14px;
        }

        .social-login {
            display: flex;
            gap: 12px;
        }

        .social-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px;
            border: 1.5px solid #e5e3f5;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-dark);
            font-size: 0.85rem;
            font-weight: 600;
            background: #fff;
            transition: border-color 0.2s;
        }

        .social-btn:hover {
            border-color: var(--primary);
        }

        .social-btn.google i { color: #DB4437; }
        .social-btn.facebook i { color: #3b5998; }

        .bottom-text {
            text-align: center;
            margin-top: 26px;
            font-size: 0.88rem;
            color: var(--text-muted);
        }

        .bottom-text a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .bottom-text a:hover {
            text-decoration: underline;
        }

        .success {
            color: #1e8e50;
            background: #e9faf0;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 0.85rem;
        }

        .error {
            color: #c0392b;
            background: #fdeeec;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 0.85rem;
        }

        .error p {
            margin: 0;
        }

        /* ============ RIGHT: ILLUSTRATION PANEL ============ */
        .illustration-panel {
            flex: 1 1 48%;
            position: relative;
            background: linear-gradient(160deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 30px;
            overflow: hidden;
        }

        .illustration-panel::before {
            content: "";
            position: absolute;
            left: -60px;
            top: 0;
            height: 100%;
            width: 120px;
            background: #fff;
            border-radius: 0 50% 50% 0 / 0 55% 55% 0;
        }

        .brand-text {
            position: relative;
            z-index: 2;
            text-align: center;
            color: #fff;
            margin-bottom: 34px;
        }

        .logo-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: var(--primary);
            color: #fff;
            font-family: 'Playfair Display', serif;
            font-weight: 900;
            font-size: 1.3rem;
            letter-spacing: -0.5px;
            box-shadow: 0 6px 14px rgba(0,0,0,0.18);
            flex-shrink: 0;
        }

        .logo-badge.on-dark {
            background: #fff;
            color: var(--primary);
        }

        .logo-badge.lg {
            width: 62px;
            height: 62px;
            border-radius: 17px;
            font-size: 1.7rem;
        }

        .logo-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-row .wordmark {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-dark);
            line-height: 1.1;
        }

        .logo-row .wordmark small {
            display: block;
            font-family: 'Poppins', sans-serif;
            font-weight: 400;
            font-size: 0.7rem;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .brand-logo span {
            display: inline-block;
        }

        .brand-logo .script {
            font-family: 'Petit Formal Script', cursive;
            font-weight: 400;
            font-size: 1.4rem;
            letter-spacing: 1px;
            display: block;
            margin-top: -6px;
        }

        .brand-text h2 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 6px;
            margin-top: 14px;
        }

        .brand-text p {
            font-size: 0.9rem;
            opacity: 0.85;
            line-height: 1.5;
        }

        .illustration-scene {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 260px;
            height: 220px;
        }

        .wallet {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 100px;
            background: #17122b;
            border-radius: 14px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }

        .wallet::after {
            content: "";
            position: absolute;
            top: 14px;
            right: 14px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--accent-orange);
        }

        .card {
            position: absolute;
            width: 120px;
            height: 76px;
            border-radius: 12px;
            box-shadow: 0 12px 24px rgba(0,0,0,0.22);
        }

        .card.one {
            background: linear-gradient(135deg, #8f85f4, #6a5cf0);
            top: 20px;
            left: 30px;
            transform: rotate(-12deg);
        }

        .card.two {
            background: linear-gradient(135deg, #4d3fce, #372a9c);
            top: 45px;
            left: 60px;
            transform: rotate(-4deg);
        }

        .coin {
            position: absolute;
            border-radius: 50%;
            background: var(--accent-orange);
            box-shadow: 0 6px 14px rgba(0,0,0,0.2);
        }

        .coin.c1 { width: 26px; height: 26px; top: 5px; right: 40px; }
        .coin.c2 { width: 18px; height: 18px; top: 30px; right: 15px; }

        .coin-stack {
            position: absolute;
            bottom: 6px;
            right: 20px;
            width: 46px;
        }

        .coin-stack span {
            display: block;
            height: 10px;
            border-radius: 50%;
            background: var(--accent-orange);
            border: 2px solid #d98c0f;
            margin-top: -4px;
        }

        .plant-icon {
            position: absolute;
            bottom: 0;
            left: 8px;
            font-size: 2.4rem;
            color: #7fd399;
        }

        @media (max-width: 760px) {
            .auth-card {
                flex-direction: column;
                max-width: 420px;
            }

            .illustration-panel {
                order: -1;
                padding: 30px 20px;
                min-height: 220px;
            }

            .illustration-panel::before {
                display: none;
            }

            .form-panel {
                padding: 36px 30px;
            }
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <!-- Left: Form -->
        <div class="form-panel">
            <div class="logo-row" style="margin-bottom: 26px;">
                <div class="logo-badge">pB</div>
                <div class="wordmark">PocketBuddy<small>Personal Finance</small></div>
            </div>
            <h1>Welcome Back!</h1>
            <p class="subtitle">Login to continue to PocketBuddy</p>

            <?php if (isset($_SESSION['success'])) : ?>
                <div class="success"><?php echo $_SESSION['success']; ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif ?>

            <?php if (count($errors) > 0) : ?>
                <div class="error">
                    <?php foreach ($errors as $error) : ?>
                        <p><?php echo $error ?></p>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <form method="post" action="login.php">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="forgot-password-link">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
                <button type="submit" class="btn" name="login_user">Login</button>
            </form>

            <div class="divider">or continue with</div>

            <div class="social-login">
                <a href="#" class="social-btn google"><i class="fab fa-google"></i> Google</a>
                <a href="#" class="social-btn facebook"><i class="fab fa-facebook"></i> Facebook</a>
            </div>

            <p class="bottom-text">Don't have an account? <a href="register.php">Register</a></p>
        </div>

        <!-- Right: Illustration -->
        <div class="illustration-panel">
            <div class="brand-text">
                <div class="logo-badge lg on-dark" style="margin: 0 auto 14px auto;">pB</div>
                <h2>PocketBuddy</h2>
                <p>Your Personal Finance<br>Companion</p>
            </div>
            <div class="illustration-scene">
                <span class="coin c1"></span>
                <span class="coin c2"></span>
                <div class="card one"></div>
                <div class="card two"></div>
                <div class="wallet"></div>
                <div class="coin-stack">
                    <span></span><span></span><span></span><span></span>
                </div>
                <i class="fas fa-leaf plant-icon"></i>
            </div>
        </div>
    </div>

</body>
</html>