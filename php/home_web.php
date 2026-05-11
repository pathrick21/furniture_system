<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FurniPlace - Premium Furniture</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/home_web.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="brand">
            <i class="fas fa-couch"></i>
            <span>FurniPlace</span>
        </div>
        <div class="nav-links">
            <a href="#about">About</a>
            <a href="#features">Features</a>
            <a href="login.php" class="btn btn-outline">Login</a>
            <a href="register.php" class="btn btn-primary">Register</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to FurniPlace</h1>
            <p>Discover premium furniture that transforms your house into a home.<br>Quality craftsmanship meets timeless design.</p>
            <div class="hero-buttons">
                <a href="register.php" class="btn btn-primary">Get Started</a>
                <a href="login.php" class="btn btn-outline">Sign In</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <h2>Why Choose FurniPlace?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <i class="fas fa-crown"></i>
                <h3>Premium Quality</h3>
                <p>Handcrafted furniture made from the finest materials, built to last generations with proper care and attention to detail.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-truck-fast"></i>
                <h3>Free Delivery</h3>
                <p>Complimentary white-glove delivery service. We'll bring your furniture inside, assemble it, and remove the packaging.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-shield-alt"></i>
                <h3>5-Year Warranty</h3>
                <p>Every piece comes with our comprehensive 5-year warranty. Shop with confidence knowing we've got you covered.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-undo"></i>
                <h3>30-Day Returns</h3>
                <p>Not satisfied? Return within 30 days for a full refund. No questions asked, hassle-free returns policy.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-tags"></i>
                <h3>Best Prices</h3>
                <p>Direct from manufacturer pricing. No middlemen means you get premium quality at unbeatable prices.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-headset"></i>
                <h3>24/7 Support</h3>
                <p>Our dedicated support team is available around the clock to assist with any questions or concerns.</p>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="about-content">
            <div class="about-text">
                <h2>About FurniPlace</h2>
                <p>FurniPlace is your premier destination for high-quality furniture and home decor. Founded in 2024, we've quickly become a trusted name in the industry, serving thousands of satisfied customers.</p>
                <p>Our mission is simple: to provide beautiful, durable furniture that transforms living spaces without breaking the bank. We work directly with skilled artisans and manufacturers to bring you the best selection at competitive prices.</p>
                <p>Whether you're furnishing your first apartment or redecorating your dream home, our extensive collection has something for every style and budget. From classic to contemporary, minimalist to luxurious - find your perfect piece today.</p>
                <a href="register.php" class="btn btn-primary">Join Our Community</a>
            </div>
            <div class="about-image">
                <i class="fas fa-couch"></i>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <h2>Ready to Transform Your Home?</h2>
        <p>Create an account today and get 10% off your first order!</p>
        <a href="register.php" class="btn btn-primary">Create Free Account</a>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 FurniPlace. All rights reserved. | Making Houses Homes Since 2024</p>
    </footer>

</body>
</html>