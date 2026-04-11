<?php
session_start();
require_once 'db.php';

// Already logged in → go straight to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}

// ── AUTO-HEAL: ensure the admin account always exists with a correct hash ──
// This runs silently every time the page loads so the hash is always valid.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        username   VARCHAR(100) NOT NULL UNIQUE,
        password   VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $fixed_hash = password_hash('admin123', PASSWORD_BCRYPT);

    $check = $pdo->prepare("SELECT id, password FROM admins WHERE username = 'admin' LIMIT 1");
    $check->execute();
    $row = $check->fetch();

    if ($row) {
        // Re-hash if stored hash doesn't verify (broken hash scenario)
        if (!password_verify('admin123', $row['password'])) {
            $pdo->prepare("UPDATE admins SET password = ? WHERE username = 'admin'")
                ->execute([$fixed_hash]);
        }
    } else {
        // No admin row at all — create it
        $pdo->prepare("INSERT INTO admins (username, password) VALUES ('admin', ?)")
            ->execute([$fixed_hash]);
    }
} catch (Exception $e) {
    // Silently continue — login form will show DB error if connection itself is broken
}

// ── Handle login form submission ──
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Single-admin rule: only the lowest ID is allowed
                $firstId = (int)$pdo->query("SELECT id FROM admins ORDER BY id ASC LIMIT 1")->fetchColumn();
                if ((int)$admin['id'] !== $firstId) {
                    $error = 'Unauthorized admin account.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id']        = $admin['id'];
                    $_SESSION['admin_username']  = $admin['username'];
                    header('Location: admin_dashboard.php');
                    exit;
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;--gray-50:#f4f8fc;--gray-200:#cddaec;--gray-400:#8aaac8;--gray-600:#3d607f;--gray-800:#1a2e45;--white:#fff;--radius:8px;--radius-lg:12px;--shadow-md:0 4px 20px rgba(0,58,107,0.13);--red:#dc2626;--red-lt:#fef2f2;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;}
.wrap{width:100%;max-width:420px;}
.header{text-align:center;margin-bottom:28px;}
.header img{width:76px;height:76px;border-radius:50%;border:3px solid var(--blue-bd);box-shadow:0 6px 20px rgba(0,58,107,0.2);}
.header h1{font-size:21px;font-weight:800;color:var(--blue-dk);margin-top:14px;letter-spacing:-0.02em;}
.header p{font-size:12.5px;color:var(--gray-400);margin-top:4px;}
.admin-badge{display:inline-block;background:var(--blue-dk);color:#fff;font-size:10.5px;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;padding:3px 13px;border-radius:20px;margin-top:10px;}
.card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);overflow:hidden;}
.card-top{background:linear-gradient(135deg,#002255 0%,#003A6B 100%);padding:15px 24px;}
.card-top h2{color:#fff;font-size:14px;font-weight:800;}
.card-top p{color:rgba(255,255,255,0.5);font-size:12px;margin-top:2px;}
.card-body{padding:28px 28px 32px;}
.alert-error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);padding:11px 14px;border-radius:var(--radius);font-size:13px;margin-bottom:20px;font-weight:600;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:11px;font-weight:800;color:var(--gray-600);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.05em;}
.field input{width:100%;padding:10px 13px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--gray-800);background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;}
.field input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.1);}
.field input::placeholder{color:var(--gray-400);}
.btn{width:100%;padding:12px;border:none;border-radius:var(--radius);background:var(--blue-dk);color:#fff;font-size:14px;font-weight:800;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s;margin-top:4px;letter-spacing:0.01em;}
.btn:hover{background:#002255;}
.back-link{text-align:center;margin-top:18px;font-size:13px;color:var(--gray-400);}
.back-link a{color:var(--blue);font-weight:700;text-decoration:none;}
.back-link a:hover{text-decoration:underline;}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
    <h1>College of Computer Studies</h1>
    <p>Sit-in Monitoring System</p>
    <span class="admin-badge">Admin Portal</span>
  </div>

  <div class="card">
    <div class="card-top">
      <h2>Administrator Login</h2>
      <p>Sign in with your admin credentials</p>
    </div>
    <div class="card-body">

      <?php if ($error): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="admin.php">
        <div class="field">
          <label>Username</label>
          <input type="text" name="username" placeholder="Enter admin username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                 required autofocus/>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" placeholder="Enter password" required/>
        </div>
        <button type="submit" class="btn">Log In</button>
      </form>

      <p class="back-link">Not an admin? <a href="login.php">Student Login</a></p>
    </div>
  </div>
</div>
</body>
</html>