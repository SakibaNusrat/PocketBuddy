<?php
session_start(); // Start session
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketBuddy — Smart Finance, Better Future</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">

    <!-- Fonts: Outfit for display/headings, Inter for body -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

    <style>

        :root {
            --terracotta: #C1502E;
            --terracotta-dark: #93391F;
            --terracotta-soft: #F0A488;
            --sand: #FBF3EC;
            --clay-card: #F7E3D6;
            --ink: #2B211C;
            --ink-muted: #7A6A61;
            --cream-panel: #FFFDFB;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            background-color: var(--sand);
            margin: 0;
        }

        h1, h2, h3, h4, .brand-font {
            font-family: 'Outfit', sans-serif;
        }

        /* ---------- Navbar ---------- */
        .navbar-brand {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: var(--ink);
        }

        .navbar-brand .brand-dot {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            background: var(--terracotta);
            color: #fff;
            border-radius: 9px;
            margin-right: 10px;
            font-size: 1rem;
        }

        .navbar-scroll {
            background-color: var(--cream-panel);
            transition: box-shadow 0.25s ease;
            padding-top: 14px;
            padding-bottom: 14px;
        }

        .navbar-scrolled {
            box-shadow: 0 2px 14px rgba(43, 33, 28, 0.08);
        }

        .navbar-scroll .nav-link {
            color: var(--ink-muted);
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .navbar-scroll .nav-link:hover {
            color: var(--terracotta);
        }

        .btn-terracotta {
            background-color: var(--terracotta);
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 8px;
            transition: background-color 0.2s ease, transform 0.15s ease;
        }

        .btn-terracotta:hover {
            background-color: var(--terracotta-dark);
            color: #fff;
        }

        .btn-outline-terracotta {
            background-color: transparent;
            border: 1.5px solid #E4D3C6;
            color: var(--ink);
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 8px;
            transition: border-color 0.2s ease, color 0.2s ease;
        }

        .btn-outline-terracotta:hover {
            border-color: var(--terracotta);
            color: var(--terracotta);
        }

        /* ---------- Hero ---------- */
        .hero-section {
            padding: 140px 0 90px;
            overflow: hidden;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--clay-card);
            color: var(--terracotta-dark);
            font-weight: 600;
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 30px;
            margin-bottom: 24px;
        }

        .hero-section h1 {
            font-size: 3.1rem;
            font-weight: 700;
            line-height: 1.12;
            margin-bottom: 22px;
            max-width: 560px;
        }

        .hero-section p.lead {
            color: var(--ink-muted);
            font-size: 1.1rem;
            max-width: 460px;
            margin-bottom: 34px;
        }

        .hero-actions { display: flex; gap: 14px; }

        /* Phone dashboard mockup (built in CSS — no stock imagery) */
        .phone-mockup {
            position: relative;
            width: 300px;
            margin: 0 auto;
            background: var(--ink);
            border-radius: 34px;
            padding: 14px;
            box-shadow: 0 30px 60px -20px rgba(43, 33, 28, 0.35);
        }

        .phone-screen {
            background: var(--cream-panel);
            border-radius: 22px;
            padding: 22px 18px;
            min-height: 480px;
        }

        .phone-screen .screen-label {
            font-size: 0.75rem;
            color: var(--ink-muted);
            font-weight: 500;
        }

        .phone-screen .balance {
            font-family: 'Outfit', sans-serif;
            font-size: 1.7rem;
            font-weight: 700;
            margin: 4px 0 18px;
        }

        .stat-pill {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--terracotta);
            color: #fff;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }

        .stat-pill small { display: block; opacity: 0.85; font-size: 0.7rem; }
        .stat-pill strong { font-family: 'Outfit', sans-serif; font-size: 1.05rem; }

        .bar-row {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 90px;
            background: var(--clay-card);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 16px;
        }

        .bar-row span {
            display: block;
            width: 100%;
            border-radius: 4px;
            background: var(--terracotta-soft);
        }

        .bar-row span.tall { background: var(--terracotta); }

        .mini-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--clay-card);
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 0.85rem;
        }

        .mini-row i {
            color: var(--terracotta);
            margin-right: 8px;
        }

        .hero-decoration {
            position: absolute;
            border-radius: 50%;
            background: var(--terracotta-soft);
            opacity: 0.35;
            z-index: -1;
        }

        /* ---------- Feature strip ---------- */
        .feature-strip {
            background: var(--cream-panel);
            border-radius: 22px;
            padding: 48px 32px;
            margin: 0 auto 100px;
            box-shadow: 0 20px 45px -30px rgba(43, 33, 28, 0.25);
        }

        .feature-boxes {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .feature-box {
            flex: 1 1 0;
            min-width: 150px;
            text-align: center;
        }

        .feature-box .icon-wrap {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: var(--clay-card);
            color: var(--terracotta);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin: 0 auto 16px;
        }

        .feature-box h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .feature-box p {
            font-size: 0.85rem;
            color: var(--ink-muted);
            margin: 0;
        }

        /* ---------- Events section ---------- */
        .events-heading {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .events-heading + p {
            color: var(--ink-muted);
            margin-bottom: 40px;
        }

        .card { border: none; border-radius: 16px; overflow: hidden; }
        .card .btn-primary { background: var(--terracotta); border: none; }
        .card .btn-primary:hover { background: var(--terracotta-dark); }
        .card .btn-info { background: var(--clay-card); border: none; color: var(--terracotta-dark); font-weight: 600; }
        .card .btn-info:hover { background: var(--terracotta-soft); color: #fff; }

        /* ---------- Footer ---------- */
        footer { background-color: var(--cream-panel); }
        .footer-logo { color: var(--ink); }
        .footer-social a { color: var(--ink-muted); }
        .footer-social a:hover { color: var(--terracotta); }
        .footer-links a { color: var(--ink-muted); }
        .footer-links a:hover { color: var(--terracotta); }

        @media (max-width: 991px) {
            .hero-section { padding-top: 120px; text-align: center; }
            .hero-section h1, .hero-section p.lead { margin-left: auto; margin-right: auto; }
            .hero-actions { justify-content: center; }
            .phone-mockup { margin-top: 50px; }
        }
    </style>
</head>

<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top navbar-scroll shadow-0">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <span class="brand-dot"><i class="fa-solid fa-wallet"></i></span>
            Pocketbuddy
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <i class="fas fa-bars"></i>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-2">
                <li class="nav-item"><a class="nav-link px-3" href="#">Home</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#!">How It Works</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#!">About Us</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#!">Contact Us</a></li>
            </ul>
            <ul class="navbar-nav flex-row align-items-center gap-2">
                <li class="nav-item">
                    <a class="nav-link px-3" href="login.php">Login</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="#!">Bank Account</a></li>
                        <li><a class="dropdown-item" href="#!">Help Center</a></li>
                        <li><a class="dropdown-item" href="#!">About Us</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="btn btn-terracotta" href="explore.php">Get Started</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- End Navbar -->

<!-- Hero Section -->
<div class="container hero-section position-relative">
    <div class="hero-decoration" style="width:260px; height:260px; top:20px; right:-60px;"></div>
    <div class="row align-items-center">
        <div class="col-lg-6">
            <span class="hero-eyebrow"><i class="fa-solid fa-seedling"></i> Smart Finance, Better Future</span>
            <h1>Manage Money Your Way</h1>
            <p class="lead">Track expenses, plan budgets, achieve goals and grow your savings with PocketBuddy.</p>
            <div class="hero-actions">
                <a href="explore.php" class="btn-terracotta btn btn-lg">Get Started</a>
                <a href="#features" class="btn-outline-terracotta btn btn-lg">Learn More</a>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="phone-mockup">
                <div class="phone-screen">
                    <span class="screen-label">Dashboard</span>
                    <div class="balance">$ 5,320.00</div>

                    <div class="stat-pill">
                        <div>
                            <small>Monthly Budget</small>
                            <strong>$ 1,200</strong>
                        </div>
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>

                    <div class="bar-row">
                        <span style="height:35%"></span>
                        <span style="height:55%"></span>
                        <span class="tall" style="height:85%"></span>
                        <span style="height:45%"></span>
                        <span style="height:65%"></span>
                        <span style="height:30%"></span>
                    </div>

                    <div class="mini-row">
                        <span><i class="fa-solid fa-shield-heart"></i> Goal: Emergency Fund</span>
                        <strong>62%</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Hero -->

<!-- Feature Strip -->
<div class="container" id="features">
    <div class="feature-strip">
        <div class="feature-boxes">
            <div class="feature-box">
                <div class="icon-wrap"><i class="fa-solid fa-receipt"></i></div>
                <h4>Expense Tracker</h4>
                <p>Track every penny</p>
            </div>
            <div class="feature-box">
                <div class="icon-wrap"><i class="fa-solid fa-chart-pie"></i></div>
                <h4>Budget Planner</h4>
                <p>Plan smart budgets</p>
            </div>
            <div class="feature-box">
                <div class="icon-wrap"><i class="fa-solid fa-bullseye"></i></div>
                <h4>Goal Setting</h4>
                <p>Achieve your goals</p>
            </div>
            <div class="feature-box">
                <div class="icon-wrap"><i class="fa-solid fa-bell"></i></div>
                <h4>Bill Reminder</h4>
                <p>Never miss a bill</p>
            </div>
            <div class="feature-box">
                <div class="icon-wrap"><i class="fa-solid fa-shield-halved"></i></div>
                <h4>Secure &amp; Private</h4>
                <p>Your data is safe</p>
            </div>
        </div>
    </div>
</div>
<!-- End Feature Strip -->

<!-- Upcoming Events -->
<div class="container mb-5">
    <h2 class="events-heading">Upcoming Events</h2>
    <p>Join community meetups and workshops to get more out of PocketBuddy.</p>

    <div id="eventsCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php
            include 'includes/db.php';
            $sql = "SELECT * FROM events WHERE date >= CURDATE() ORDER BY date ASC";
            $result = $conn->query($sql);
            $first = true;
            while ($row = $result->fetch_assoc()):
                $activeClass = $first ? 'active' : ''; // Ensure only the first event is marked as active
                $first = false;
            ?>

            <div class="carousel-item <?= $activeClass ?>">
                <div class="row">
                    <!-- Left Column for Image -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <img src="<?= $row['image'] ?: 'https://via.placeholder.com/350x150' ?>" class="card-img-top" alt="Event Image">
                            <div class="card-body">
                                <h5 class="card-title"><?= $row['title'] ?></h5>
                                <p class="card-text"><?= $row['description'] ?></p>
                                <a href="#" class="btn btn-primary">Join Now</a>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column for Event Details -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Event Details</h5>
                                <p class="card-text"><strong>Date:</strong> <?= date('F j, Y', strtotime($row['date'])) ?></p>
                                <p class="card-text"><strong>Location:</strong> <?= $row['location'] ?></p>
                                <p class="card-text"><strong>Time:</strong> <?= date('h:i A', strtotime($row['time'])) ?></p>
                                <a href="#" class="btn btn-info">Get More Info</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Carousel Controls -->
        <button class="carousel-control-prev" type="button" data-bs-target="#eventsCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#eventsCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
</div>
<!-- End Events -->

<!-- Footer -->
<footer>
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="footer-logo mb-3">PocketBuddy</div>
                <p class="text-muted">Smart finance, better future. Manage your money your way.</p>
                <div class="footer-social">
                    <a href="#!"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#!"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#!"><i class="fa-brands fa-x-twitter"></i></a>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <h5>Quick Links</h5>
                <div class="footer-links">
                    <a href="#!">Home</a>
                    <a href="#features">Features</a>
                    <a href="#!">About Us</a>
                    <a href="#!">Contact Us</a>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <h5>Support</h5>
                <div class="footer-links">
                    <a href="#!">Help Center</a>
                    <a href="#!">Bank Account</a>
                    <a href="login.php">Login</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.min.js"></script>

<script>
    // Navbar shadow on scroll
    window.onscroll = function () {
        if (window.scrollY > 50) {
            document.querySelector('.navbar-scroll').classList.add('navbar-scrolled');
        } else {
            document.querySelector('.navbar-scroll').classList.remove('navbar-scrolled');
        }
    }
</script>
</body>

</html>