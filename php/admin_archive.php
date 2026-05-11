<?php
session_start();
include 'connection.php';
include 'permission_helper.php';

// Authentication and permission checks...

// Get archived admins
$archived_admins = $conn->query("
    SELECT * FROM deleted_admins_archive 
    WHERE restored_at IS NULL 
    ORDER BY deleted_at DESC
");
?>

<!-- Display archived admins with restore buttons -->
<table>
    <?php while($archived = $archived_admins->fetch_assoc()): ?>
    <tr>
        <td><?php echo $archived['fname'] . ' ' . $archived['lname']; ?></td>
        <td><?php echo $archived['username']; ?></td>
        <td>Deleted: <?php echo $archived['deleted_at']; ?></td>
        <td>
            <a href="?action=restore&id=<?php echo $archived['id']; ?>" 
               class="btn-restore">Restore Account</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>