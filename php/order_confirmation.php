<?php
session_start();
include 'connection.php';

if (!isset($_GET['id'])) {
    header("Location: shop.php");
    exit();
}

$order_id = intval($_GET['id']);
$username = $_SESSION['user'] ?? '';

// Fetch order
$order_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND username = ?");
$order_stmt->bind_param("is", $order_id, $username);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: shop.php");
    exit();
}

// Fetch order items
$items_stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation - Furniplace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #faf8f5; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 3rem; border-radius: 20px; text-align: center; }
        .success-icon { font-size: 5rem; color: #4CAF50; margin-bottom: 1rem; }
        h1 { color: #3E2723; font-family: 'Playfair Display', serif; }
        .order-details { background: #f5f2ed; padding: 2rem; border-radius: 15px; margin: 2rem 0; text-align: left; }
        .btn { display: inline-block; background: #8B5A2B; color: white; padding: 1rem 2rem; border-radius: 10px; text-decoration: none; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <i class="fas fa-check-circle success-icon"></i>
        <h1>Order Placed Successfully!</h1>
        <p>Thank you for your purchase. Your order number is <strong>#<?php echo $order_id; ?></strong></p>
        
        <div class="order-details">
            <h3>Order Details</h3>
            <p><strong>Total Amount:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?></p>
            <p><strong>Payment Method:</strong> <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></p>
            <p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>
            
            <h4 style="margin-top: 1rem;">Items:</h4>
            <?php while($item = $items->fetch_assoc()): ?>
                <p><?php echo $item['product_name']; ?> x <?php echo $item['quantity']; ?> - ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
            <?php endwhile; ?>
        </div>
        
        <a href="shop.php" class="btn"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
        <a href="home.php" class="btn" style="background: #3E2723; margin-left: 1rem;"><i class="fas fa-home"></i> Go Home</a>
    </div>
</body>
</html>