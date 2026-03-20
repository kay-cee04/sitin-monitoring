<?php
require_once 'db.php';

$username = 'admin';
$password = 'admin123';

// Generate a fresh hash right now
$hash = password_hash($password, PASSWORD_BCRYPT);

// Verify the hash works before saving
if (!password_verify($password, $hash)) {
    die('<p style="color:red;font-family:sans-serif;padding:20px;">❌ Hash generation failed on this server. PHP issue.</p>');
}

// Check if admin row exists
$stmt = $pdo->prepare("SELECT id, password FROM admins WHERE username = ?");
$stmt->execute([$username]);
$existing = $stmt->fetch();

if ($existing) {
    // Update password
    $pdo->prepare("UPDATE admins SET password = ? WHERE username = ?")
        ->execute([$hash, $username]);
    $status = 'updated';
} else {
    // Create admin row fresh
    $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)")
        ->execute([$username, $hash]);
    $status = 'created';
}

// Final verification: re-read from DB and verify
$stmt2 = $pdo->prepare("SELECT password FROM admins WHERE username = ?");
$stmt2->execute([$username]);
$saved_hash = $stmt2->fetchColumn();
$final_check = password_verify($password, $saved_hash);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"/>
<title>Fix Admin</title>
<style>
  body { font-family: sans-serif; max-width: 520px; margin: 60px auto; padding: 20px; background: #f4f8fc; }
  .box { background: #fff; border-radius: 10px; padding: 28px; box-shadow: 0 2px 16px rgba(0,0,0,0.1); }
  h2 { margin-bottom: 16px; color: #003A6B; }
  .ok  { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; padding: 14px 16px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; }
  .err { background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; padding: 14px 16px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; }
  table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px; }
  td { padding: 9px 12px; border: 1px solid #e2e8f0; }
  td:first-child { font-weight: 600; background: #f8fafc; width: 38%; color: #3d607f; }
  .warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; padding: 12px; border-radius: 8px; font-size: 13px; margin-top: 16px; }
  .btn { display: inline-block; margin-top: 18px; background: #003A6B; color: #fff; padding: 11px 28px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 14px; }
  .btn:hover { background: #1B5886; }
</style>
</head>
<body>
<div class="box">
  <h2>🔧 Admin Account Fix</h2>

  <?php if ($final_check): ?>
    <div class="ok">✅ Admin account <?= $status ?> — password verified successfully!</div>
  <?php else: ?>
    <div class="err">❌ Account <?= $status ?> but DB verification failed. Check your db.php config.</div>
  <?php endif; ?>

  <table>
    <tr><td>Username</td><td><strong><?= htmlspecialchars($username) ?></strong></td></tr>
    <tr><td>Password</td><td><strong><?= htmlspecialchars($password) ?></strong></td></tr>
    <tr><td>Action</td><td><?= ucfirst($status) ?></td></tr>
    <tr><td>DB Verify</td><td><?= $final_check ? '✅ PASS' : '❌ FAIL' ?></td></tr>
    <tr>
      <td>Saved hash</td>
      <td style="font-size:10.5px;word-break:break-all;color:#666;"><?= htmlspecialchars($saved_hash) ?></td>
    </tr>
  </table>

  <div class="warn">
    ⚠️ <strong>Important:</strong> Delete <code>fix_admin.php</code> from your server immediately after logging in!
  </div>

  <a class="btn" href="admin.php">→ Go to Admin Login</a>
</div>
</body>
</html>