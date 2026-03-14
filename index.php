<?php
session_start();
require_once 'db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: Homepage.php');
    exit;
}

// Fetch announcements from DB
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Home</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root {
  --blue:    #1B5886;
  --blue-dk: #003A6B;
  --blue-lt: #e8f4fb;
  --blue-bd: #89CFF1;
  --gray-50: #f4f8fc;
  --gray-100:#e8f0f7;
  --gray-200:#cddaec;
  --gray-400:#8aaac8;
  --gray-600:#3d607f;
  --gray-800:#003A6B;
  --white:   #ffffff;
  --radius:  8px;
  --shadow:  0 1px 3px rgba(0,58,107,0.08), 0 1px 2px rgba(0,58,107,0.06);
  --shadow-md: 0 4px 16px rgba(0,58,107,0.10);
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;}

nav{
  background:var(--blue-dk);border-bottom:1px solid rgba(255,255,255,0.1);
  height:60px;padding:0 32px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:100;
}
.nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-brand img{width:36px;height:36px;border-radius:50%;border:2px solid rgba(255,255,255,0.3);}
.nav-brand-text{font-size:14px;font-weight:600;color:#fff;line-height:1.3;}
.nav-brand-sub{font-size:11px;font-weight:400;color:rgba(255,255,255,0.5);}
.nav-links{display:flex;align-items:center;gap:4px;}
.nav-links a{font-size:13.5px;font-weight:500;color:rgba(255,255,255,0.8);text-decoration:none;padding:6px 12px;border-radius:var(--radius);transition:all .15s;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.1);}
.nav-links a.active{color:#89CFF1;}
.nav-links .btn-login{border:1px solid rgba(255,255,255,0.35);color:rgba(255,255,255,0.85);}
.nav-links .btn-login:hover{color:#fff;border-color:#fff;}
.nav-links .btn-register{background:var(--blue);color:#fff;font-weight:600;margin-left:2px;}
.nav-links .btn-register:hover{background:#1a4f7a;color:#fff;}
.nav-dropdown{position:relative;}
.nav-dropdown > a{display:flex;align-items:center;gap:4px;}
.nav-dropdown > a .chevron{font-size:10px;color:rgba(255,255,255,0.5);transition:transform .2s;}
.nav-dropdown:hover > a .chevron{transform:rotate(180deg);}
.dropdown-menu{display:none;position:absolute;top:calc(100% + 6px);left:0;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow-md);min-width:180px;z-index:200;overflow:hidden;}
.nav-dropdown:hover .dropdown-menu{display:block;}
.dropdown-menu a{display:block;padding:9px 16px;font-size:13px;font-weight:500;color:var(--gray-600) !important;text-decoration:none;border-radius:0 !important;background:transparent !important;}
.dropdown-menu a:hover{background:var(--gray-50) !important;color:var(--blue) !important;}

.home-banner{background:var(--blue);padding:48px 24px;text-align:center;}
.home-banner img{width:80px;height:80px;border-radius:50%;border:3px solid rgba(255,255,255,0.35);box-shadow:0 4px 16px rgba(0,0,0,0.2);}
.home-banner h1{color:#fff;font-size:22px;font-weight:700;margin-top:16px;line-height:1.3;}
.home-banner p{color:rgba(255,255,255,0.7);font-size:13px;margin-top:6px;}
.home-banner-pills{display:flex;justify-content:center;gap:8px;margin-top:20px;flex-wrap:wrap;}
.pill{background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);color:rgba(255,255,255,0.9);font-size:11px;font-weight:500;padding:4px 12px;border-radius:20px;letter-spacing:0.04em;}
.home-banner-btns{display:flex;justify-content:center;gap:10px;margin-top:24px;flex-wrap:wrap;}
.btn-hero{padding:10px 26px;border-radius:var(--radius);font-size:13.5px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;text-decoration:none;transition:all .15s;border:none;}
.btn-hero-solid{background:#fff;color:var(--blue-dk);}
.btn-hero-solid:hover{background:#e8f4fb;}
.btn-hero-outline{background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,0.5);}
.btn-hero-outline:hover{background:rgba(255,255,255,0.1);border-color:#fff;}

.home-body{max-width:720px;margin:0 auto;padding:36px 24px 56px;}
.card{background:var(--white);border-radius:var(--radius);border:1px solid var(--gray-200);box-shadow:var(--shadow);overflow:hidden;}
.card-head{background:var(--blue);padding:14px 20px;display:flex;align-items:center;gap:8px;}
.card-head h2{color:#fff;font-size:14px;font-weight:600;}
.card-head .count{margin-left:auto;background:rgba(255,255,255,0.2);color:#fff;font-size:11px;font-weight:600;padding:2px 9px;border-radius:20px;}
.ann-list{max-height:360px;overflow-y:auto;}
.ann-list::-webkit-scrollbar{width:4px;}
.ann-list::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:99px;}
.ann-item{padding:16px 20px;border-bottom:1px solid var(--gray-100);}
.ann-item:last-child{border-bottom:none;}
.ann-item:hover{background:var(--gray-50);}
.ann-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
.ann-dot{width:32px;height:32px;border-radius:50%;flex-shrink:0;background:var(--blue-lt);color:var(--blue);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;border:1px solid var(--blue-bd);}
.ann-author{font-size:13px;font-weight:600;color:var(--gray-800);}
.ann-date{font-size:12px;color:var(--gray-400);margin-left:auto;}
.ann-body{font-size:13px;color:var(--gray-600);line-height:1.6;padding-left:42px;}
.ann-empty{font-size:13px;color:var(--gray-400);font-style:italic;padding-left:42px;}
footer{background:var(--gray-800);text-align:center;padding:16px;font-size:12px;color:var(--gray-400);}
@media(max-width:640px){nav{padding:0 16px;}}
</style>
</head>
<body>

<nav>
  <a class="nav-brand" href="index.php">
    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
    <div>
      <div class="nav-brand-text">College of Computer Studies</div>
      <div class="nav-brand-sub">Sit-in Monitoring System</div>
    </div>
  </a>
  <div class="nav-links">
    <a href="index.php" class="active">Home</a>
    <div class="nav-dropdown">
      <a href="#">Community <span class="chevron">▾</span></a>
    </div>
    <a href="#">About</a>
    <a href="login.php" class="btn-login">Login</a>
    <a href="register.php" class="btn-register">Register</a>
  </div>
</nav>

<div class="home-banner">
  <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
  <h1>College of Computer Studies</h1>
  <p>University of Cebu · Sit-in Monitoring System</p>
  <div class="home-banner-pills">
    <span class="pill">Inceptum</span>
    <span class="pill">Innovatio</span>
    <span class="pill">Muneris</span>
    <span class="pill">Est. 1983</span>
  </div>
  <div class="home-banner-btns">
    <a href="login.php" class="btn-hero btn-hero-solid">Login</a>
    <a href="register.php" class="btn-hero btn-hero-outline">Create Account</a>
  </div>
</div>

<div class="home-body">
  <div class="card">
    <div class="card-head">
      <h2>Announcements</h2>
      <span class="count"><?php echo count($announcements); ?></span>
    </div>
    <div class="ann-list">
      <?php if ($announcements): ?>
        <?php foreach ($announcements as $ann): ?>
        <div class="ann-item">
          <div class="ann-row">
            <div class="ann-dot">CA</div>
            <span class="ann-author"><?php echo htmlspecialchars($ann['admin_name']); ?></span>
            <span class="ann-date"><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></span>
          </div>
          <?php if (!empty($ann['content'])): ?>
            <div class="ann-body"><?php echo htmlspecialchars($ann['content']); ?></div>
          <?php else: ?>
            <div class="ann-empty">No content provided for this announcement.</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="ann-item">
          <div class="ann-empty" style="padding:16px 0;">No announcements yet.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<footer>© 2026 College of Computer Studies · University of Cebu</footer>
</body>
</html>