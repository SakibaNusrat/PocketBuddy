<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Connect to the database
$db = mysqli_connect('localhost', 'root', '', 'pocketbuddy');
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch the user's first name based on their email
$first_Name = "Guest"; // Default value
if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
    $query = "SELECT first_name FROM users WHERE email = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $first_Name = $row['first_name'];
    }
    $stmt->close();
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Sidebar</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        @import "https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700";
        body {
            font-family: 'Poppins', sans-serif;
            background:rgb(241, 239, 239);
        }

        p {
            font-family: 'Dancing Script', cursive;
            font-size: 1.1em;
            font-weight: 300;
            line-height: 1.7em;
            color: #999;
        }
        


        .logo img {
         width: 150px; /* Adjust the width of the logo */
         height: 150px; /* Ensures a consistent shape */
          border-radius: 50%; /* Makes the frame circular */
         border: 5px solid rgb(24, 24, 24); /* Adds a border to the frame */
         object-fit: cover; /* Ensures the image fits within the oval */
         }

        a,
        a:hover,
        a:focus {
            color: inherit;
            text-decoration: none;
            transition: all 0.3s;
        }

        .navbar {
            padding: 15px 10px;
            background: #fff;
            border: none;
            border-radius: 10px 10px 10px 10px;
            margin-bottom: 40px;
            box-shadow: 1px 1px 3px #641e16;
        }

        .navbar-btn {
            box-shadow: none;
            outline: none !important;
            border: none;
        }

        .line {
            width: 100%;
            height: 1px;
            border-bottom: 1px dashed #ddd;
            margin: 40px 0;
        }

        /* Sidebar Style */
        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #641e16;
            color: #fff;
            transition: all 0.3s;
        }

        #sidebar.active {
            margin-left: -250px;
        }

        #sidebar .sidebar-header {
            padding: 20px;
            background: #641e16;
        }

        #sidebar ul.components {
            padding: 20px 0;
            border-bottom: 1px solid #641e16;
        }

        #sidebar ul p {
            color: #fff;
            padding: 10px;
        }

        #sidebar ul li a {
            padding: 10px;
            font-size: 1.1em;
            display: block;
        }

        #sidebar ul li a:hover {
            color: #641e16;
            background: #fff;
        }

        #sidebar ul li.active>a,
        a[aria-expanded="true"] {
            color: #fff;
            background: #edbb99;
        }

        ul ul a {
            font-size: 0.9em !important;
            padding-left: 30px !important;
            background: #641e16;
        }

        ul.CTAs {
            padding: 20px;
        }

        ul.CTAs a {
            text-align: center;
            font-size: 0.9em !important;
            display: block;
            border-radius: 5px;
            margin-bottom: 5px;
        }

        a.download {
            background: #fff;
            color: #f5cba7;
        }

        /* Content Style */
        #content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }

            #sidebar.active {
                margin-left: 0;
            }

            #sidebarCollapse span {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <!-- Sidebar -->
        
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3>Dashboard</h3>
            </div>
            <ul class="list-unstyled components">
                <p>Simplifying Budgeting and Expense Management</p>
                <li class="active">
                    <a href="#homeSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">Feature</a>
                    <ul class="collapse list-unstyled" id="homeSubmenu">
                        <li><a href="budget.php">Budget</a></li>
                        <li><a href="goal.php">Goal</a></li>
                        <li><a href="expense.php">Expense</a></li>
                        <li><a href="donation.php">Donation</a></li>
                        <li><a href="subscription.php">Subscription</a></li>
                        <li><a href="bills.php">Bills</a></li>
                        <li><a href="savingChallenge.php">Saving Challenge</a></li>
                    </ul>
                </li>
                
                <li>
                    
                </li>
                <li><a href="budgetchart.php">budgetchart</a></li>
                <li><a href="goalchart.php">Goalchart</a></li>
                <li><a href="child_sub_account.php">child Sub Accout</a></li>
                <li><a href="bankaccountadd.php">Bank Account</a></li>
                <li><a href="#">About</a></li>
            </ul>
        </nav>

       <!-- Page Content -->
<div id="content">
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <button type="button" id="sidebarCollapse" class="btn btn-info">
                <i class="fas fa-align-left"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="nav navbar-nav ml-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="username"><?php echo htmlspecialchars($first_Name); ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-bell"></i> <span class="badge badge-danger"></span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="message.php"><i class="fas fa-envelope"></i> <span class="badge badge-primary">2</span></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section with Image Slider -->
    <div class="hero-section" style="position: relative; text-align: center; color: white; overflow: hidden;">
        <img src="hero-image.jpg" alt="Hero Image" class="slide" style="width: 100%; height: auto; transition: transform 0.5s ease-in-out;">
        <img src="hero-image1.jpg" alt="Hero Image" class="slide" style="width: 100%; height: auto; transition: transform 0.5s ease-in-out;">
        <img src="hero-image2.jpg" alt="Hero Image" class="slide" style="width: 100%; height: auto; transition: transform 0.5s ease-in-out;">
        <img src="hero-image3.jpg" alt="Second Hero Image" class="slide" style="width: 100%; height: auto; transition: transform 0.5s ease-in-out; display: none;">
        <div class="hero-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; justify-content: center; align-items: center;">
            
            <h1 style="font-size: 3rem;font-weight: bold;">Welcome To our page </h1>
        </div>
    </div>

    <!-- Content Section -->
    <div class="container mt-5">
        
        <p> </p>
    </div>
</div>

<script>
    // Hero Image Slider (Auto-Switch Images)
    let currentIndex = 0;
    const slides = document.querySelectorAll('.slide');

    function showSlide() {
        slides.forEach((slide, index) => {
            slide.style.display = "none"; // Hide all slides
        });
        currentIndex = (currentIndex + 1) % slides.length;
        slides[currentIndex].style.display = "block"; // Show the next slide
    }

    // Show the first slide initially
    showSlide();

    // Change images every 5 seconds
    setInterval(showSlide, 5000);

    // Add a subtle zoom effect on the hero images on hover
    const heroImages = document.querySelectorAll('.hero-section img');

    heroImages.forEach(heroImage => {
        heroImage.addEventListener('mouseover', () => {
            heroImage.style.transform = 'scale(1.05)';
        });

        heroImage.addEventListener('mouseout', () => {
            heroImage.style.transform = 'scale(1)';
        });
    });
</script>


    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
            });
        });
    </script>
</body>

</html>
