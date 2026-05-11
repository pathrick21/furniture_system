<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    
    if (empty($field) || empty($value)) {
        echo json_encode(['exists' => false]);
        exit;
    }
    
    // Sanitize field name to prevent SQL injection
    $allowed_fields = ['id_main', 'username', 'email'];
    if (!in_array($field, $allowed_fields)) {
        echo json_encode(['exists' => false]);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT 1 FROM signinfo WHERE $field = ?");
    $stmt->bind_param("s", $value);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode(['exists' => $result->num_rows > 0]);
    exit;
}

echo json_encode(['exists' => false]);