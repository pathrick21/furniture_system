<?php
session_start();
include 'connection.php';
include 'permission_helper.php';

// Authentication check
if (!isset($_SESSION['user']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['user'];
$account_type = $_SESSION['account_type'];
$is_super_admin = ($account_type === 'super_admin');
$is_admin = ($account_type === 'admin');

if (!$is_super_admin && !$is_admin) {
    header("Location: login.php");
    exit();
}

// Load permissions
$permissions = loadPermissions($conn, $current_user);
$can_delete_user = hasPermission($permissions, 'can_delete_user');
$can_edit_user = hasPermission($permissions, 'can_edit_user');

// Handle Restore
if (isset($_GET['restore']) && !empty($_GET['restore'])) {
    $user_id = $_GET['restore'];
    $stmt = $conn->prepare("UPDATE signinfo SET status='active' WHERE id_main=? AND status='deleted'");
    $stmt->bind_param("s", $user_id);
    if ($stmt->execute()) {
        header("Location: user_archive.php?restored=1");
        exit();
    }
}

// Handle Permanent Delete
if (isset($_GET['permanent_delete']) && !empty($_GET['permanent_delete']) && $can_delete_user) {
    $user_id = $_GET['permanent_delete'];
    
    // Get username first
    $user_stmt = $conn->prepare("SELECT username FROM signinfo WHERE id_main=?");
    $user_stmt->bind_param("s", $user_id);
    $user_stmt->execute();
    $user_data = $user_stmt->get_result()->fetch_assoc();
    
    if ($user_data) {
        $username = $user_data['username'];
        
        // Delete related data
        $conn->query("DELETE FROM orders WHERE username='".$conn->real_escape_string($username)."'");
        $conn->query("DELETE FROM user_sessions WHERE username='".$conn->real_escape_string($username)."'");
        $conn->query("DELETE FROM user_logs WHERE username='".$conn->real_escape_string($username)."'");
        
        // Delete user
        $del_stmt = $conn->prepare("DELETE FROM signinfo WHERE id_main=?");
        $del_stmt->bind_param("s", $user_id);
        $del_stmt->execute();
        
        header("Location: user_archive.php?deleted=1");
        exit();
    }
}

// Get archived users - using deleted_at for the date
$archive_sql = "SELECT id_main, fname, lname, mi, Ename, username, email, created_at, deleted_at 
                FROM signinfo 
                WHERE status = 'deleted' 
                ORDER BY deleted_at DESC";
$archive_result = $conn->query($archive_sql);
$archive_count = $archive_result->num_rows;

$message = '';
if (isset($_GET['restored'])) $message = "User restored successfully!";
if (isset($_GET['deleted'])) $message = "User permanently deleted!";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Archive - Furniplace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f0ebe3;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: <?php echo $is_super_admin ? 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)' : 'linear-gradient(135deg, #2c3e50 0%, #1a252f 100%)'; ?>;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .brand {
            padding: 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }

        .brand h2 {
            font-family: 'Playfair Display', serif;
            color: <?php echo $is_super_admin ? '#D4AF37' : '#e67e22'; ?>;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-section {
            padding: 1rem 0;
        }

        .nav-header {
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #8b7355;
            font-weight: 600;
        }

        .nav-item {
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            background: <?php echo $is_super_admin ? 'rgba(212, 175, 55, 0.1)' : 'rgba(230, 126, 34, 0.1)'; ?>;
            color: <?php echo $is_super_admin ? '#D4AF37' : '#e67e22'; ?>;
            border-left-color: <?php echo $is_super_admin ? '#D4AF37' : '#e67e22'; ?>;
        }

        .main {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-family: 'Playfair Display', serif;
            color: #1a1a2e;
            font-size: 1.75rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #E8F5E9;
            color: #2E7D32;
            border: 1px solid #A5D6A7;
        }

        .table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .archive-table {
            width: 100%;
            border-collapse: collapse;
        }

        .archive-table th {
            background: #f5f2ed;
            padding: 1rem;
            text-align: left;
            color: #5d4e37;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .archive-table td {
            padding: 1rem;
            border-bottom: 1px solid #f5f2ed;
            font-size: 0.875rem;
        }

        .archive-table tr:hover {
            background: #faf8f5;
        }

        .deleted-badge {
            background: #C62828;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-restore {
            background: #E8F5E9;
            color: #2E7D32;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-restore:hover {
            background: #2E7D32;
            color: white;
        }

        .btn-permanent-delete {
            background: #FFEBEE;
            color: #C62828;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s;
            margin-left: 0.5rem;
        }

        .btn-permanent-delete:hover {
            background: #C62828;
            color: white;
        }

        .btn-back {
            background: #f5f2ed;
            color: #5d4e37;
            padding: 0.875rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #e0d5c7;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #8b7355;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
</head>
<body>

    <!-- Sidebar (copy your sidebar code here) -->
    <aside class="sidebar">
        <!-- ... your existing sidebar code ... -->
    </aside>

    <main class="main">
        <div class="header">
            <div>
                <h1><i class="fas fa-archive" style="color: #8b7355; margin-right: 0.75rem;"></i>User Archive</h1>
                <p style="color: #8b7355; margin-top: 0.25rem;">View and manage deleted user accounts</p>
            </div>
            <a href="user_management.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to User Management
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table class="archive-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Deleted Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($archive_result && $archive_result->num_rows > 0): ?>
                        <?php while($archived = $archive_result->fetch_assoc()): 
                            $fullName = $archived['fname'] . ' ' . 
                                       (!empty($archived['mi']) ? $archived['mi'] . '. ' : '') . 
                                       $archived['lname'] . 
                                       (!empty($archived['Ename']) ? ' ' . $archived['Ename'] : '');
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 35px; height: 35px; border-radius: 50%; background: #C62828; display: flex; align-items: center; justify-content: center; color: white;">
                                        <i class="fas fa-user-slash"></i>
                                    </div>
                                    <strong><?php echo htmlspecialchars($fullName); ?></strong>
                                </div>
                            </td>
                            <td>@<?php echo htmlspecialchars($archived['username']); ?></td>
                            <td><?php echo htmlspecialchars($archived['email']); ?></td>
                            <td>
                                <span class="deleted-badge">
                                    <i class="fas fa-calendar-alt"></i> 
                                    <?php 
                                    if (!empty($archived['deleted_at'])) {
                                        echo date('M d, Y', strtotime($archived['deleted_at']));
                                    } else {
                                        echo date('M d, Y', strtotime($archived['created_at'])) . ' (approx)';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="?restore=<?php echo $archived['id_main']; ?>" 
                                       class="btn-restore"
                                       onclick="return confirm('Restore this user?')">
                                        <i class="fas fa-undo-alt"></i> Restore
                                    </a>
                                    <?php if ($can_delete_user): ?>
                                    <a href="?permanent_delete=<?php echo $archived['id_main']; ?>" 
                                       class="btn-permanent-delete"
                                       onclick="return confirm('Permanently delete this user? This cannot be undone!')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <i class="fas fa-archive"></i>
                                <p>No archived users found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>