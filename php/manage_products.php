<?php
session_start();
include 'connection.php';
include 'permission_helper.php';

if (isset($_SESSION['user'])) {
    $check_user = $conn->prepare("SELECT status FROM signinfo WHERE username = ?");
    $check_user->bind_param("s", $_SESSION['user']);
    $check_user->execute();
    $result = $check_user->get_result();
    if ($result->num_rows == 0) { session_destroy(); header("Location: login.php?error=account_deleted"); exit(); }
    $user = $result->fetch_assoc();
    if ($user['status'] === 'banned' || $user['status'] === 'deleted') { session_destroy(); header("Location: login.php?error=account_inactive"); exit(); }
}

if (!isset($_SESSION['account_type']) || !in_array($_SESSION['account_type'], ['admin', 'super_admin'])) {
    header("Location: login.php"); exit();
}

$current_user = $_SESSION['user'];
$account_type = $_SESSION['account_type'];
$is_super_admin = ($account_type === 'super_admin');
$is_admin = ($account_type === 'admin');

$refresh_stmt = $conn->prepare("SELECT control_level FROM signinfo WHERE username = ?");
$refresh_stmt->bind_param("s", $current_user);
$refresh_stmt->execute();
$refresh_result = $refresh_stmt->get_result()->fetch_assoc();
$control_level = $refresh_result['control_level'] ?? 'limited';
$_SESSION['control_level'] = $control_level;

$is_god_mode = ($account_type === 'super_admin' && $control_level === 'full');
$is_manager_mode = ($account_type === 'admin' && $control_level === 'full');
$is_viewer_mode = ($account_type === 'admin' && $control_level === 'limited');
$is_super_admin_limited = ($account_type === 'super_admin' && $control_level === 'limited');

$permissions = loadPermissions($conn, $current_user);
$can_add = hasPermission($permissions, 'can_add_product');
$can_edit = hasPermission($permissions, 'can_edit_product');
$can_delete = hasPermission($permissions, 'can_delete_product');

$message = ''; $error = ''; $success_redirect = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $product_id = !empty($_POST['product_id']) ? intval($_POST['product_id']) : null;
    if ($product_id && !$can_edit) { $error = "Access Denied: You don't have permission to edit products."; }
    elseif (!$product_id && !$can_add) { $error = "Access Denied: You don't have permission to add products."; }
    else {
        $name = trim($_POST['name']); $description = trim($_POST['description']);
        $price = floatval($_POST['price']); $category = trim($_POST['category']); $stock = intval($_POST['stock']);
        $image_path = '';
        if (!empty($_FILES['product_image']['name'])) {
            $target_dir = "uploads/products/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_extension, $allowed_types)) {
                $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                    if ($product_id && !empty($_POST['old_image'])) {
                        if (file_exists($_POST['old_image']) && strpos($_POST['old_image'], 'uploads/') === 0) unlink($_POST['old_image']);
                    }
                } else { $error = "Failed to upload image."; }
            } else { $error = "Invalid file type. Allowed: JPG, PNG, GIF, WEBP"; }
        } elseif ($product_id) { $image_path = $_POST['old_image'] ?? ''; }
        if (empty($error)) {
            $log_data = ['name' => $name, 'category' => $category, 'price' => $price, 'stock' => $stock, 'description' => $description];
            if ($product_id) {
                if (!empty($image_path)) { $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, category=?, stock=?, image=? WHERE id=?"); $stmt->bind_param("ssdsisi", $name, $description, $price, $category, $stock, $image_path, $product_id); }
                else { $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, category=?, stock=? WHERE id=?"); $stmt->bind_param("ssdsii", $name, $description, $price, $category, $stock, $product_id); }
                $message = "Product updated successfully!"; $log_action = 'updated'; $logged_product_id = $product_id;
            } else {
                $stmt = $conn->prepare("INSERT INTO products (name, description, price, category, stock, image, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdsiss", $name, $description, $price, $category, $stock, $image_path, $current_user);
                $message = "Product added successfully!"; $log_action = 'created';
            }
            if ($stmt->execute()) {
                if (!$product_id) $logged_product_id = $stmt->insert_id;
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $details = json_encode(['source' => 'Admin Panel', 'category' => $log_data['category'], 'price' => floatval($log_data['price']), 'stock' => intval($log_data['stock']), 'description' => $log_data['description']]);
                $log_stmt = $conn->prepare("INSERT INTO product_logs (username, action, product_id, product_name, ip_address, details) VALUES (?, ?, ?, ?, ?, ?)");
                $log_stmt->bind_param("ssisss", $current_user, $log_action, $logged_product_id, $log_data['name'], $ip, $details);
                $log_stmt->execute();
                $success_redirect = true;
            } else { $error = "Database error: " . $conn->error; }
        }
    }
}

