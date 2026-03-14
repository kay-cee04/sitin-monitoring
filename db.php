<?php
$host     = 'localhost';
$dbname   = 'ccs_sitin';
$username = 'root';       // change if needed
$password = '';           // change if needed

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
    die('<p style="font-family:sans-serif;color:red;padding:20px;">
        Database connection failed: ' . htmlspecialchars($e->getMessage()) . '
        <br><small>Check your credentials in db.php</small>
    </p>');
}