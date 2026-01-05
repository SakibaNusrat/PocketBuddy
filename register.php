<?php include('server.php'); ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration</title>
  <style>
    * {
      margin: 0px;
      padding: 0px;
    }
    body {
      font-size: 120%;
      background: #F8F8FF;
    }

    .header {
      width: 30%;
      margin: 50px auto 0px;
      color: white;
      background:#641e16;
      text-align: center;
      border: 1px solid #641e16;
      border-bottom: none;
      border-radius: 10px 10px 0px 0px;
      padding: 20px;
    }
    form, .content {
      width: 30%;
      margin: 0px auto;
      padding: 20px;
      border: 1px solid #641e16;
      background: white;
      border-radius: 0px 0px 10px 10px;
    }
    .input-group {
      margin: 10px 0px 10px 0px;
    }
    .input-group label {
      display: block;
      text-align: left;
      margin: 3px;
    }
    .input-group input {
      height: 30px;
      width: 93%;
      padding: 5px 10px;
      font-size: 16px;
      border-radius: 5px;
      border: 1px solid gray;
    }
    .btn {
      padding: 10px;
      font-size: 15px;
      color: white;
      background: #641e16;
      border: none;
      border-radius: 5px;
    }
    .error {
      width: 92%;
      margin: 0px auto;
      padding: 10px;
      border: 1px solid #a94442;
      color: #a94442;
      background: #f2dede;
      border-radius: 5px;
      text-align: left;
    }
    .success {
      color: #3c763d;
      background: #dff0d8;
      border: 1px solid #3c763d;
      margin-bottom: 20px;
    }
    a {
  color: #641e16; /* Set the link color */
  text-decoration: none; /* Remove underline by default */
}

a:hover {
  color: #641e16; /* Changes color on hover */
  text-decoration: underline; /* Underline the link on hover */
}


  </style>
</head>

<body>
  <div class="header">
    <h2>Sign Up</h2>
  </div>

  <form method="post" action="register.php">
    <?php include('errors.php'); ?>

    <!-- First Name -->
    <div class="input-group">
      <label for="first_name">First Name</label>
      <input type="text" name="first_name" value="<?php echo $first_name; ?>" required>
    </div>

    <!-- Last Name -->
    <div class="input-group">
      <label for="last_name">Last Name</label>
      <input type="text" name="last_name" value="<?php echo $last_name; ?>" required>
    </div>

    <!-- Email -->
    <div class="input-group">
      <label for="email">Email</label>
      <input type="email" name="email" value="<?php echo $email; ?>" required>
    </div>

    <!-- Password -->
    <div class="input-group">
      <label for="password_1">Password</label>
      <input type="password" name="password_1" required>
    </div>

    <!-- Confirm Password -->
    <div class="input-group">
      <label for="password_2">Confirm Password</label>
      <input type="password" name="password_2" required>
    </div>

    <!-- Contact Number (Optional) -->
    <div class="input-group">
      <label for="contact_number">Contact Number (Optional)</label>
      <input type="text" name="contact_number" value="<?php echo $contact_number; ?>" maxlength="11" pattern="^\d{11}$" 
             title="Contact number must be exactly 11 digits">
    </div>

    <!-- Submit Button -->
    <div class="input-group">
      <button type="submit" class="btn" name="reg_user">Continue</button>
    </div>

    <!-- Sign In -->
    <p>
      Already a member? <a href="login.php">Sign in</a>
    </p>
  </form>
</body>

</html>