if ($success_redirect) { header("Location: manage_products.php?success=" . ($product_id ? 'updated' : 'created')); exit(); }
if (isset($_GET['success'])) { $message = $_GET['success'] === 'updated' ? "Product updated successfully!" : "Product added successfully!"; }

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!$can_delete) { $error = "Access Denied: You don't have permission to delete products."; }
    else {
        $id = intval($_GET['delete']);
        $conn->begin_transaction();
        try {
            $img_stmt = $conn->prepare("SELECT id, name, category, price, stock, image FROM products WHERE id = ?");
            $img_stmt->bind_param("i", $id); $img_stmt->execute();
            $product_data = $img_stmt->get_result()->fetch_assoc();
            if (!$product_data) throw new Exception("Product not found");
            $product_name = $product_data['name']; $product_image = $product_data['image'];
            $delete_items = $conn->prepare("DELETE FROM order_items WHERE product_id = ?");
            $delete_items->bind_param("i", $id); $delete_items->execute();
            $deleted_items_count = $delete_items->affected_rows;
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $details = json_encode(['source' => 'Admin Panel', 'category' => $product_data['category'], 'price' => floatval($product_data['price']), 'stock' => intval($product_data['stock']), 'note' => 'Product permanently deleted']);
            $log_stmt = $conn->prepare("INSERT INTO product_logs (username, action, product_id, product_name, ip_address, details) VALUES (?, 'deleted', ?, ?, ?, ?)");
            $log_stmt->bind_param("sisss", $current_user, $id, $product_name, $ip, $details); $log_stmt->execute();
            $del_stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $del_stmt->bind_param("i", $id);
            if ($del_stmt->execute()) {
                $conn->commit();
                if (!empty($product_image) && file_exists($product_image) && strpos($product_image, 'uploads/') === 0) unlink($product_image);
                $message = "Product '$product_name' deleted!";
                if ($deleted_items_count > 0) $message .= " (Removed from $deleted_items_count order(s))";
            } else throw new Exception("Failed to delete product");
        } catch (Exception $e) { $conn->rollback(); $error = "Delete failed: " . $e->getMessage(); }
    }
}

$products = $conn->query("SELECT * FROM products ORDER BY id DESC");
$total_products = $products->num_rows;
$low_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock < 5")->fetch_assoc()['count'];
$total_value = $conn->query("SELECT SUM(price * stock) as total FROM products")->fetch_assoc()['total'] ?? 0;
$out_of_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock = 0")->fetch_assoc()['count'];

$edit_product = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit']) && !$success_redirect) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $edit_stmt->bind_param("i", $edit_id); $edit_stmt->execute();
    $result = $edit_stmt->get_result()->fetch_assoc();
    if ($result) $edit_product = $result;
}

$existing_categories = [];
$cat_query = $conn->query("SELECT DISTINCT category FROM products ORDER BY category");
if ($cat_query) { while($row = $cat_query->fetch_assoc()) { if (!empty($row['category'])) $existing_categories[] = $row['category']; } }

