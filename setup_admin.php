<?php
// ============================================================
//  setup_admin.php — Run this ONCE in your browser
//  URL: http://localhost/your-folder/setup_admin.php
//  DELETE or restrict access after running!
// ============================================================
require_once 'db.php';

$username = 'admin';
$password = 'admin123';
$hash     = password_hash($password, PASSWORD_BCRYPT);

// Ensure admins table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Check if admin already exists
$stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
$stmt->execute([$username]);

if ($stmt->fetch()) {
    $pdo->prepare("UPDATE admins SET password = ? WHERE username = ?")->execute([$hash, $username]);
    $action = 'updated';
} else {
    $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)")->execute([$username, $hash]);
    $action = 'created';
}

// Verify the hash works correctly
$verify_stmt = $pdo->prepare("SELECT password FROM admins WHERE username = ?");
$verify_stmt->execute([$username]);
$stored = $verify_stmt->fetchColumn();
$verified = password_verify($password, $stored);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>Setup Admin</title>
<style>
  body{font-family:sans-serif;max-width:500px;margin:60px auto;padding:20px;}
  .ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:16px;border-radius:8px;margin-bottom:16px;}
  .err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:16px;border-radius:8px;margin-bottom:16px;}
  table{width:100%;border-collapse:collapse;margin:16px 0;}
  td{padding:8px 12px;border:1px solid #e2e8f0;font-size:14px;}
  td:first-child{font-weight:600;background:#f8fafc;width:40%;}
  .warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:12px;border-radius:8px;margin-top:16px;font-size:13px;}
  a.btn{display:inline-block;margin-top:16px;background:#003A6B;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;}
</style>
</head>
<body>

<?php if ($verified): ?>
  <div class="ok">✅ Admin account <?= $action ?> successfully and password verified!</div>
<?php else: ?>
  <div class="err">❌ Account <?= $action ?> but password verification FAILED. Check PHP version / db.php.</div>
<?php endif; ?>

<table>
  <tr><td>Username</td><td><strong><?= htmlspecialchars($username) ?></strong></td></tr>
  <tr><td>Password</td><td><strong><?= htmlspecialchars($password) ?></strong></td></tr>
  <tr><td>Hash stored</td><td style="font-size:11px;word-break:break-all;"><?= htmlspecialchars($stored) ?></td></tr>
  <tr><td>Verification</td><td><?= $verified ? '✅ PASS' : '❌ FAIL' ?></td></tr>
</table>

<div class="warn">⚠️ <strong>Delete or restrict access to this file after use!</strong></div>

<a class="btn" href="admin.php">→ Go to Admin Login</a>

</body>
</html>