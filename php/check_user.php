<?php
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $field = $_POST['field'];
    $value = $_POST['value'];

    $allowedFields = ['id_main', 'user', 'email'];
    if (!in_array($field, $allowedFields)) {
        echo json_encode(['exists' => false]);
        exit;
    }

    try {
        $sql = "SELECT COUNT(*) FROM signinfo WHERE $field = :value";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':value', $value);
        $stmt->execute();

        $exists = $stmt->fetchColumn() > 0;
        echo json_encode(['exists' => $exists]);
    } catch (PDOException $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
}
?>