$profile_stmt = $conn->prepare("SELECT fname, lname, profile_pic, email FROM signinfo WHERE username = ?");
$profile_stmt->bind_param("s", $current_user); $profile_stmt->execute();
$profile_data = $profile_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace — Product Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #C9A84C;
            --gold-light: #E8C97A;
            --gold-dark: #A07830;
            --dark: #0F0F1A;
            --dark-2: #161625;
            --dark-3: #1E1E30;
            --dark-4: #252538;
            --dark-5: #2E2E45;
            --text-primary: #F0EDE8;
            --text-secondary: #9B97A8;
            --text-muted: #6B6878;
            --accent-blue: #4E7CFF;
            --accent-green: #3ECF8E;
            --accent-orange: #FF8C42;
            --accent-red: #FF4757;
            --border: rgba(201,168,76,0.12);
            --border-subtle: rgba(255,255,255,0.06);
            --sidebar-w: 270px;
            --radius: 14px;
            --radius-sm: 8px;
            --shadow: 0 8px 32px rgba(0,0,0,0.4);
            --shadow-sm: 0 4px 16px rgba(0,0,0,0.25);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--dark); color: var(--text-primary); min-height: 100vh; display: flex; overflow-x: hidden; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--dark-2); }
        ::-webkit-scrollbar-thumb { background: var(--dark-5); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gold-dark); }

        /* ─── SIDEBAR ─── */
        .sidebar { width: var(--sidebar-w); background: var(--dark-2); border-right: 1px solid var(--border); position: fixed; height: 100vh; display: flex; flex-direction: column; z-index: 100; overflow-y: auto; }
        .sidebar-brand { padding: 1.75rem 1.5rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
        .sidebar-brand-logo { display: flex; align-items: center; gap: 0.75rem; }
        .brand-icon { width: 38px; height: 38px; background: linear-gradient(135deg, var(--gold), var(--gold-dark)); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: var(--dark); flex-shrink: 0; box-shadow: 0 4px 12px rgba(201,168,76,0.3); }
        .brand-text h2 { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--gold-light); }
        .brand-text small { font-size: 0.7rem; color: var(--text-muted); letter-spacing: 0.5px; }
        .sidebar-profile { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 0.875rem; }
        .profile-avatar { width: 44px; height: 44px; border-radius: 50%; border: 2px solid var(--gold); overflow: hidden; flex-shrink: 0; position: relative; box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-avatar-placeholder { width: 100%; height: 100%; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 700; color: var(--dark); }
        .profile-status { position: absolute; bottom: 1px; right: 1px; width: 10px; height: 10px; background: var(--accent-green); border-radius: 50%; border: 2px solid var(--dark-2); }
        .profile-info { flex: 1; min-width: 0; }
        .profile-name { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .profile-sub { font-size: 0.7rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.2rem; margin-top: 0.1rem; }
        .profile-email { font-size: 0.68rem; color: var(--text-muted); display: flex; align-items: flex-start; gap: 0.2rem; margin-top: 0.1rem; word-break: break-all; line-height: 1.3; }
        .role-pill { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.6rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; margin-top: 0.3rem; }
        .role-pill.super { background: rgba(201,168,76,0.15); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.3); }
        .role-pill.admin { background: rgba(255,140,66,0.15); color: var(--accent-orange); border: 1px solid rgba(255,140,66,0.3); }
        .nav-section { padding: 1rem 0; flex: 1; }
        .nav-label { padding: 0.6rem 1.5rem 0.3rem; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); font-weight: 600; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 1.5rem; color: var(--text-secondary); text-decoration: none; font-size: 0.875rem; transition: all 0.2s; border-left: 2px solid transparent; }
        .nav-item:hover { background: rgba(255,255,255,0.04); color: var(--text-primary); border-left-color: var(--border); }
        .nav-item.active { background: rgba(201,168,76,0.08); color: var(--gold-light); border-left-color: var(--gold); font-weight: 500; }
        .nav-item i { width: 18px; text-align: center; font-size: 0.875rem; }
        .nav-item.danger { color: var(--accent-red); }

        /* ─── MAIN ─── */
        .main { flex: 1; margin-left: var(--sidebar-w); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: var(--dark-2); border-bottom: 1px solid var(--border-subtle); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; }
        .topbar-left h1 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--text-primary); }
        .topbar-left p { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem; }
        .topbar-right { display: flex; align-items: center; gap: 0.75rem; }
        .badge-pill { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.875rem; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
        .badge-pill.super { background: rgba(201,168,76,0.12); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.25); }
        .badge-pill.admin { background: rgba(255,140,66,0.12); color: var(--accent-orange); border: 1px solid rgba(255,140,66,0.25); }
        .page-content { padding: 2rem; flex: 1; }

        /* ─── ALERTS ─── */
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.875rem; font-weight: 500; }
        .alert-success { background: rgba(62,207,142,0.1); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.25); }
        .alert-error { background: rgba(255,71,87,0.1); color: var(--accent-red); border: 1px solid rgba(255,71,87,0.25); }

        /* ─── PERMISSION NOTICE ─── */
        .perm-notice { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.85rem; border-left: 3px solid; }
        .perm-notice.has-perms { background: rgba(62,207,142,0.06); border-color: var(--accent-green); color: var(--accent-green); }
        .perm-notice.view-only { background: rgba(78,124,255,0.06); border-color: var(--accent-blue); color: var(--accent-blue); }

        /* ─── STATS ─── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
        .stat-card { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1.25rem; display: flex; align-items: center; gap: 1rem; transition: all 0.25s; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--card-accent, var(--gold)); opacity: 0.6; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); border-color: var(--border); }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .stat-icon.blue { background: rgba(78,124,255,0.12); color: var(--accent-blue); }
        .stat-icon.orange { background: rgba(255,140,66,0.12); color: var(--accent-orange); }
        .stat-icon.green { background: rgba(62,207,142,0.12); color: var(--accent-green); }
        .stat-icon.red { background: rgba(255,71,87,0.12); color: var(--accent-red); }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--text-primary); line-height: 1; font-family: 'Playfair Display', serif; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }

        /* ─── ACTION BAR ─── */
        .action-bar { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1rem 1.25rem; margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .search-box { position: relative; flex: 1; min-width: 220px; max-width: 380px; }
        .search-box input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; transition: all 0.2s; }
        .search-box input:focus { outline: none; border-color: var(--gold-dark); background: var(--dark-5); }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.8rem; }
        .action-bar-right { display: flex; align-items: center; gap: 0.75rem; }
        .filter-chips { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.35rem 0.75rem; border-radius: 99px; font-size: 0.75rem; font-weight: 500; cursor: pointer; border: 1px solid var(--border-subtle); color: var(--text-secondary); background: transparent; transition: all 0.2s; }
        .chip:hover, .chip.active { background: rgba(201,168,76,0.1); color: var(--gold-light); border-color: rgba(201,168,76,0.3); }
        .btn-add { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); color: var(--dark); border: none; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.25s; text-decoration: none; white-space: nowrap; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(201,168,76,0.35); }

        /* ─── PRODUCTS GRID ─── */
        .products-wrapper { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); overflow: hidden; }
        .products-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; background: var(--dark-4); }
        .products-header-title { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
        .products-count { background: var(--dark-5); color: var(--text-secondary); padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.72rem; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; padding: 1.25rem; }
        .product-card { background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius); overflow: hidden; transition: all 0.3s; position: relative; }
        .product-card:hover { border-color: rgba(201,168,76,0.3); transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.4); }
        .product-img { height: 180px; background: var(--dark-5); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
        .product-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
        .product-card:hover .product-img img { transform: scale(1.05); }
        .product-img-placeholder { font-size: 3.5rem; color: var(--dark-3); }
        .product-cat-badge { position: absolute; top: 0.75rem; left: 0.75rem; background: rgba(15,15,26,0.85); backdrop-filter: blur(6px); color: var(--gold-light); padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid rgba(201,168,76,0.2); }
        .product-stock-badge { position: absolute; top: 0.75rem; right: 0.75rem; padding: 0.2rem 0.55rem; border-radius: 6px; font-size: 0.65rem; font-weight: 700; }
        .stock-high { background: rgba(62,207,142,0.15); color: var(--accent-green); }
        .stock-medium { background: rgba(255,140,66,0.15); color: var(--accent-orange); }
        .stock-low { background: rgba(255,71,87,0.15); color: var(--accent-red); }
        .product-body { padding: 1rem 1.1rem 0.75rem; }
        .product-name { font-family: 'Playfair Display', serif; font-size: 1rem; color: var(--text-primary); margin-bottom: 0.5rem; line-height: 1.3; }
        .product-desc { font-size: 0.75rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 0.75rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .product-price-row { display: flex; align-items: center; justify-content: space-between; }
        .product-price { font-size: 1.15rem; font-weight: 700; color: var(--gold-light); font-family: 'Playfair Display', serif; }
        .product-stock-text { font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.3rem; }
        .product-footer { padding: 0.75rem 1.1rem; border-top: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 0.5rem; }
        .btn-action { display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.45rem 0.875rem; border-radius: var(--radius-sm); font-size: 0.78rem; font-weight: 500; cursor: pointer; border: 1px solid transparent; text-decoration: none; transition: all 0.2s; font-family: 'DM Sans', sans-serif; }
        .btn-edit { background: rgba(78,124,255,0.1); color: var(--accent-blue); border-color: rgba(78,124,255,0.2); }
        .btn-edit:hover { background: var(--accent-blue); color: white; }
        .btn-delete { background: rgba(255,71,87,0.1); color: var(--accent-red); border-color: rgba(255,71,87,0.2); }
        .btn-delete:hover { background: var(--accent-red); color: white; }
        .btn-disabled { background: rgba(255,255,255,0.03); color: var(--text-muted); border-color: var(--border-subtle); cursor: not-allowed; opacity: 0.5; }
        .product-id { margin-left: auto; font-family: 'JetBrains Mono', monospace; font-size: 0.65rem; color: var(--text-muted); background: var(--dark-5); padding: 0.2rem 0.4rem; border-radius: 4px; }

        /* empty state */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); grid-column: 1/-1; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.15; display: block; }
        .empty-state p { font-size: 0.9rem; }

        /* ─── MODAL ─── */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 2000; display: none; align-items: center; justify-content: center; padding: 1.5rem; backdrop-filter: blur(6px); }
        .modal-overlay.active { display: flex !important; }
        .modal { background: var(--dark-3); border: 1px solid var(--border); border-radius: 20px; width: 100%; max-width: 620px; max-height: 92vh; overflow-y: auto; box-shadow: 0 30px 70px rgba(0,0,0,0.6); animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { background: linear-gradient(135deg, var(--dark-4), var(--dark-5)); padding: 1.5rem 1.75rem; border-bottom: 2px solid var(--gold-dark); display: flex; justify-content: space-between; align-items: center; border-radius: 20px 20px 0 0; position: sticky; top: 0; z-index: 10; }
        .modal-header-left { display: flex; align-items: center; gap: 0.875rem; }
        .modal-header-icon { width: 42px; height: 42px; background: rgba(201,168,76,0.15); border: 2px solid rgba(201,168,76,0.3); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--gold-light); font-size: 1.1rem; }
        .modal-header h2 { font-family: 'Playfair Display', serif; color: var(--gold-light); font-size: 1.3rem; margin: 0; }
        .modal-header p { color: var(--text-muted); font-size: 0.78rem; margin: 0.2rem 0 0 0; }
        .modal-close { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.07); border: 1px solid var(--border-subtle); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; transition: all 0.2s; }
        .modal-close:hover { background: rgba(255,71,87,0.15); color: var(--accent-red); transform: rotate(90deg); }
        .modal-body { padding: 1.75rem; }
        .form-section { margin-bottom: 1.5rem; }
        .form-section-title { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 600; margin-bottom: 0.875rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border-subtle); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; color: var(--text-secondary); font-size: 0.8rem; font-weight: 500; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.7rem 0.875rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; transition: all 0.2s; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--gold-dark); background: var(--dark-5); }
        .form-group input::placeholder, .form-group textarea::placeholder { color: var(--text-muted); }
        .form-group textarea { resize: vertical; min-height: 90px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-hint { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.3rem; }
        .file-upload-area { border: 2px dashed var(--border-subtle); border-radius: var(--radius-sm); padding: 1.25rem; text-align: center; cursor: pointer; transition: all 0.2s; position: relative; background: var(--dark-4); }
        .file-upload-area:hover { border-color: var(--gold-dark); background: var(--dark-5); }
        .file-upload-area input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .file-upload-area i { font-size: 1.5rem; color: var(--text-muted); margin-bottom: 0.5rem; display: block; }
        .file-upload-area span { font-size: 0.8rem; color: var(--text-muted); }
        .image-preview { width: 100%; height: 140px; object-fit: cover; border-radius: var(--radius-sm); margin-top: 0.75rem; display: none; border: 1px solid var(--border-subtle); }
        .image-preview.active { display: block; }
        .modal-footer { padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--dark-4); border-radius: 0 0 20px 20px; }
        .btn-cancel { padding: 0.7rem 1.25rem; background: transparent; border: 1px solid var(--border-subtle); color: var(--text-secondary); border-radius: var(--radius-sm); cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; }
        .btn-cancel:hover { background: var(--dark-5); color: var(--text-primary); }
        .btn-save { padding: 0.7rem 1.5rem; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); color: var(--dark); border: none; border-radius: var(--radius-sm); cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.25s; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(201,168,76,0.3); }

        @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .stats-grid { grid-template-columns: 1fr; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-logo">
            <div class="brand-icon"><i class="fas fa-<?php echo $is_super_admin ? 'shield-alt' : 'user-shield'; ?>"></i></div>
            <div class="brand-text">
                <h2>Furniplace</h2>
                <small><?php echo $is_super_admin ? 'Super Admin Console' : 'Admin Panel'; ?></small>
            </div>
        </div>
    </div>

    <div class="sidebar-profile">
        <div class="profile-avatar">
            <?php if (!empty($profile_data['profile_pic']) && file_exists($profile_data['profile_pic'])): ?>
                <img src="<?php echo htmlspecialchars($profile_data['profile_pic']); ?>" alt="">
            <?php else: ?>
                <div class="profile-avatar-placeholder"><?php echo strtoupper(substr($profile_data['fname'] ?? $current_user, 0, 1)); ?></div>
            <?php endif; ?>
            <div class="profile-status"></div>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?php echo htmlspecialchars(($profile_data['fname'] ?? '') . ' ' . ($profile_data['lname'] ?? '')); ?></div>
            <div class="profile-sub"><i class="fas fa-at" style="font-size:0.6rem;"></i> <?php echo htmlspecialchars($current_user); ?></div>
            <div class="profile-email"><i class="fas fa-envelope" style="font-size:0.6rem; flex-shrink:0; margin-top:1px;"></i> <?php echo htmlspecialchars($profile_data['email'] ?? ''); ?></div>
            <div class="role-pill <?php echo $is_super_admin ? 'super' : 'admin'; ?>">
                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="font-size:0.55rem;"></i>
                <?php echo $is_super_admin ? 'Super Admin' : 'Admin'; ?>
            </div>
        </div>
    </div>

    <nav class="nav-section">
        <div class="nav-label">Dashboard</div>
        <a href="super_admin_dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Activity Monitor</a>
        <a href="manage_products.php" class="nav-item active"><i class="fas fa-box"></i> Manage Products</a>
        <a href="user_management.php" class="nav-item"><i class="fas fa-users"></i> User Management</a>
        <a href="admin_management.php" class="nav-item"><i class="fas fa-user-shield"></i> Admin Management</a>
        <a href="product_activity_log.php" class="nav-item"><i class="fas fa-clipboard-list"></i> Product Activity</a>
        <a href="admin_activity_logs.php" class="nav-item"><i class="fas fa-history"></i> Admin Activity Logs</a>
        <?php if ($is_super_admin): ?>
        <a href="view_deletion_proofs.php" class="nav-item"><i class="fas fa-camera"></i> Deletion Proofs</a>
        <?php endif; ?>
        <div class="nav-label" style="margin-top:0.5rem;">System</div>
        <a href="home.php" class="nav-item"><i class="fas fa-store"></i> View Store</a>
        <a href="admin_settings.php" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
        <a href="logout.php" class="nav-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- ═══ MAIN ═══ -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1>Product Management</h1>
            <p>
                <?php if ($is_super_admin && $control_level === 'full'): ?>Full inventory control — add, edit &amp; delete<?php elseif ($can_add || $can_edit): ?>Create and edit products<?php else: ?>View-only access to products<?php endif; ?>
            </p>
        </div>
        <div class="topbar-right">
            <span class="badge-pill <?php echo $is_super_admin ? 'super' : 'admin'; ?>">
                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="font-size:0.65rem;"></i>
                <?php echo strtoupper(str_replace('_', ' ', $account_type)); ?> &nbsp;·&nbsp;
                <?php if ($control_level === 'full') echo 'FULL ACCESS'; elseif ($control_level === 'limited') echo 'LIMITED'; else echo strtoupper($control_level); ?>
            </span>
        </div>
    </div>

    <div class="page-content">

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$is_super_admin):
            $product_permissions = [];
            if ($can_add) $product_permissions[] = 'add';
            if ($can_edit) $product_permissions[] = 'edit';
            if ($can_delete) $product_permissions[] = 'delete';
            $has_perms = !empty($product_permissions);
            $last = $has_perms ? array_pop($product_permissions) : '';
            $perm_text = $has_perms ? ('You can ' . (count($product_permissions) ? implode(', ', $product_permissions) . ' and ' : '') . $last . ' products' . (!$can_delete && ($can_add || $can_edit) ? ' (no delete)' : '')) : 'View-only access to products';
        ?>
        <div class="perm-notice <?php echo $has_perms ? 'has-perms' : 'view-only'; ?>">
            <i class="fas fa-<?php echo $has_perms ? 'check-circle' : 'info-circle'; ?>"></i>
            <span><strong>Product Access:</strong> <?php echo $perm_text; ?>.</span>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="--card-accent: var(--accent-blue);">
                <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
                <div><div class="stat-value"><?php echo $total_products; ?></div><div class="stat-label">Total Products</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-orange);">
                <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
                <div><div class="stat-value"><?php echo $low_stock; ?></div><div class="stat-label">Low Stock Items</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-red);">
                <div class="stat-icon red"><i class="fas fa-ban"></i></div>
                <div><div class="stat-value"><?php echo $out_of_stock; ?></div><div class="stat-label">Out of Stock</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-green);">
                <div class="stat-icon green"><i class="fas fa-peso-sign"></i></div>
                <div><div class="stat-value" style="font-size:1.1rem;">₱<?php echo number_format($total_value, 0); ?></div><div class="stat-label">Inventory Value</div></div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search products by name..." onkeyup="searchProducts()">
            </div>
            <div class="action-bar-right">
                <div class="filter-chips">
                    <button class="chip active" onclick="filterCategory('all', this)"><i class="fas fa-th" style="font-size:0.65rem;"></i> All</button>
                    <?php foreach (array_slice($existing_categories, 0, 5) as $cat): ?>
                    <button class="chip" onclick="filterCategory('<?php echo strtolower(htmlspecialchars($cat)); ?>', this)"><?php echo htmlspecialchars($cat); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php if ($can_add): ?>
                <button class="btn-add" onclick="openModal()"><i class="fas fa-plus"></i> Add Product</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Products -->
        <div class="products-wrapper">
            <div class="products-header">
                <div class="products-header-title">
                    <i class="fas fa-box" style="color: var(--gold); font-size:0.85rem;"></i>
                    Products
                    <span class="products-count" id="visibleCount"><?php echo $total_products; ?> items</span>
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:0.4rem;">
                    <i class="fas fa-sort-amount-down" style="font-size:0.7rem;"></i> Newest first
                </div>
            </div>
            <div class="products-grid" id="productsGrid">
                <?php
                $products->data_seek(0);
                if ($products->num_rows > 0):
                    while($product = $products->fetch_assoc()):
                        $sv = $product['stock'] ?? 0;
                        $sc = $sv == 0 ? 'stock-low' : ($sv < 5 ? 'stock-low' : ($sv < 20 ? 'stock-medium' : 'stock-high'));
                        $st = $sv == 0 ? 'Out of Stock' : ($sv < 5 ? 'Low Stock' : ($sv < 20 ? 'Medium' : 'In Stock'));
                        $cat = strtolower($product['category'] ?? 'uncategorized');
                ?>
                <div class="product-card" data-name="<?php echo strtolower(htmlspecialchars($product['name'])); ?>" data-cat="<?php echo $cat; ?>">
                    <div class="product-img">
                        <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <i class="fas fa-couch product-img-placeholder"></i>
                        <?php endif; ?>
                        <span class="product-cat-badge"><?php echo htmlspecialchars($product['category'] ?? 'General'); ?></span>
                        <span class="product-stock-badge <?php echo $sc; ?>"><?php echo $st; ?></span>
                    </div>
                    <div class="product-body">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        <?php if (!empty($product['description'])): ?>
                        <div class="product-desc"><?php echo htmlspecialchars($product['description']); ?></div>
                        <?php endif; ?>
                        <div class="product-price-row">
                            <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-stock-text">
                                <i class="fas fa-box" style="font-size:0.6rem;"></i>
                                <?php echo $sv; ?> units
                            </div>
                        </div>
                    </div>
                    <div class="product-footer">
                        <?php if ($can_edit): ?>
                        <a href="?edit=<?php echo $product['id']; ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i> Edit</a>
                        <?php else: ?>
                        <button class="btn-action btn-disabled" disabled><i class="fas fa-edit"></i> Edit</button>
                        <?php endif; ?>

                        <?php if ($can_delete): ?>
                        <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>')"><i class="fas fa-trash"></i> Delete</button>
                        <?php else: ?>
                        <button class="btn-action btn-disabled" disabled title="No delete permission"><i class="fas fa-lock"></i> Delete</button>
                        <?php endif; ?>

                        <span class="product-id">#<?php echo $product['id']; ?></span>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <div class="empty-state"><i class="fas fa-box-open"></i><p>No products yet. Add your first product!</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /page-content -->
