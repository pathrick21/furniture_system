<?php
// script.php

// List of allowed referer domains
$allowedReferers = [
    
    'localhost',
    '127.0.0.1'
];

// Get the HTTP_REFERER value
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$allowed = false;

// Check if the referer is allowed
foreach ($allowedReferers as $domain) {
    if (strpos($referer, $domain) !== false) {
        $allowed = true;
        break;
    }
}

// If the referer is not allowed, redirect to the status code page
if (!$allowed) {
    header("Location: ../php/status-code.php", true, 403);
    exit();
}

// Get the file parameter
$file = isset($_GET['file']) ? basename($_GET['file']) : '';
$dir = isset($_GET['dir']) ? basename($_GET['dir']) : 'js'; // Default to 'js'

// Define directories for JS and CSS files

$cssDirectory = '../css/';

// Determine the directory and build the file path based on the provided dir parameter
$filePath = '';

if ($dir === 'css') {
    $filePath = $cssDirectory . $file;
} else {
    $filePath = $jsDirectory . $file;
}

// Check if the file exists
if (file_exists($filePath)) {
    // Set the correct Content-Type based on the directory
    if ($dir === 'css') {
        header("Content-Type: text/css");
    } else {
        header("Content-Type: application/javascript");
    }
    echo file_get_contents($filePath);
} else {
    // Handle file not found
    http_response_code(404);
    echo "console.error('File not found at: $filePath');"; // Provide detailed error message
}
exit();
?>