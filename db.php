<?php
// Database configuration
$host     = 'localhost';
$dbname   = 'ccs_sitin'; // CRITICAL: Make sure this is the EXACT name in phpMyAdmin
$username = 'root';      // Default XAMPP username
$password = '';          // Default XAMPP password (empty string)

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // If this triggers, your $dbname, $username, or $password is wrong
    die('<p style="font-family:sans-serif;color:red;padding:20px;border:1px solid red;border-radius:8px;">
        <strong>Database connection failed:</strong> ' . htmlspecialchars($e->getMessage()) . '
        <br><br>
        <small>1. Open phpMyAdmin (http://localhost/phpmyadmin)<br>
        2. Check if a database named "<strong>' . htmlspecialchars($dbname) . '</strong>" exists.<br>
        3. Ensure your XAMPP MySQL user is "root" with no password.</small>
    </p>');
}
?>