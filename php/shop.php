<?php
session_start();
include 'connection.php';
include 'device_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Update user activity
$stmt = $conn->prepare("UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP, is_online = 1 WHERE username = ?");
$stmt->bind_param("s", $_SESSION['user']);
$stmt->execute();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Handle Add to Cart (only if stock available)
$show_modal = false;
$modal_product = null;

if (isset($_GET['action']) && $_GET['action'] == 'add' && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    
    // Check stock first
    $stock_check = $conn->prepare("SELECT stock FROM products WHERE id = ?");
    $stock_check->bind_param("i", $product_id);
    $stock_check->execute();
    $stock_result = $stock_check->get_result()->fetch_assoc();
    
    if ($stock_result && $stock_result['stock'] > 0) {
        // Get product details
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        if ($product) {
            $added = false;
            if (isset($_SESSION['cart'][$product_id])) {
                if ($_SESSION['cart'][$product_id]['quantity'] < $product['stock']) {
                    $_SESSION['cart'][$product_id]['quantity']++;
                    $added = true;
                } else {
                    $_SESSION['error'] = "Maximum stock reached!";
                }
            } else {
                $_SESSION['cart'][$product_id] = array(
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => 1,
                    'image' => $product['image'],
                    'stock' => $product['stock']
                );
                $added = true;
            }
            
            // Set modal data if added successfully
            if ($added) {
                $show_modal = true;
                $modal_product = $product;
                $cart_count = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $cart_count += $item['quantity'];
                }
            }
        }
    } else {
        $_SESSION['error'] = "Product out of stock!";
    }
    
    // Keep filter params for redirect
    $redirect_url = "shop.php";
    $params = array();
    if (isset($_GET['category'])) $params[] = "category=" . $_GET['category'];
    if (isset($_GET['search'])) $params[] = "search=" . urlencode($_GET['search']);
    if (!empty($params)) $redirect_url .= "?" . implode("&", $params);
    
    // Don't redirect immediately - show modal first via JavaScript
    // We'll handle this with JS instead
}

// Handle Remove from Cart
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    unset($_SESSION['cart'][$product_id]);
    header("Location: shop.php");
    exit();
}

// Get filter values
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query - Only show products with stock > 0
$sql = "SELECT * FROM products WHERE stock > 0";
$params = array();
$types = "";

if (!empty($category_filter)) {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if (!empty($search_term)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $types .= "ss";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$products = $stmt->get_result();

// Get categories for filter
$cat_stmt = $conn->prepare("SELECT DISTINCT category FROM products WHERE stock > 0 ORDER BY category");
$cat_stmt->execute();
$categories = $cat_stmt->get_result();

// Calculate cart count
$cart_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_count += $item['quantity'];
}

// Fetch user profile
$username = $_SESSION['user'];
$prof_stmt = $conn->prepare("SELECT profile_pic, fname, lname FROM signinfo WHERE username = ?");
$prof_stmt->bind_param("s", $username);
$prof_stmt->execute();
$user_data = $prof_stmt->get_result()->fetch_assoc();
$profile_pic = !empty($user_data['profile_pic']) && file_exists($user_data['profile_pic']) 
    ? $user_data['profile_pic'] 
    : null;

// Show error messages
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace - Shop</title>
    <link rel="stylesheet" href="../css/shop.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Add Modal Styles -->
    <style>
        /* Add to Cart Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .add-to-cart-modal {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            transform: scale(0.8) translateY(20px);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .modal-overlay.active .add-to-cart-modal {
            transform: scale(1) translateY(0);
        }
        
        .modal-success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.3);
        }
        
        .modal-success-icon i {
            font-size: 2.5rem;
            color: white;
        }
        
        .add-to-cart-modal h3 {
            font-family: 'Playfair Display', serif;
            color: #3E2723;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .add-to-cart-modal p {
            color: #8b7355;
            margin-bottom: 1.5rem;
        }
        
        .modal-product {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #faf8f5;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        .modal-product-image {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .modal-product-image i {
            font-size: 2rem;
            color: #d4c4b0;
        }
        
        .modal-product-info h4 {
            color: #3E2723;
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        
        .modal-product-info .price {
            color: #8B5A2B;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .modal-cart-info {
            background: linear-gradient(135deg, #3E2723 0%, #5d4e37 100%);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        .modal-cart-info i {
            font-size: 1.25rem;
            color: #D4AF37;
        }
        
        .modal-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .modal-btn {
            flex: 1;
            padding: 0.875rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .modal-btn-primary {
            background: linear-gradient(135deg, #8B5A2B 0%, #3E2723 100%);
            color: white;
            border: none;
        }
        
        .modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 90, 43, 0.3);
        }
        
        .modal-btn-secondary {
            background: #f5f2ed;
            color: #5d4e37;
            border: 2px solid #e0d5c7;
        }
        
        .modal-btn-secondary:hover {
            background: #e0d5c7;
        }
        
        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #8b7355;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .close-modal:hover {
            background: #f5f2ed;
            color: #3E2723;
        }
        
        /* Animation for cart icon bounce */
        @keyframes cartBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }
        
        .cart-bounce {
            animation: cartBounce 0.5s ease;
        }
    </style>
