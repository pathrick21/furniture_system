<?php
session_start();
include 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['user'];

// Handle Remove from Cart
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    unset($_SESSION['cart'][$product_id]);
    header("Location: cart.php");
    exit();
}

// Handle Update Quantity
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $product_id => $qty) {
        $product_id = intval($product_id);
        $qty = intval($qty);
        
        if ($qty > 0) {
            // Check stock
            $stock_check = $conn->prepare("SELECT stock FROM products WHERE id = ?");
            $stock_check->bind_param("i", $product_id);
            $stock_check->execute();
            $stock_result = $stock_check->get_result()->fetch_assoc();
            
            if ($stock_result && $qty <= $stock_result['stock']) {
                $_SESSION['cart'][$product_id]['quantity'] = $qty;
            } else {
                $_SESSION['error'] = "Not enough stock for some items!";
            }
        } else {
            unset($_SESSION['cart'][$product_id]);
        }
    }
    header("Location: cart.php");
    exit();
}

// Handle Checkout
if (isset($_POST['checkout'])) {
    if (empty($_SESSION['cart'])) {
        $_SESSION['error'] = "Your cart is empty!";
        header("Location: cart.php");
        exit();
    }
    
    // Validate payment method
    $payment_method = $_POST['payment_method'] ?? '';
    $valid_methods = ['credit_card', 'paypal', 'cash_on_delivery', 'bank_transfer'];
    
    if (!in_array($payment_method, $valid_methods)) {
        $_SESSION['error'] = "Please select a valid payment method!";
        header("Location: cart.php");
        exit();
    }
    
    // Calculate total
    $total_amount = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
    
    // Create order
    $order_stmt = $conn->prepare("INSERT INTO orders (username, total_amount, payment_method, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $order_stmt->bind_param("sds", $username, $total_amount, $payment_method);
    
    if ($order_stmt->execute()) {
        $order_id = $conn->insert_id;
        
        // Add order items
        $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)");
        $update_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        
        foreach ($_SESSION['cart'] as $product_id => $item) {
            // Insert order item
            $item_stmt->bind_param("iisdi", $order_id, $product_id, $item['name'], $item['price'], $item['quantity']);
            $item_stmt->execute();
            
            // Update stock
            $update_stock->bind_param("ii", $item['quantity'], $product_id);
            $update_stock->execute();
        }
        
        // Clear cart
        $_SESSION['cart'] = array();
        $_SESSION['success'] = "Order placed successfully! Order #$order_id";
        header("Location: order_confirmation.php?id=$order_id");
        exit();
    }
}

// Calculate totals
$subtotal = 0;
$cart_count = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
        $cart_count += $item['quantity'];
    }
}

$shipping = ($subtotal > 5000) ? 0 : 150; // Free shipping over 5000
$total = $subtotal + $shipping;

// Fetch user profile
$prof_stmt = $conn->prepare("SELECT profile_pic, fname, lname FROM signinfo WHERE username = ?");
$prof_stmt->bind_param("s", $username);
$prof_stmt->execute();
$user_data = $prof_stmt->get_result()->fetch_assoc();
$profile_pic = !empty($user_data['profile_pic']) && file_exists($user_data['profile_pic']) 
    ? $user_data['profile_pic'] 
    : null;

