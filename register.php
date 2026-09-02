<?php include('server.php'); ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      background: #FDF3EF;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 30px 15px;
    }

    .auth-wrapper {
      width: 100%;
      max-width: 900px;
      min-height: 620px;
      background: #fff;
      border-radius: 24px;
      box-shadow: 0 20px 50px rgba(196, 92, 58, 0.15);
      display: flex;
      overflow: hidden;
    }

    /* LEFT PANEL - FORM */
    .form-panel {
      flex: 1.1;
      padding: 50px 45px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      position: relative;
      z-index: 2;
    }

    .form-panel::after {
      content: "";
      position: absolute;
      top: 0;
      right: -40px;
      width: 80px;
      height: 100%;
      background: #fff;
      border-radius: 50% / 8%;
      z-index: 1;
    }

    .form-panel h2 {
      color: #2b2b2b;
      font-size: 28px;
      margin-bottom: 6px;
    }

    .form-panel .subtitle {
      color: #9a9a9a;
      font-size: 14px;
      margin-bottom: 28px;
    }

    .input-group {
      margin-bottom: 18px;
    }

    .input-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #4a4a4a;
      margin-bottom: 6px;
    }

    .input-group input {
      width: 100%;
      height: 46px;
      padding: 0 16px;
      font-size: 14px;
      border: 1px solid #ECE0DB;
      background: #FBF6F3;
      border-radius: 10px;
      outline: none;
      color: #2b2b2b;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .input-group input::placeholder {
      color: #b7aca6;
    }

    .input-group input:focus {
      border-color: #C15A3E;
      box-shadow: 0 0 0 3px rgba(193, 90, 62, 0.12);
      background: #fff;
    }

    .name-row {
      display: flex;
      gap: 12px;
    }

    .name-row .input-group {
      flex: 1;
    }

    .terms {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 6px 0 22px;
      font-size: 13px;
      color: #4a4a4a;
    }

    .terms input {
      accent-color: #C15A3E;
      width: 15px;
      height: 15px;
    }

    .terms a {
      color: #C15A3E;
      font-weight: 600;
      text-decoration: none;
    }

    .terms a:hover {
      text-decoration: underline;
    }

    .btn {
      width: 100%;
      height: 48px;
      font-size: 15px;
      font-weight: 600;
      color: #fff;
      background: linear-gradient(135deg, #D97A55, #B5533C);
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: transform 0.15s, box-shadow 0.15s;
      box-shadow: 0 8px 20px rgba(181, 83, 60, 0.35);
    }

    .btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 24px rgba(181, 83, 60, 0.45);
    }

    .signin-text {
      text-align: center;
      margin-top: 22px;
      font-size: 14px;
      color: #4a4a4a;
    }

    .signin-text a {
      color: #C15A3E;
      font-weight: 600;
      text-decoration: none;
    }

    .signin-text a:hover {
      text-decoration: underline;
    }

    /* RIGHT PANEL - ILLUSTRATION */
    .side-panel {
      flex: 0.9;
      background: linear-gradient(160deg, #C15A3E 0%, #A6432C 100%);
      color: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 40px 30px;
      position: relative;
    }

    .side-panel h3 {
      font-size: 24px;
      line-height: 1.4;
      margin-bottom: 25px;
      max-width: 260px;
    }

    .side-panel svg {
      width: 240px;
      height: auto;
    }

    /* ERRORS */
    .error {
      width: 100%;
      margin: 0 0 18px;
      padding: 12px 15px;
      border: 1px solid #d98c73;
      color: #a13d24;
      background: #fbeae4;
      border-radius: 8px;
      text-align: left;
      font-size: 13px;
    }

    .success {
      color: #2f6b3a;
      background: #e2f3e5;
      border: 1px solid #2f6b3a;
    }

    /* RESPONSIVE */
    @media (max-width: 760px) {
      .auth-wrapper {
        flex-direction: column-reverse;
      }
      .form-panel::after {
        display: none;
      }
      .side-panel {
        padding: 30px;
      }
      .side-panel svg {
        width: 160px;
      }
      .name-row {
        flex-direction: column;
        gap: 0;
      }
    }
  </style>
</head>

<body>

  <div class="auth-wrapper">

    <!-- LEFT: FORM -->
    <div class="form-panel">
      <h2>Create Account</h2>
      <p class="subtitle">Join us today!</p>

      <form method="post" action="register.php">
        <?php include('errors.php'); ?>

        <div class="name-row">
          <!-- First Name -->
          <div class="input-group">
            <label for="first_name">First Name</label>
            <input type="text" name="first_name" placeholder="Enter your first name" value="<?php echo $first_name; ?>" required>
          </div>

          <!-- Last Name -->
          <div class="input-group">
            <label for="last_name">Last Name</label>
            <input type="text" name="last_name" placeholder="Enter your last name" value="<?php echo $last_name; ?>" required>
          </div>
        </div>

        <!-- Email -->
        <div class="input-group">
          <label for="email">Email Address</label>
          <input type="email" name="email" placeholder="Enter your email" value="<?php echo $email; ?>" required>
        </div>

        <!-- Password -->
        <div class="input-group">
          <label for="password_1">Password</label>
          <input type="password" name="password_1" placeholder="Create a password" required>
        </div>

        <!-- Confirm Password -->
        <div class="input-group">
          <label for="password_2">Confirm Password</label>
          <input type="password" name="password_2" placeholder="Confirm your password" required>
        </div>

        <!-- Contact Number (Optional) -->
        <div class="input-group">
          <label for="contact_number">Contact Number (Optional)</label>
          <input type="text" name="contact_number" placeholder="Enter your contact number" value="<?php echo $contact_number; ?>"
                 maxlength="11" pattern="^\d{11}$" title="Contact number must be exactly 11 digits">
        </div>

        <div class="terms">
          <input type="checkbox" id="agree">
          <label for="agree">I agree to the <a href="terms.php">Terms &amp; Privacy Policy</a></label>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn" name="reg_user">Register</button>

        <!-- Sign In -->
        <p class="signin-text">
          Already have an account? <a href="login.php">Login</a>
        </p>
      </form>
    </div>

    <!-- RIGHT: ILLUSTRATION -->
    <div class="side-panel">
      <h3>Start Your Financial Journey With Us</h3>
      <svg viewBox="0 0 240 220" fill="none" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="120" cy="190" rx="90" ry="10" fill="rgba(0,0,0,0.15)"/>
        <circle cx="60" cy="70" r="10" fill="#F2C879"/>
        <circle cx="90" cy="45" r="8" fill="#F2C879"/>
        <circle cx="180" cy="60" r="9" fill="#F2C879"/>
        <ellipse cx="120" cy="130" rx="70" ry="45" fill="#F5A57A"/>
        <circle cx="170" cy="120" r="16" fill="#F5A57A"/>
        <polygon points="180,105 195,100 190,115" fill="#F5A57A"/>
        <circle cx="196" cy="118" r="3" fill="#3a2a20"/>
        <ellipse cx="120" cy="175" rx="14" ry="6" fill="#E8946A"/>
        <ellipse cx="90" cy="178" rx="10" ry="5" fill="#E8946A"/>
        <ellipse cx="150" cy="178" rx="10" ry="5" fill="#E8946A"/>
        <rect x="60" y="120" width="14" height="6" rx="3" fill="#E8946A"/>
        <circle cx="190" cy="185" r="14" fill="#F2C879"/>
        <circle cx="210" cy="180" r="10" fill="#F2C879"/>
        <circle cx="205" cy="195" r="8" fill="#F2C879"/>
      </svg>
    </div>

  </div>

</body>

</html>