</div><!-- /main -->

<!-- ═══ MODAL ═══ -->
<div class="modal-overlay" id="productModal" <?php echo isset($edit_product['id']) ? 'style="display:flex;"' : ''; ?>>
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-header-icon">
                    <i class="fas fa-<?php echo isset($edit_product['id']) ? 'edit' : 'plus'; ?>"></i>
                </div>
                <div>
                    <h2><?php echo isset($edit_product['id']) ? 'Edit Product' : 'New Product'; ?></h2>
                    <p><?php echo isset($edit_product['id']) ? 'Update product details below' : 'Fill in the details to add a new product'; ?></p>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="product_id" value="<?php echo $edit_product['id'] ?? ''; ?>">
                <input type="hidden" name="old_image" value="<?php echo $edit_product['image'] ?? ''; ?>">

                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-info-circle" style="color:var(--gold);"></i> Basic Info</div>
                    <div class="form-group">
                        <label>Product Name <span style="color:var(--accent-red);">*</span></label>
                        <input type="text" name="name" required placeholder="e.g., Modern Leather Sofa" value="<?php echo htmlspecialchars($edit_product['name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Describe this product..."><?php echo htmlspecialchars($edit_product['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-tag" style="color:var(--gold);"></i> Pricing &amp; Stock</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Price (₱) <span style="color:var(--accent-red);">*</span></label>
                            <input type="number" name="price" step="0.01" min="0" required placeholder="0.00" value="<?php echo $edit_product['price'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Stock Quantity <span style="color:var(--accent-red);">*</span></label>
                            <input type="number" name="stock" min="0" required placeholder="0" value="<?php echo $edit_product['stock'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Category <span style="color:var(--accent-red);">*</span></label>
                        <input type="text" name="category" required placeholder="e.g., Sofa, Table, Chair" value="<?php echo htmlspecialchars($edit_product['category'] ?? ''); ?>">
                        <?php if (!empty($existing_categories)): ?>
                        <div class="form-hint">Existing: <?php echo implode(', ', array_slice($existing_categories, 0, 6)); ?><?php if (count($existing_categories) > 6) echo '…'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-image" style="color:var(--gold);"></i> Product Image</div>
                    <div class="file-upload-area">
                        <input type="file" name="product_image" accept="image/*" id="imageInput" onchange="previewImage(this)">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span id="fileLabel">Click or drag to upload image</span>
                        <div class="form-hint" style="margin-top:0.3rem;">JPG, PNG, GIF, WEBP supported</div>
                    </div>
                    <?php if (!empty($edit_product['image'])): ?>
                    <img id="imagePreview" src="<?php echo htmlspecialchars($edit_product['image']); ?>" class="image-preview active" alt="Preview">
                    <?php else: ?>
                    <img id="imagePreview" class="image-preview" alt="Preview">
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" name="save_product" class="btn-save">
                    <i class="fas fa-<?php echo isset($edit_product['id']) ? 'save' : 'plus-circle'; ?>"></i>
                    <?php echo isset($edit_product['id']) ? 'Update Product' : 'Save Product'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    const m = document.getElementById('productModal');
    m.classList.add('active'); m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    const m = document.getElementById('productModal');
    m.classList.remove('active'); m.style.display = 'none';
    document.body.style.overflow = 'auto';
    <?php if (!empty($edit_product) && isset($edit_product['id'])): ?>
    window.location.href = 'manage_products.php';
    <?php endif; ?>
}
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const label = document.getElementById('fileLabel');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.classList.add('active'); label.textContent = input.files[0].name; }
        reader.readAsDataURL(input.files[0]);
    }
}
function searchProducts() {
    const filter = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.product-card');
    let visible = 0;
    cards.forEach(c => {
        const show = c.dataset.name.includes(filter);
        c.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('visibleCount').textContent = visible + ' items';
}
function filterCategory(cat, btn) {
    document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    const cards = document.querySelectorAll('.product-card');
    let visible = 0;
    cards.forEach(c => {
        const show = cat === 'all' || c.dataset.cat === cat;
        c.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('visibleCount').textContent = visible + ' items';
}
function confirmDelete(id, name) {
    if (confirm(`Delete "${name}"?\n\nThis cannot be undone.`)) window.location.href = `?delete=${id}`;
}
document.getElementById('productModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });
<?php if (isset($edit_product['id']) || $error): ?>window.onload = () => openModal();<?php endif; ?>
</script>
</body>
</html>