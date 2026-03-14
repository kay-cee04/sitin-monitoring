<?php
// ============================================================
//  setup_admin.php
//  Run this ONCE in your browser to set up the admin account
//  URL: http://localhost/your-folder/setup_admin.php
//  DELETE this file after running it!
// ============================================================
require_once 'db.php';

$username = 'admin';
$password = 'admin123';
$hash     = password_hash($password, PASSWORD_DEFAULT);

// Check if admin already exists
$stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
$stmt->execute([$username]);

if ($stmt->fetch()) {
    // Update existing
    $pdo->prepare("UPDATE admins SET password = ? WHERE username = ?")->execute([$hash, $username]);
    echo '<p style="font-family:sans-serif;color:green;padding:20px;">✅ Admin password updated successfully!</p>';
} else {
    // Insert new
    $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)")->execute([$username, $hash]);
    echo '<p style="font-family:sans-serif;color:green;padding:20px;">✅ Admin account created successfully!</p>';
}

echo '<p style="font-family:sans-serif;padding:20px 20px 0;">
    <strong>Username:</strong> admin<br>
    <strong>Password:</strong> admin123<br><br>
    <strong style="color:red;">⚠️ Delete this file (setup_admin.php) now for security!</strong><br><br>
    <a href="admin_login.php" style="color:#1B5886;">→ Go to Admin Login</a>
</p>';

// Also fix student passwords in case they need re-hashing
$students_raw = [
    ['2024-00001', 'Password123'],
    ['2024-00002', 'Password123'],
    ['2024-00003', 'Password123'],
    ['2024-00004', 'Password123'],
    ['23784630',   'Password123'],
];

foreach ($students_raw as [$id_num, $pw]) {
    $h = password_hash($pw, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE students SET password = ? WHERE id_number = ?")->execute([$h, $id_num]);
}

echo '<p style="font-family:sans-serif;padding:0 20px;">✅ Student passwords also refreshed (all set to <strong>Password123</strong>).</p>';