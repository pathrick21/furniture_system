<?php
session_start();
include 'connection.php';

// CHECK IF USER IS BANNED - IMMEDIATE LOGOUT
if (isset($_SESSION['user'])) {
    $check_stmt = $conn->prepare("SELECT status FROM signinfo WHERE username = ?");
    $check_stmt->bind_param("s", $_SESSION['user']);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    
    if ($result && $result['status'] === 'banned') {
        // Clear all session data
        $_SESSION = array();
        session_destroy();
        
        // Redirect to login with banned message
        header("Location: login.php?banned=1");
        exit();
    }

    // ===== ADD THIS: CHECK IF SESSION STILL EXISTS IN user_sessions =====
    $session_check = $conn->prepare("SELECT username FROM user_sessions WHERE username = ?");
    $session_check->bind_param("s", $_SESSION['user']);
    $session_check->execute();
    $session_result = $session_check->get_result();
    
    if ($session_result->num_rows == 0) {
        // Session was deleted (force logged out)
        $_SESSION = array();
        session_destroy();
        
        // Redirect to login with force logout message
        header("Location: login.php?force_logout=1");
        exit();
    }
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Fetch user profile data
$username = $_SESSION['user'];
$stmt = $conn->prepare("SELECT profile_pic, fname, lname FROM signinfo WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

$profile_pic = !empty($user_data['profile_pic']) && file_exists($user_data['profile_pic']) 
    ? $user_data['profile_pic'] 
    : null;
    
$fullname = $user_data['fname'] . ' ' . $user_data['lname'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace - Home</title>
    <!-- External CSS -->
    <link rel="stylesheet" href="../css/home.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Menu Overlay -->
    <div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>

    <!-- Side Navigation Menu -->
    <nav class="side-menu" id="sideMenu">
        <div class="menu-header">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($profile_pic): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($fullname); ?></h3>
                    <span><?php echo htmlspecialchars($username); ?></span>
                </div>
            </div>
            <button class="close-menu" onclick="toggleMenu()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="menu-items">
            <a href="home.php" class="menu-item highlight">
                <i class="fas fa-home"></i>
                <span>Home</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
            
            <a href="profile.php" class="menu-item">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
            
            <a href="shop.php" class="menu-item">
                <i class="fas fa-store"></i>
                <span>Shop</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
            
            <a href="cart.php" class="menu-item">
                <i class="fas fa-shopping-cart"></i>
                <span>My Cart</span>
                <span class="badge">3</span>
            </a>
            
            <a href="new-arrivals.php" class="menu-item highlight">
                <i class="fas fa-star"></i>
                <span>Brand New</span>
                <span class="new-tag">NEW</span>
            </a>
        </div>

        <div class="menu-footer">
            <a href="settings.php" class="menu-item small">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php" class="menu-item small logout" onclick="recordLogout(event)">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <button class="hamburger-btn" onclick="toggleMenu()" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <i class="fas fa-chair logo-icon"></i>
            <h1>Furniplace</h1>
        </div>
        <div class="header-right">
            <a href="cart.php" class="cart-icon">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count">3</span>
            </a>
            
            <div class="header-profile">
                <div class="profile-avatar">
                    <?php if ($profile_pic): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($username); ?>!</span>
            </div>
            
            <a href="logout.php" class="logout-btn" onclick="recordLogout(event)">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Section -->
        <section class="welcome-section">
            <h2>Discover Timeless Elegance</h2>
            <p>Transform your house into a home with our exquisite furniture collection</p>
        </section>

        <!-- Auto Swipe Carousel -->
        <div class="carousel-container">
            <div class="carousel">
                <div class="carousel-inner">
                    <div class="carousel-item">
                        <img src="../img/car1.jpg" alt="Luxury Living Room">
                        <div class="carousel-caption">
                            <h3>Luxury Living Rooms</h3>
                            <p>Comfort meets sophistication</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="../img/car2.jpg" alt="Modern Dining">
                        <div class="carousel-caption">
                            <h3>Modern Dining</h3>
                            <p>Dine in style</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="../img/car3.jpg" alt="Cozy Bedroom">
                        <div class="carousel-caption">
                            <h3>Cozy Bedrooms</h3>
                            <p>Your personal sanctuary</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="../img/car4.jpg" alt="Office Furniture">
                        <div class="carousel-caption">
                            <h3>Elegant Offices</h3>
                            <p>Work in comfort</p>
                        </div>
                    </div>
                </div>
                
                <!-- Carousel Controls -->
                <button class="carousel-control prev" onclick="moveCarousel(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-control next" onclick="moveCarousel(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
                
                <!-- Indicators -->
                <div class="carousel-indicators">
                    <span class="indicator active" onclick="goToSlide(0)"></span>
                    <span class="indicator" onclick="goToSlide(1)"></span>
                    <span class="indicator" onclick="goToSlide(2)"></span>
                    <span class="indicator" onclick="goToSlide(3)"></span>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <section class="quick-links">
            <h3>Explore Collections</h3>
            <div class="links-grid">
                <a href="#" class="link-card">
                    <i class="fas fa-couch"></i>
                    <span>Living Room</span>
                </a>
                <a href="#" class="link-card">
                    <i class="fas fa-bed"></i>
                    <span>Bedroom</span>
                </a>
                <a href="#" class="link-card">
                    <i class="fas fa-utensils"></i>
                    <span>Dining</span>
                </a>
                <a href="#" class="link-card">
                    <i class="fas fa-briefcase"></i>
                    <span>Office</span>
                </a>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <i class="fas fa-chair"></i>
                <span>Furniplace</span>
            </div>
            <p class="copyright">&copy; 2024 Furniplace. All rights reserved.</p>
            <div class="footer-links">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // REAL-TIME BAN AND SESSION CHECK - Check every 5 seconds
setInterval(function() {
    fetch('check_ban.php')
        .then(response => response.json())
        .then(data => {
            if (data.banned) {
                alert('⚠️ Your account has been BANNED by an administrator. You will be logged out now.');
                window.location.href = 'login.php?banned=1';
            } else if (data.session_expired) {
                alert('🔒 Your session has been terminated by an administrator. You have been force logged out.');
                window.location.href = 'login.php?force_logout=1';
            }
        })
        .catch(err => console.log('Check failed:', err));
}, 5000); // Check every 5 seconds

        // Carousel Functionality
        const carouselInner = document.querySelector('.carousel-inner');
        const indicators = document.querySelectorAll('.indicator');
        let index = 0;
        const totalItems = 4;
        let autoSwipeInterval;

        function updateCarousel() {
            carouselInner.style.transform = `translateX(-${index * 100}%)`;
            indicators.forEach((ind, i) => {
                if (i === index) ind.classList.add('active');
                else ind.classList.remove('active');
            });
        }

        function moveCarousel(direction) {
            index += direction;
            if (index >= totalItems) index = 0;
            else if (index < 0) index = totalItems - 1;
            updateCarousel();
            resetAutoSwipe();
        }

        function goToSlide(slideIndex) {
            index = slideIndex;
            updateCarousel();
            resetAutoSwipe();
        }

        function autoSwipe() {
            index++;
            if (index >= totalItems) index = 0;
            updateCarousel();
        }

        function resetAutoSwipe() {
            clearInterval(autoSwipeInterval);
            autoSwipeInterval = setInterval(autoSwipe, 5000);
        }

        autoSwipeInterval = setInterval(autoSwipe, 5000);

        const carousel = document.querySelector('.carousel');
        carousel.addEventListener('mouseenter', () => clearInterval(autoSwipeInterval));
        carousel.addEventListener('mouseleave', resetAutoSwipe);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') moveCarousel(-1);
            if (e.key === 'ArrowRight') moveCarousel(1);
        });

        // Hamburger Menu Functionality
        function toggleMenu() {
            const sideMenu = document.getElementById('sideMenu');
            const menuOverlay = document.getElementById('menuOverlay');
            const body = document.body;
            
            sideMenu.classList.toggle('active');
            menuOverlay.classList.toggle('active');
            body.classList.toggle('menu-open');
        }

        // Close menu when clicking on a menu item (mobile friendly)
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleMenu();
                }
            });
        });

        // Record Logout Function
        function recordLogout(e) {
            e.preventDefault();
            navigator.sendBeacon('ping.php?action=offline');
            window.location.href = 'logout.php';
        }

        // Ping functionality
        fetch('ping.php').catch(err => console.log('Ping failed'));
        setInterval(function() {
            fetch('ping.php').catch(err => console.log('Ping failed'));
        }, 30000);
        window.addEventListener('beforeunload', function() {
            navigator.sendBeacon('ping.php?action=offline');
        });
    </script>

</body>
</html>