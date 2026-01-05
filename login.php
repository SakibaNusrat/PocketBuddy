<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login to PocketBuddy</title>
    <style>
     * {
      margin: 0px;
      padding: 0px;
    }
    body {
      font-size: 120%;
      background: #F8F8FF;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    
     .container {
        max-width: 350px;
        width: 100%;
        padding: 40px;
        background-color: #fff;
        border-radius: 10px 10px 10px 10px; ; /* Rounded corners */
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center;
        height: auto;  /* Allow content to determine height */
        min-height: 250px;  /* Set a minimum height for a smaller box */
        text-align: center; /* Center align content */
        margin-top: 20px;
      }
     
    .logo img {
    width: 150px; /* Adjust the width of the logo */
    height: 150px; /* Ensures a consistent shape */
    border-radius: 50%; /* Makes the frame circular */
    border: 5px solid rgb(24, 24, 24); /* Adds a border to the frame */
    object-fit: cover; /* Ensures the image fits within the oval */
    }

      

      h1 {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 20px;
        color: #641e16;; /* Pinterest red color */
      }

      .input-group {
        margin-bottom: 20px;
        text-align: left;
      }

      .input-group input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 16px;
        margin-top: 6px;
      }

      .input-group input:focus {
        outline: none;
        border-color: #641e16; /* Pinterest red border on focus */
      }

      .input-group label {
        font-size: 14px;
        color: #333;
        font-weight: 600;
      }

      .btn {
        width: 100%;
        background-color:#641e16; /* Pinterest red background */
        color: #fff;
        padding: 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
      }

      .btn:hover {
        background-color:rgb(164, 75, 66); /* Darker red on hover */
      }

      .forgot-password-link a {
        color: #641e16;
        text-decoration: none;
        font-size: 14px;
      }

      .forgot-password-link a:hover {
        text-decoration: underline;
      }

      .social-login {
        margin-top: 20px;
        border-top: 1px solid #ddd;
        padding-top: 20px;
      }

      .social-btn {
        display: block;
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 10px;
        text-decoration: none;
        color: #333;
        font-size: 14px;
        font-weight: 600;
      }

      .facebook {
        background-color: #3b5998;
        color: #fff;
      }

      .facebook:hover {
        background-color:rgb(127, 185, 232);
      }

      .google {
        background-color:rgb(216, 91, 80);
        color: #fff;
      }

      .google:hover {
        background-color: #c1351d;
      }

      .apple {
        background-color: #000;
        color: #fff;
      }

      .apple:hover {
        background-color: #333;
      }

      .social-login p {
        margin-bottom: 10px;
        font-size: 14px;
      }

      .success {
        color: green;
        margin-bottom: 15px;
      }

      .error {
        color: red;
        margin-bottom: 15px;
      }

      p {
        font-size: 14px;
        color: #333;
      }

      p a {
        color: #641e16;
        font-weight: bold;
      }

      p a:hover {
        text-decoration: underline;
      }
    </style>
</head>
<body>
    <?php
    session_start();

    // Initialize variables and errors
    $email = "";
    $password = "";
    $errors = array();

    // Database connection (replace with your actual credentials)
    $db = mysqli_connect('localhost', 'root', '', 'pocketbuddy');

    // Check for login submission
    if (isset($_POST['login_user'])) {
        // Escape input to prevent SQL Injection
        $email = mysqli_real_escape_string($db, $_POST['email']);
        $password = mysqli_real_escape_string($db, $_POST['password']);

        // Validation
        if (empty($email)) {
            array_push($errors, "Email is required");
        }
        if (empty($password)) {
            array_push($errors, "Password is required");
        }

        // If there are no errors, proceed to check the credentials
        if (count($errors) == 0) {
            $password = md5($password); // Encrypt password using md5

            // Query to check if the email and password match
            $query = "SELECT * FROM USERS WHERE Email='$email' AND Password='$password'";
            $results = mysqli_query($db, $query);

            // If user is found, login is successful
            if (mysqli_num_rows($results) == 1) {
                $_SESSION['email'] = $email; // Store user email in session
                $_SESSION['success'] = "You are now logged in"; // Set success message
                header('location: index.php'); // Redirect to index.php after successful login
                exit();
            } else {
                array_push($errors, "Wrong email/password combination"); // Show error if login fails
            }
        }
    }
    ?>

    <div class="container">
        <div class="logo">
            <img src="logo.png" alt=" Logo"> </div>
        <h1>Login to PocketBuddy</h1>

        <?php if (isset($_SESSION['success'])) : ?>
            <div class="success">
                <h3><?php echo $_SESSION['success']; ?></h3>
            </div>
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
                <label>Email</label>
                <input type="email" name="email" placeholder="E-mail address" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Password" required>
            </div>    
            <div class="input-group">
                <button type="submit" class="btn" name="login_user">Login</button>
            </div>
            <p class="forgot-password-link"><a href="forgot_password.php">Forgot password?</a></p> 
        </form>

        <div class="social-login">
            <p>or Login via</p>
            <a href="#" class="social-btn facebook">
                Sign In with Facebook
            </a>
            <a href="#" class="social-btn google">
                Sign In with Google
            </a>
            <a href="#" class="social-btn apple">
                Sign In with Apple
            </a>
        </div>

        <p>Don't have an account yet? <a href="register.php">Sign Up here!</a></p>
    </div>

</body>
</html>

