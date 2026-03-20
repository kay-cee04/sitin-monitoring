<?php
session_start();
require_once 'db.php';
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: Homepage.php'); exit;
}
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Home</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;--gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-400:#8aaac8;--gray-600:#3d607f;--gray-800:#1a2e45;--white:#fff;--radius:8px;--radius-lg:12px;--shadow:0 1px 3px rgba(0,58,107,0.08);--shadow-md:0 4px 20px rgba(0,58,107,0.11);}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;}
/* NAV */
nav{background:var(--blue-dk);height:58px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,0.15);}
.nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-brand img{width:34px;height:34px;border-radius:50%;border:2px solid rgba(255,255,255,0.3);}
.nav-brand-text{font-size:14px;font-weight:800;color:#fff;letter-spacing:-0.02em;line-height:1.2;}
.nav-brand-sub{font-size:10.5px;font-weight:400;color:rgba(255,255,255,0.5);}
.nav-links{display:flex;align-items:center;gap:2px;}
.nav-links a{font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);text-decoration:none;padding:6px 11px;border-radius:6px;transition:all .15s;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.1);}
.nav-links a.active{color:#89CFF1;font-weight:600;}
.btn-login{border:1px solid rgba(255,255,255,0.3);}
.btn-register{background:var(--blue);color:#fff !important;font-weight:700 !important;}
.btn-register:hover{background:#154f7a !important;}
.btn-admin{background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);color:rgba(255,255,255,0.55) !important;font-size:12px !important;margin-left:4px;}
.btn-admin:hover{background:rgba(255,255,255,0.13) !important;color:#fff !important;}
.nav-dropdown{position:relative;}
.nav-dropdown>a{display:flex;align-items:center;gap:4px;}
.chevron{font-size:10px;color:rgba(255,255,255,0.4);transition:transform .2s;}
.nav-dropdown:hover .chevron{transform:rotate(180deg);}
.dropdown-menu{display:none;position:absolute;top:calc(100% + 8px);left:0;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow-md);min-width:180px;z-index:200;overflow:hidden;}
.nav-dropdown:hover .dropdown-menu{display:block;}
.dropdown-menu a{display:block;padding:9px 16px;font-size:13px;color:var(--gray-600) !important;background:transparent !important;border-radius:0 !important;font-weight:500;}
.dropdown-menu a:hover{background:var(--gray-50) !important;color:var(--blue) !important;}
/* HERO */
.hero{background:linear-gradient(140deg,#002855 0%,#1B5886 100%);padding:60px 24px 52px;text-align:center;position:relative;overflow:hidden;}
.hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 70% 50%,rgba(137,207,241,0.12) 0%,transparent 65%);}
.hero img{width:90px;height:90px;border-radius:50%;border:3px solid rgba(255,255,255,0.28);box-shadow:0 8px 32px rgba(0,0,0,0.3);position:relative;}
.hero h1{color:#fff;font-size:28px;font-weight:800;margin-top:18px;letter-spacing:-0.03em;position:relative;}
.hero p{color:rgba(255,255,255,0.6);font-size:13.5px;margin-top:6px;position:relative;}
.hero-pills{display:flex;justify-content:center;gap:8px;margin-top:20px;flex-wrap:wrap;position:relative;}
.pill{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.18);color:rgba(255,255,255,0.8);font-size:11px;font-weight:600;padding:4px 13px;border-radius:20px;letter-spacing:0.05em;}
.hero-btns{display:flex;justify-content:center;gap:10px;margin-top:28px;flex-wrap:wrap;position:relative;}
.btn-hero{padding:11px 30px;border-radius:var(--radius);font-size:13.5px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;text-decoration:none;transition:all .15s;border:none;display:inline-block;}
.btn-solid{background:#fff;color:#002855;}
.btn-solid:hover{background:#e8f4fb;}
.btn-outline-hero{background:transparent;color:#fff;border:2px solid rgba(255,255,255,0.4);}
.btn-outline-hero:hover{background:rgba(255,255,255,0.1);border-color:#fff;}
/* CONTENT */
.home-body{max-width:680px;margin:0 auto;padding:36px 20px 56px;}
.card{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow);overflow:hidden;}
.card-head{background:var(--blue);padding:13px 20px;display:flex;align-items:center;gap:8px;}
.card-head h2{color:#fff;font-size:13.5px;font-weight:700;}
.card-badge{margin-left:auto;background:rgba(255,255,255,0.2);color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;}
.ann-list{max-height:400px;overflow-y:auto;}
.ann-list::-webkit-scrollbar{width:4px;}
.ann-list::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:99px;}
.ann-item{padding:15px 20px;border-bottom:1px solid var(--gray-100);}
.ann-item:last-child{border-bottom:none;}
.ann-item:hover{background:var(--gray-50);}
.ann-meta{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
.ann-dot{width:32px;height:32px;border-radius:50%;background:var(--blue-lt);border:1px solid var(--blue-bd);color:var(--blue);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ann-author{font-size:13px;font-weight:700;color:var(--blue-dk);}
.ann-date{font-size:11.5px;color:var(--gray-400);margin-left:auto;}
.ann-body{font-size:13px;color:var(--gray-600);line-height:1.65;padding-left:42px;}
.ann-empty{font-size:13px;color:var(--gray-400);font-style:italic;padding-left:42px;}
footer{background:var(--blue-dk);text-align:center;padding:18px;font-size:12px;color:rgba(255,255,255,0.35);}
footer a{color:rgba(255,255,255,0.45);text-decoration:none;}
footer a:hover{color:rgba(255,255,255,0.8);}
@media(max-width:600px){nav{padding:0 16px;}.nav-brand-sub{display:none;}}
</style>
</head>
<body>
<nav>
  <a class="nav-brand" href="index.php">
    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
    <div><div class="nav-brand-text">College of Computer Studies</div><div class="nav-brand-sub">Sit-in Monitoring System</div></div>
  </a>
  <div class="nav-links">
    <a href="index.php" class="active">Home</a>
    <div class="nav-dropdown">
      <a href="#">Community <span class="chevron">▾</span></a>
    </div>
    <a href="#">About</a>
    <a href="login.php" class="btn-login">Login</a>
    <a href="register.php" class="btn-register">Register</a>
    <a href="admin.php" class="btn-admin">🔒 Admin</a>
  </div>
</nav>
<div class="hero">
  <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRy4J1dSoQ3EKgOPNlwRe_8LCU0oHWbN5z8qQ&s" alt="CCS"/>
  <h1>College of Computer Studies</h1>
  <p>University of Cebu · Sit-in Monitoring System</p>
  <div class="hero-pills"><span class="pill">Inceptum</span><span class="pill">Innovatio</span><span class="pill">Muneris</span><span class="pill">Est. 1983</span></div>
  <div class="hero-btns">
    <a href="login.php" class="btn-hero btn-solid">Login</a>
    <a href="register.php" class="btn-hero btn-outline-hero">Create Account</a>
  </div>
</div>
<div class="home-body">
  <div class="card">
    <div class="card-head"><h2>📢 Announcements</h2><span class="card-badge"><?= count($announcements) ?></span></div>
    <div class="ann-list">
      <?php if ($announcements): foreach ($announcements as $ann): ?>
      <div class="ann-item">
        <div class="ann-meta">
          <div class="ann-dot">CA</div>
          <span class="ann-author"><?= htmlspecialchars($ann['admin_name']) ?></span>
          <span class="ann-date"><?= date('M d, Y', strtotime($ann['created_at'])) ?></span>
        </div>
        <?php if (!empty($ann['content'])): ?>
          <div class="ann-body"><?= htmlspecialchars($ann['content']) ?></div>
        <?php else: ?>
          <div class="ann-empty">No content provided for this announcement.</div>
        <?php endif; ?>
      </div>
      <?php endforeach; else: ?>
      <div class="ann-item"><div class="ann-empty" style="padding:16px 0;">No announcements yet.</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<footer>© 2026 College of Computer Studies · University of Cebu &nbsp;|&nbsp; <a href="admin.php">Admin Login</a></footer>
</body>
</html>