$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace - My Cart</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/cart.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Menu Overlay -->
    <div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>

    <!-- Side Navigation -->
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
                    <h3><?php echo htmlspecialchars($user_data['fname'] . ' ' . $user_data['lname']); ?></h3>
                    <span><?php echo htmlspecialchars($username); ?></span>
                </div>
            </div>
            <button class="close-menu" onclick="toggleMenu()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="menu-items">
            <a href="home.php" class="menu-item">
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
            <a href="cart.php" class="menu-item highlight">
                <i class="fas fa-shopping-cart"></i>
                <span>My Cart</span>
                <span class="badge"><?php echo $cart_count; ?></span>
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
            <button class="hamburger-btn" onclick="toggleMenu()">
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
                <span class="cart-count"><?php echo $cart_count; ?></span>
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
        </div>
    </header>

    <!-- Alerts -->
    <?php if ($error_msg): ?>
        <div class="alert alert-error" style="max-width: 1200px; margin: 1rem auto;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success_msg): ?>
        <div class="alert alert-success" style="max-width: 1200px; margin: 1rem auto;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="cart-container">
        <div class="cart-header">
            <h1><i class="fas fa-shopping-cart"></i> My Shopping Cart</h1>
        </div>

        <?php if (empty($_SESSION['cart'])): ?>
            <div class="cart-items" style="grid-column: 1 / -1;">
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <h3>Your cart is empty</h3>
                    <p>Looks like you haven't added anything yet.</p>
                    <a href="shop.php" class="continue-shopping">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Cart Items -->
            <form method="POST" class="cart-items">
                <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                    <div class="cart-item">
                        <div class="item-image">
                            <?php if (!empty($item['image']) && file_exists($item['image'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-couch"></i>
                            <?php endif; ?>
                        </div>
                        <div class="item-details">
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p>Stock: <?php echo $item['stock']; ?> available</p>
                        </div>
                        <div class="item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                        <div class="quantity-control">
                            <input type="number" name="quantity[<?php echo $product_id; ?>]" 
                                   value="<?php echo $item['quantity']; ?>" 
                                   min="0" max="<?php echo $item['stock']; ?>">
                        </div>
                        <a href="?action=remove&id=<?php echo $product_id; ?>" class="remove-btn" onclick="return confirm('Remove this item?')">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" name="update_cart" class="update-btn">
                    <i class="fas fa-sync-alt"></i> Update Cart
                </button>
            </form>

            <!-- Order Summary & Payment -->
            <form method="POST" class="order-summary">
                <h2>Order Summary</h2>
                
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span><?php echo $shipping == 0 ? 'FREE' : '₱' . number_format($shipping, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Tax (12%)</span>
                    <span>₱<?php echo number_format($subtotal * 0.12, 2); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span>₱<?php echo number_format($total + ($subtotal * 0.12), 2); ?></span>
                </div>

                <div class="payment-section">
                    <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                    
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="credit_card" required>
                        <i class="fas fa-credit-card"></i>
                        <div>
                            <div style="font-weight: 600;">Credit Card</div>
                            <div style="font-size: 0.8rem; color: #8b7355;">Visa, Mastercard, Amex</div>
                        </div>
                    </label>
                    
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="paypal">
                        <i class="fab fa-paypal"></i>
                        <div>
                            <div style="font-weight: 600;">PayPal</div>
                            <div style="font-size: 0.8rem; color: #8b7355;">Pay with your PayPal account</div>
                        </div>
                    </label>
                    
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="cash_on_delivery">
                        <i class="fas fa-money-bill-wave"></i>
                        <div>
                            <div style="font-weight: 600;">Cash on Delivery</div>
                            <div style="font-size: 0.8rem; color: #8b7355;">Pay when you receive</div>
                        </div>
                    </label>
                    
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="bank_transfer">
                        <i class="fas fa-university"></i>
                        <div>
                            <div style="font-weight: 600;">Bank Transfer</div>
                            <div style="font-size: 0.8rem; color: #8b7355;">Direct bank deposit</div>
                        </div>
                    </label>
                </div>

                <button type="submit" name="checkout" class="checkout-btn">
                    <i class="fas fa-lock"></i> Place Order
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script>
        function toggleMenu() {
            const sideMenu = document.getElementById('sideMenu');
            const menuOverlay = document.getElementById('menuOverlay');
            const body = document.body;
            
            sideMenu.classList.toggle('active');
            menuOverlay.classList.toggle('active');
            body.classList.toggle('menu-open');
        }

        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleMenu();
                }
            });
        });

        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input').checked = true;
            });
        });

        function recordLogout(e) {
            e.preventDefault();
            navigator.sendBeacon('ping.php?action=offline');
            window.location.href = 'logout.php';
        }

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