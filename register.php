<?php
session_start();
require_once 'db.php';
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) { header('Location: Homepage.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number'] ?? '');
    $password  = $_POST['password'] ?? '';
    if (empty($id_number) || empty($password)) { $error = 'Please enter your ID number and password.'; }
    else {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id_number = ? LIMIT 1");
        $stmt->execute([$id_number]);
        $student = $stmt->fetch();
        if ($student && password_verify($password, $student['password'])) {
            $_SESSION['logged_in']  = true; $_SESSION['student_id'] = $student['id'];
            $_SESSION['id_number']  = $student['id_number']; $_SESSION['lastname']   = $student['lastname'];
            $_SESSION['firstname']  = $student['firstname']; $_SESSION['middlename'] = $student['middlename'];
            $_SESSION['fullname']   = trim($student['firstname'].' '.$student['middlename'].' '.$student['lastname']);
            $_SESSION['course']     = $student['course']; $_SESSION['year_level'] = $student['year_level'];
            $_SESSION['email']      = $student['email']; $_SESSION['address']    = $student['address'];
            $_SESSION['session']    = $student['session'];
            header('Location: Homepage.php'); exit;
        } else { $error = 'Invalid ID number or password. Please try again.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Login</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;--gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-400:#8aaac8;--gray-600:#3d607f;--gray-800:#1a2e45;--white:#fff;--radius:8px;--radius-lg:12px;--shadow-md:0 4px 20px rgba(0,58,107,0.11);--red:#dc2626;--red-lt:#fef2f2;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;}
/* NAV */
nav{background:var(--blue-dk);height:58px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,0.15);}
.nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-brand img{width:34px;height:34px;border-radius:50%;border:2px solid rgba(255,255,255,0.3);}
.nav-brand-text{font-size:14px;font-weight:800;color:#fff;letter-spacing:-0.02em;}
.nav-brand-sub{font-size:10.5px;color:rgba(255,255,255,0.5);}
.nav-links{display:flex;align-items:center;gap:2px;}
.nav-links a{font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);text-decoration:none;padding:6px 11px;border-radius:6px;transition:all .15s;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.1);}
.btn-login-nav{border:1px solid rgba(255,255,255,0.3);font-weight:600 !important;color:#fff !important;opacity:.5;pointer-events:none;}
.btn-register-nav{background:var(--blue);color:#fff !important;font-weight:700 !important;}
.nav-dropdown{position:relative;}
.nav-dropdown>a{display:flex;align-items:center;gap:4px;}
.chevron{font-size:10px;color:rgba(255,255,255,0.4);transition:transform .2s;}
.nav-dropdown:hover .chevron{transform:rotate(180deg);}
.dropdown-menu{display:none;position:absolute;top:calc(100% + 8px);left:0;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow-md);min-width:180px;z-index:200;overflow:hidden;}
.nav-dropdown:hover .dropdown-menu{display:block;}
.dropdown-menu a{display:block;padding:9px 16px;font-size:13px;color:var(--gray-600) !important;background:transparent !important;border-radius:0 !important;font-weight:500;}
.dropdown-menu a:hover{background:var(--gray-50) !important;color:var(--blue) !important;}
/* AUTH */
.auth-page{min-height:calc(100vh - 58px);display:flex;align-items:center;justify-content:center;padding:40px 16px;}
.login-box{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);width:100%;max-width:860px;display:grid;grid-template-columns:320px 1fr;overflow:hidden;}
.login-left{background:linear-gradient(145deg,#002855 0%,#1B5886 100%);padding:48px 32px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;position:relative;overflow:hidden;}
.login-left::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 60% 40%,rgba(137,207,241,0.15) 0%,transparent 60%);}
.login-left img{width:86px;height:86px;border-radius:50%;border:3px solid rgba(255,255,255,0.28);box-shadow:0 6px 24px rgba(0,0,0,0.25);position:relative;}
.login-left h2{color:#fff;font-size:18px;font-weight:800;margin-top:18px;letter-spacing:-0.02em;line-height:1.3;position:relative;}
.login-left p{color:rgba(255,255,255,0.6);font-size:12px;margin-top:8px;position:relative;}
.login-left .motto{margin-top:26px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.12);font-size:11px;color:rgba(255,255,255,0.45);letter-spacing:0.07em;line-height:1.9;position:relative;}
.login-right{padding:44px 40px;}
.login-right h3{font-size:21px;font-weight:800;color:var(--gray-800);letter-spacing:-0.02em;}
.login-right .sub{font-size:13px;color:var(--gray-400);margin-top:4px;margin-bottom:28px;}
.alert-error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);padding:11px 14px;border-radius:var(--radius);font-size:13px;margin-bottom:20px;font-weight:500;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:12px;font-weight:700;color:var(--gray-600);margin-bottom:6px;letter-spacing:0.02em;text-transform:uppercase;}
.field input{width:100%;padding:10px 13px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--gray-800);background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;}
.field input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.1);}
.field input::placeholder{color:var(--gray-400);}
.extras{display:flex;align-items:center;justify-content:space-between;margin:14px 0 22px;}
.check{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--gray-600);cursor:pointer;}
.check input{accent-color:var(--blue);}
.link{font-size:13px;color:var(--blue);text-decoration:none;font-weight:600;}
.link:hover{text-decoration:underline;}
.btn{width:100%;padding:12px;border:none;border-radius:var(--radius);background:var(--blue-dk);color:#fff;font-size:14px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s;letter-spacing:0.01em;}
.btn:hover{background:#002255;}
.alt-line{text-align:center;margin-top:18px;font-size:13px;color:var(--gray-400);}
.alt-line a{color:var(--blue);font-weight:700;text-decoration:none;}
.alt-line a:hover{text-decoration:underline;}
@media(max-width:640px){nav{padding:0 16px;}.login-box{grid-template-columns:1fr;}.login-left{padding:32px 24px;}.login-right{padding:28px 24px;}}
</style>
</head>
<body>
<nav>
  <a class="nav-brand" href="index.php">
    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
    <div><div class="nav-brand-text">College of Computer Studies</div><div class="nav-brand-sub">Sit-in Monitoring System</div></div>
  </a>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <div class="nav-dropdown">
      <a href="#">Community <span class="chevron">▾</span></a>
      <div class="dropdown-menu">
        <a href="#">Forum</a>
        <a href="#">Resources</a>
        <a href="#">Events</a>
      </div>
    </div>
    <a href="#">About</a>
    <a href="login.php" class="btn-login-nav">Login</a>
    <a href="register.php" class="btn-register-nav">Register</a>
  </div>
</nav>
<div class="auth-page">
  <div class="login-box">
    <div class="login-left">
      <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
      <h2>College of Computer Studies</h2>
      <p>University of Cebu - Main Campus</p>
      <div class="motto">Inceptum · Innovatio · Muneris<br/>Established 1983</div>
    </div>
    <div class="login-right">
      <h3>Sign In</h3>
      <p class="sub">Enter your credentials to access your account.</p>
      <?php if ($error): ?><div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST" action="login.php">
        <div class="field"><label>ID Number</label><input type="text" name="id_number" placeholder="Enter your student ID" value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>" required autofocus/></div>
        <div class="field"><label>Password</label><input type="password" name="password" placeholder="Enter your password" required/></div>
        <div class="extras">
          <label class="check"><input type="checkbox" name="remember"/> Remember me</label>
          <a href="#" class="link">Forgot password?</a>
        </div>
        <button type="submit" class="btn">Login</button>
      </form>
      <p class="alt-line">Don't have an account? <a href="register.php">Register here</a></p>
    </div>
  </div>
</div>
</body>
</html>