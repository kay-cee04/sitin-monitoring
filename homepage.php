<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header('Location: login.php'); exit; }
require_once 'db.php';
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();
if ($student) {
    $_SESSION['id_number']     = $student['id_number'];
    $_SESSION['firstname']     = $student['firstname'];
    $_SESSION['lastname']      = $student['lastname'];
    $_SESSION['middlename']    = $student['middlename'];
    $_SESSION['fullname']      = trim($student['firstname'].' '.$student['middlename'].' '.$student['lastname']);
    $_SESSION['course']        = $student['course'];
    $_SESSION['year_level']    = $student['year_level'];
    $_SESSION['email']         = $student['email'];
    $_SESSION['address']       = $student['address'];
    $_SESSION['session']       = $student['session'];
    $_SESSION['profile_photo'] = $student['profile_photo'] ?? null;
}
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
$photoSrc = (!empty($_SESSION['profile_photo'])) ? 'uploads/profiles/'.htmlspecialchars($_SESSION['profile_photo']) : null;
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
.nav-brand{font-size:15px;font-weight:800;color:#fff;letter-spacing:-0.02em;}
.nav-links{display:flex;align-items:center;gap:2px;}
.nav-links a{font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);text-decoration:none;padding:6px 11px;border-radius:6px;transition:all .15s;white-space:nowrap;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.1);}
.nav-links a.active{color:#89CFF1;font-weight:600;}
.nav-dropdown{position:relative;}
.nav-dropdown>a{display:flex;align-items:center;gap:4px;}
.nav-dropdown>a .chevron{font-size:10px;color:rgba(255,255,255,0.4);transition:transform .2s;}
.nav-dropdown:hover>a .chevron{transform:rotate(180deg);}
.dropdown-menu{display:none;position:absolute;top:calc(100% + 8px);left:0;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow-md);min-width:180px;z-index:200;overflow:hidden;}
.nav-dropdown:hover .dropdown-menu{display:block;}
.dropdown-menu a{display:block;padding:9px 16px;font-size:13px;color:var(--gray-600) !important;background:transparent !important;border-radius:0 !important;}
.dropdown-menu a:hover{background:var(--gray-50) !important;color:var(--blue) !important;}
.btn-logout{background:#e53e3e !important;color:#fff !important;font-weight:700 !important;border-radius:6px;padding:6px 16px !important;margin-left:6px;}
.btn-logout:hover{background:#c53030 !important;}
/* DASHBOARD GRID */
.dashboard{max-width:1280px;margin:0 auto;padding:24px 20px;display:grid;grid-template-columns:280px 1fr 300px;gap:20px;align-items:start;}
/* CARD */
.card{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow);overflow:hidden;}
.card-head{background:var(--blue);padding:12px 16px;display:flex;align-items:center;gap:8px;}
.card-head h2{color:#fff;font-size:13px;font-weight:700;}
.card-head svg{width:15px;height:15px;stroke:rgba(255,255,255,0.8);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
/* STUDENT INFO CARD */
.student-avatar{display:flex;flex-direction:column;align-items:center;padding:22px 16px 18px;border-bottom:1px solid var(--gray-100);}
.avatar-circle{width:92px;height:92px;border-radius:50%;background:var(--blue-lt);border:3px solid var(--blue-bd);display:flex;align-items:center;justify-content:center;box-shadow:0 3px 12px rgba(0,58,107,0.15);overflow:hidden;}
.avatar-circle img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.avatar-circle svg{width:42px;height:42px;stroke:var(--blue);fill:none;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;}
.student-info-list{padding:12px 16px;}
.info-row{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--gray-100);}
.info-row:last-child{border-bottom:none;}
.info-icon{display:flex;align-items:flex-start;justify-content:center;width:18px;flex-shrink:0;padding-top:2px;}
.info-icon svg{width:13px;height:13px;stroke:var(--blue);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.info-content{display:flex;flex-direction:column;gap:1px;min-width:0;flex:1;}
.info-label{font-size:10px;font-weight:800;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.06em;}
.info-value{font-size:13px;color:var(--gray-800);font-weight:600;word-break:break-all;overflow-wrap:anywhere;}
/* ANNOUNCEMENTS */
.ann-scroll{max-height:440px;overflow-y:auto;}
.ann-scroll::-webkit-scrollbar{width:4px;}
.ann-scroll::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:99px;}
.ann-item{padding:14px 16px;border-bottom:1px solid var(--gray-100);}
.ann-item:last-child{border-bottom:none;}
.ann-item:hover{background:var(--gray-50);}
.ann-meta{font-size:12px;font-weight:700;color:var(--blue);margin-bottom:7px;}
.ann-bubble{background:var(--gray-50);border:1px solid var(--gray-100);border-radius:6px;padding:10px 12px;font-size:13px;color:var(--gray-600);line-height:1.65;}
.ann-empty{font-size:13px;color:var(--gray-400);font-style:italic;}
/* RULES */
.rules-scroll{max-height:440px;overflow-y:auto;padding:16px 18px;}
.rules-scroll::-webkit-scrollbar{width:4px;}
.rules-scroll::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:99px;}
.rules-header{text-align:center;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--gray-100);}
.rules-header h3{font-size:14px;font-weight:800;color:var(--blue-dk);}
.rules-header p{font-size:11px;font-weight:700;color:var(--gray-600);margin-top:3px;letter-spacing:0.03em;}
.rules-section-title{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:var(--blue);margin:14px 0 10px;}
.rules-intro{font-size:13px;color:var(--gray-600);line-height:1.65;margin-bottom:12px;}
.rules-list{display:flex;flex-direction:column;gap:10px;}
.rule-item{display:flex;gap:10px;font-size:13px;color:var(--gray-600);line-height:1.6;}
.rule-num{min-width:22px;height:22px;border-radius:50%;background:var(--blue-lt);color:var(--blue);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;border:1px solid var(--blue-bd);}
@media(max-width:900px){.dashboard{grid-template-columns:1fr 1fr;}.dashboard>.card:first-child{grid-column:1/-1;}}
@media(max-width:600px){.dashboard{grid-template-columns:1fr;}nav{padding:0 16px;}.nav-brand{font-size:13px;}}
</style>
</head>
<body>
<nav>
  <div class="nav-brand">Dashboard</div>
  <div class="nav-links">
    <div class="nav-dropdown">
      <a href="#">Notification <span class="chevron">▾</span></a>
      <div class="dropdown-menu">
        <a href="#">All Notifications</a>
        <a href="#">Unread</a>
        <a href="#">Mark all as read</a>
      </div>
    </div>
    <a href="Homepage.php" class="active">Home</a>
    <a href="profile.php">Edit Profile</a>
    <a href="history.php">History</a>
    <a href="reservation.php">Reservation</a>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="dashboard">
  <!-- STUDENT INFO -->
  <div class="card">
    <div class="card-head">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <h2>Student Information</h2>
    </div>
    <div class="student-avatar">
      <div class="avatar-circle">
        <?php if ($photoSrc): ?>
          <img src="<?= $photoSrc ?>" alt="Profile"/>
        <?php else: ?>
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <?php endif; ?>
      </div>
    </div>
    <div class="student-info-list">
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8.5" cy="10" r="2.5"/><path d="M4 19c0-2.2 2-4 4.5-4s4.5 1.8 4.5 4"/></svg></span>
        <div class="info-content"><span class="info-label">ID Number</span><span class="info-value"><?= htmlspecialchars($_SESSION['id_number']??'') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        <div class="info-content"><span class="info-label">Full Name</span><span class="info-value"><?= htmlspecialchars($_SESSION['fullname']??'') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></span>
        <div class="info-content"><span class="info-label">Course</span><span class="info-value"><?= htmlspecialchars($_SESSION['course']??'') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
        <div class="info-content"><span class="info-label">Year Level</span><span class="info-value"><?= htmlspecialchars($_SESSION['year_level']??'') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
        <div class="info-content"><span class="info-label">Email Address</span><span class="info-value"><?= htmlspecialchars($_SESSION['email']??'') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
        <div class="info-content"><span class="info-label">Address</span><span class="info-value"><?= htmlspecialchars($_SESSION['address']??'') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
        <div class="info-content"><span class="info-label">Remaining Sessions</span><span class="info-value"><?= htmlspecialchars($_SESSION['session']??'') ?></span></div>
      </div>
    </div>
  </div>

  <!-- ANNOUNCEMENTS -->
  <div class="card">
    <div class="card-head">
      <svg viewBox="0 0 24 24"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <h2>Announcement</h2>
    </div>
    <div class="ann-scroll">
      <?php if ($announcements): foreach ($announcements as $ann): ?>
      <div class="ann-item">
        <div class="ann-meta"><?= htmlspecialchars($ann['admin_name']??'CCS Admin') ?> &nbsp;|&nbsp; <?= date('M d, Y',strtotime($ann['created_at'])) ?></div>
        <?php if (!empty($ann['content'])): ?><div class="ann-bubble"><?= htmlspecialchars($ann['content']) ?></div>
        <?php else: ?><div class="ann-empty">No content provided.</div><?php endif; ?>
      </div>
      <?php endforeach; else: ?>
      <div class="ann-item"><div class="ann-empty" style="padding:16px;">No announcements yet.</div></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RULES -->
  <div class="card">
    <div class="card-head">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <h2>Rules and Regulations</h2>
    </div>
    <div class="rules-scroll">
      <div class="rules-header"><h3>University of Cebu</h3><p>COLLEGE OF INFORMATION &amp; COMPUTER STUDIES</p></div>
      <div class="rules-section-title">Laboratory Rules and Regulations</div>
      <p class="rules-intro">To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:</p>
      <div class="rules-list">
        <div class="rule-item"><span class="rule-num">1</span><span>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones and other personal equipment must be switched off.</span></div>
        <div class="rule-item"><span class="rule-num">2</span><span>Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation.</span></div>
        <div class="rule-item"><span class="rule-num">3</span><span>Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing software are strictly prohibited.</span></div>
        <div class="rule-item"><span class="rule-num">4</span><span>Eating, drinking, and smoking inside the laboratory are strictly prohibited.</span></div>
        <div class="rule-item"><span class="rule-num">5</span><span>Students are responsible for keeping their workstations clean and orderly at all times.</span></div>
        <div class="rule-item"><span class="rule-num">6</span><span>Any damage to laboratory equipment due to negligence shall be the responsibility of the student concerned.</span></div>
        <div class="rule-item"><span class="rule-num">7</span><span>Only authorized personnel are allowed to install or remove software and hardware components.</span></div>
        <div class="rule-item"><span class="rule-num">8</span><span>Students must log out and properly shut down computers after use.</span></div>
      </div>
    </div>
  </div>
</div>
</body>
</html>