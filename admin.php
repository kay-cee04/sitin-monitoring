<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = $admin['id'];
            $_SESSION['admin_username']  = $admin['username'];
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;
  --gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;
  --gray-400:#8aaac8;--gray-600:#3d607f;--gray-800:#003A6B;
  --white:#ffffff;--radius:8px;--shadow-md:0 4px 16px rgba(0,58,107,0.12);
  --red:#dc2626;--red-lt:#fef2f2;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:var(--gray-50);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;}

.login-wrap{width:100%;max-width:420px;}

.login-header{text-align:center;margin-bottom:28px;}
.login-header img{width:72px;height:72px;border-radius:50%;border:3px solid var(--blue-bd);box-shadow:0 4px 16px rgba(0,58,107,0.18);}
.login-header h1{font-size:20px;font-weight:700;color:var(--blue-dk);margin-top:14px;}
.login-header p{font-size:12.5px;color:var(--gray-400);margin-top:4px;letter-spacing:0.03em;}
.admin-badge{
  display:inline-block;background:var(--blue-dk);color:#fff;
  font-size:10.5px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;
  padding:3px 12px;border-radius:20px;margin-top:8px;
}

.login-card{
  background:var(--white);border:1px solid var(--gray-200);
  border-radius:12px;box-shadow:var(--shadow-md);overflow:hidden;
}
.login-card-top{background:var(--blue-dk);padding:14px 24px;}
.login-card-top h2{color:#fff;font-size:14px;font-weight:600;}
.login-card-top p{color:rgba(255,255,255,0.55);font-size:12px;margin-top:2px;}

.login-body{padding:28px 28px 32px;}

.alert-error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);padding:10px 14px;border-radius:var(--radius);font-size:13px;margin-bottom:20px;}

.field{margin-bottom:16px;}
.field label{display:block;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:6px;letter-spacing:0.02em;}
.field input{
  width:100%;padding:10px 12px;
  border:1px solid var(--gray-200);border-radius:var(--radius);
  font-size:14px;font-family:'Inter',sans-serif;color:var(--gray-800);
  background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;
}
.field input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.12);}
.field input::placeholder{color:var(--gray-400);}

.btn{
  width:100%;padding:11px;border:none;border-radius:var(--radius);
  background:var(--blue-dk);color:#fff;
  font-size:14px;font-weight:600;font-family:'Inter',sans-serif;
  cursor:pointer;transition:background .15s;margin-top:4px;
}
.btn:hover{background:#002855;}

.back-link{text-align:center;margin-top:18px;font-size:13px;color:var(--gray-400);}
.back-link a{color:var(--blue);font-weight:600;text-decoration:none;}
.back-link a:hover{text-decoration:underline;}
</style>
</head>
<body>

<div class="login-wrap">
  <div class="login-header">
    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
    <h1>College of Computer Studies</h1>
    <p>Sit-in Monitoring System</p>
    <span class="admin-badge">Admin Portal</span>
  </div>

  <div class="login-card">
    <div class="login-card-top">
      <h2>Administrator Login</h2>
      <p>Sign in with your admin credentials</p>
    </div>
    <div class="login-body">

      <?php if ($error): ?>
        <div class="alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST" action="admin_login.php">
        <div class="field">
          <label>Username</label>
          <input type="text" name="username" placeholder="Enter admin username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required autofocus/>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" placeholder="Enter password" required/>
        </div>
        <button type="submit" class="btn">Login as Admin</button>
      </form>

      <p class="back-link">Not an admin? <a href="login.php">Student Login</a></p>
    </div>
  </div>
</div>

</body>
</html>