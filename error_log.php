<?php
// A simple script to check the PHP error log
// Note: This should be protected in a production environment

// Check if user is logged in as admin (basic protection)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    echo "Access denied";
    exit;
}

// Path to error log - adjust this to your server's configuration
$log_file = ini_get('error_log');

if (file_exists($log_file) && is_readable($log_file)) {
    echo "<h1>PHP Error Log</h1>";
    echo "<pre>";
    // Get the last 50 lines
    $lines = file($log_file);
    $lines = array_slice($lines, -50);
    foreach ($lines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "<h1>Cannot read error log</h1>";
    echo "<p>Error log file not found or not readable at: " . htmlspecialchars($log_file) . "</p>";
    
    echo "<h2>Server Information</h2>";
    echo "<pre>";
    echo "PHP Version: " . phpversion() . "\n";
    echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
    echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    echo "Error reporting: " . ini_get('error_reporting') . "\n";
    echo "Display errors: " . ini_get('display_errors') . "\n";
    echo "Log errors: " . ini_get('log_errors') . "\n";
    echo "</pre>";
}
?>