</head>
<body>

    <!-- Add to Cart Success Modal -->
    <div class="modal-overlay" id="addToCartModal">
        <div class="add-to-cart-modal">
            <button class="close-modal" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="modal-success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h3>Added to Cart!</h3>
            <p>This item has been added to your shopping cart.</p>
            
            <div class="modal-product" id="modalProduct">
                <!-- Product info will be inserted here -->
            </div>
            
            <div class="modal-cart-info">
                <i class="fas fa-shopping-cart"></i>
                <span>You have <strong id="modalCartCount">0</strong> item(s) in your cart</span>
            </div>
            
            <div class="modal-buttons">
                <a href="cart.php" class="modal-btn modal-btn-primary">
                    <i class="fas fa-shopping-bag"></i> View Cart
                </a>
                <button class="modal-btn modal-btn-secondary" onclick="closeModal()">
                    <i class="fas fa-arrow-left"></i> Continue Shopping
                </button>
            </div>
        </div>
    </div>

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
            <a href="shop.php" class="menu-item highlight">
                <i class="fas fa-store"></i>
                <span>Shop</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
            <a href="cart.php" class="menu-item">
                <i class="fas fa-shopping-cart"></i>
                <span>My Cart</span>
                <span class="badge" id="cartBadge"><?php echo $cart_count; ?></span>
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
            <a href="cart.php" class="cart-icon" id="headerCartIcon">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count" id="headerCartCount"><?php echo $cart_count; ?></span>
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

    <!-- Error Message -->
    <?php if ($error_msg): ?>
        <div class="alert">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="shop-container">
        
        <!-- Shop Header -->
        <div class="shop-header">
            <h1>Furniture Collection</h1>
            <p>Discover premium quality furniture for every room in your home</p>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" style="display: contents;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search furniture..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>

                <select name="category" class="category-filter" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php while($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                            <?php echo ($category_filter == $cat['category']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <?php if (!empty($category_filter) || !empty($search_term)): ?>
                    <a href="shop.php" class="clear-filters">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="products-grid">
            <?php if ($products->num_rows > 0): ?>
                <?php while($product = $products->fetch_assoc()): 
                    $stock_status = $product['stock'] > 10 ? 'high' : ($product['stock'] > 0 ? 'low' : 'out');
                ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     id="product-img-<?php echo $product['id']; ?>">
                            <?php else: ?>
                                <i class="fas fa-couch product-placeholder" id="product-icon-<?php echo $product['id']; ?>"></i>
                            <?php endif; ?>
                            <span class="category-tag"><?php echo htmlspecialchars($product['category']); ?></span>
                            <span class="stock-badge <?php echo $stock_status; ?>"><?php echo $product['stock']; ?> left</span>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name" id="product-name-<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h3>
                            <p class="product-description">
                                <?php echo htmlspecialchars(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : ''); ?>
                            </p>
                            <div class="product-footer">
                                <div class="product-price" id="product-price-<?php echo $product['id']; ?>">
                                    ₱<?php echo number_format($product['price'], 2); ?>
                                </div>
                                <?php if ($product['stock'] > 0): ?>
                                    <button class="add-to-cart" onclick="addToCart(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-cart-plus"></i> Add
                                    </button>
                                <?php else: ?>
                                    <button class="add-to-cart" disabled>
                                        <i class="fas fa-times"></i> Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-products">
                    <i class="fas fa-search"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your search or check back later for new arrivals!</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- JavaScript -->
    <script>
        // Hamburger Menu Functionality
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

        // Modal Functions
        function showModal(productData) {
            const modal = document.getElementById('addToCartModal');
            const productHtml = `
                <div class="modal-product-image">
                    ${productData.image ? 
                        `<img src="${productData.image}" alt="${productData.name}">` : 
                        `<i class="fas fa-couch"></i>`
                    }
                </div>
                <div class="modal-product-info">
                    <h4>${productData.name}</h4>
                    <div class="price">₱${productData.price}</div>
                </div>
            `;
            
            document.getElementById('modalProduct').innerHTML = productHtml;
            document.getElementById('modalCartCount').textContent = productData.cartCount;
            
            // Update cart counts
            document.getElementById('cartBadge').textContent = productData.cartCount;
            document.getElementById('headerCartCount').textContent = productData.cartCount;
            
            // Add bounce animation to cart icon
            const cartIcon = document.getElementById('headerCartIcon');
            cartIcon.classList.add('cart-bounce');
            setTimeout(() => cartIcon.classList.remove('cart-bounce'), 500);
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('addToCartModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Add to Cart with AJAX
        function addToCart(productId) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('action', 'add');
            urlParams.set('id', productId);
            
            fetch('shop.php?' + urlParams.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                // Parse the response to get updated cart info
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Get product info from the page
                const nameEl = document.getElementById('product-name-' + productId);
                const priceEl = document.getElementById('product-price-' + productId);
                const imgEl = document.getElementById('product-img-' + productId);
                const iconEl = document.getElementById('product-icon-' + productId);
                
                const productData = {
                    name: nameEl ? nameEl.textContent.trim() : 'Product',
                    price: priceEl ? priceEl.textContent.trim().replace('₱', '') : '0.00',
                    image: imgEl ? imgEl.src : (iconEl ? null : null),
                    cartCount: doc.getElementById('headerCartCount') ? 
                               doc.getElementById('headerCartCount').textContent : 
                               parseInt(document.getElementById('headerCartCount').textContent) + 1
                };
                
                showModal(productData);
            })
            .catch(error => {
                console.error('Error:', error);
                // Fallback: redirect normally
                window.location.href = 'shop.php?' + urlParams.toString();
            });
        }

        // Close modal when clicking outside
        document.getElementById('addToCartModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
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

        // Show modal if PHP triggered it (fallback for non-JS)
        <?php if ($show_modal && $modal_product): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const productData = {
                name: "<?php echo htmlspecialchars($modal_product['name']); ?>",
                price: "<?php echo number_format($modal_product['price'], 2); ?>",
                image: "<?php echo !empty($modal_product['image']) ? htmlspecialchars($modal_product['image']) : ''; ?>",
                cartCount: "<?php echo $cart_count; ?>"
            };
            showModal(productData);
        });
        <?php endif; ?>
    </script>

</body>
</html>