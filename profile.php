<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}
require_once 'db.php';

$student_id = (int)$_SESSION['student_id'];

// Handle mark-all-read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notif_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0")
        ->execute([$student_id]);
    header('Location: profile.php'); exit;
}

// Fetch notifications FIRST
try {
    $notif_stmt = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 30");
    $notif_stmt->execute([$student_id]);
    $notifications = $notif_stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}
$unread_count = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));

$success = $_SESSION['profile_success'] ?? '';
$error   = $_SESSION['profile_error']   ?? '';
unset($_SESSION['profile_success'], $_SESSION['profile_error']);

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if ($student && isset($student['profile_photo'])) {
    $_SESSION['profile_photo'] = $student['profile_photo'];
}

$photoSrc = (!empty($_SESSION['profile_photo']))
    ? 'uploads/profiles/' . htmlspecialchars($_SESSION['profile_photo'])
    : null;

// Parse notification to extract title and description
function parseNotification($text) {
    $text = trim($text);
    $text = preg_replace('/^[\x{1F000}-\x{1FFFF}\x{2600}-\x{27FF}\x{FE00}-\x{FEFF}\x{1F300}-\x{1F9FF}\s]+/u', '', $text);
    
    if (strpos($text, 'New announcement from') === 0) {
        $parts = explode(':', $text, 2);
        return [
            'title' => trim($parts[0]),
            'description' => isset($parts[1]) ? trim($parts[1]) : ''
        ];
    }
    
    if (strpos($text, 'feedback') !== false) {
        $cleanText = preg_replace('/^[💬]*\s*You received feedback from admin:\s*/i', '', $text);
        return [
            'title' => 'Feedback Received',
            'description' => trim($cleanText)
        ];
    }
    
    if (strpos($text, 'logged out') !== false || strpos($text, 'Logged out') !== false) {
        $cleanText = preg_replace('/^[📤]*\s*/', '', $text);
        return [
            'title' => 'Session Ended',
            'description' => trim($cleanText)
        ];
    }
    
    $lines = explode("\n", $text, 2);
    return [
        'title' => trim($lines[0]),
        'description' => isset($lines[1]) ? trim($lines[1]) : ''
    ];
}

function getNotificationIcon($message) {
    $msgLower = strtolower($message);
    if (strpos($msgLower, 'announcement') !== false) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
    } elseif (strpos($msgLower, 'feedback') !== false) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    } elseif (strpos($msgLower, 'logged out') !== false || strpos($msgLower, 'logout') !== false) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    } else {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
    }
}

function val($student, $key, $session_key = null) {
    if ($student && isset($student[$key]) && $student[$key] !== '') return htmlspecialchars($student[$key]);
    $sk = $session_key ?? $key;
    return htmlspecialchars($_SESSION[$sk] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Edit Profile</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;
  --gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-300:#b8c8dc;
  --gray-400:#8aaac8;--gray-500:#6b8fae;--gray-600:#3d607f;
  --gray-700:#2a4560;--gray-800:#1a2e45;--white:#fff;
  --radius:8px;--radius-lg:12px;
  --shadow:0 1px 4px rgba(0,58,107,0.08);--shadow-md:0 4px 20px rgba(0,58,107,0.12);
  --red:#dc2626;--red-lt:#fef2f2;--green:#16a34a;--green-lt:#f0fdf4;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;}

nav{background:var(--blue-dk);height:58px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,0.15);}
.nav-brand{font-size:15px;font-weight:800;color:#fff;letter-spacing:-0.02em;}
.nav-links{display:flex;align-items:center;gap:2px;}
.nav-links a{font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);text-decoration:none;padding:6px 11px;border-radius:6px;transition:all .15s;white-space:nowrap;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.1);}
.nav-links a.active{color:#89CFF1;font-weight:600;}
.btn-logout{background:#e53e3e !important;color:#fff !important;font-weight:700 !important;border-radius:6px;padding:6px 16px !important;margin-left:6px;}
.btn-logout:hover{background:#c53030 !important;}

/* NOTIFICATION BELL & DROPDOWN */
.notif-wrap{position:relative;}
.notif-btn{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);background:none;border:none;cursor:pointer;padding:6px 11px;border-radius:6px;font-family:'Plus Jakarta Sans',sans-serif;transition:all .15s;}
.notif-btn:hover{color:#fff;background:rgba(255,255,255,0.1);}
.bell-wrap{position:relative;display:inline-flex;align-items:center;}
.red-dot{position:absolute;top:-3px;right:-4px;width:9px;height:9px;background:#e53e3e;border-radius:50%;border:2px solid var(--blue-dk);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.7;transform:scale(1.25);}}
.notif-badge{background:#e53e3e;color:#fff;font-size:10px;font-weight:800;min-width:17px;height:17px;border-radius:99px;display:none;align-items:center;justify-content:center;padding:0 4px;}
.notif-badge.show{display:flex;}

.notif-dropdown{
  display:none;
  position:absolute;
  top:calc(100% + 8px);
  right:0;
  background:var(--white);
  border:1px solid var(--gray-200);
  border-radius:var(--radius-lg);
  box-shadow:var(--shadow-md);
  width:450px;
  max-width:calc(100vw - 40px);
  z-index:300;
  overflow:hidden;
}
.notif-dropdown.open{display:block;}
.notif-head{background:var(--blue-dk);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.notif-head-title{color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;}
.notif-new-pill{background:rgba(255,255,255,0.2);color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;}
.notif-mark{background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);color:#fff;font-size:11px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;padding:4px 10px;border-radius:5px;cursor:pointer;transition:background .15s;}
.notif-mark:hover{background:rgba(255,255,255,0.3);}
.notif-caught{font-size:11px;color:rgba(255,255,255,0.55);display:flex;align-items:center;gap:5px;}

.notif-list{
  max-height:500px;
  overflow-y:auto;
  overflow-x:hidden;
  background:var(--white);
}
.notif-list::-webkit-scrollbar{width:4px;}
.notif-list::-webkit-scrollbar-track{background:var(--gray-100);}
.notif-list::-webkit-scrollbar-thumb{background:var(--gray-300);border-radius:99px;}

.notif-item{
  display:flex;
  gap:14px;
  padding:16px 18px;
  border-bottom:1px solid var(--gray-100);
  transition:background .15s;
  text-decoration:none;
  color:inherit;
  cursor:pointer;
  width:100%;
}
.notif-item:first-child{padding-top:16px;}
.notif-item:last-child{border-bottom:none;padding-bottom:16px;}
.notif-item:hover{background:var(--gray-50);}
.notif-item.unread{background:var(--blue-lt);}
.notif-item.unread:hover{background:#dceef9;}

.notif-icon{
  display:inline-flex;
  align-items:flex-start;
  justify-content:center;
  width:36px;
  flex-shrink:0;
}
.notif-icon svg{
  width:20px;
  height:20px;
  stroke:#4B5563;
  stroke-width:1.8;
  fill:none;
}

.notif-content{
  flex:1;
  min-width:0;
}
.notif-title{
  font-size:14px;
  color:var(--gray-800);
  font-weight:700;
  margin-bottom:6px;
  line-height:1.4;
  word-break:break-word;
  white-space:normal;
}
.notif-desc{
  font-size:13px;
  color:var(--gray-600);
  line-height:1.5;
  word-break:break-word;
  white-space:normal;
  margin-bottom:8px;
}
.notif-desc br{
  display:block;
  content:"";
  margin-top:4px;
}
.notif-date{
  font-size:11px;
  color:var(--gray-400);
  display:flex;
  align-items:center;
  gap:6px;
  margin-top:4px;
}
.notif-date::before{
  content:"";
  display:inline-block;
  width:4px;
  height:4px;
  background:var(--gray-400);
  border-radius:50%;
}
.notif-empty{padding:48px 24px;text-align:center;font-size:13px;color:var(--gray-400);font-style:italic;}

/* PAGE STYLES */
.page-body{max-width:920px;margin:0 auto;padding:36px 20px 70px;}
.page-header{text-align:center;margin-bottom:28px;}
.page-header h1{font-size:23px;font-weight:800;color:var(--blue-dk);letter-spacing:-0.02em;}
.page-header p{font-size:13px;color:var(--gray-400);margin-top:4px;}
.alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--radius);font-size:13.5px;font-weight:600;margin-bottom:22px;}
.alert svg{width:17px;height:17px;flex-shrink:0;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.alert-success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
.alert-success svg{stroke:var(--green);}
.alert-error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
.alert-error svg{stroke:var(--red);}
.profile-layout{display:grid;grid-template-columns:270px 1fr;gap:22px;align-items:start;}
.card{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow-md);overflow:hidden;}
.card-head{background:var(--blue);padding:13px 18px;}
.card-head h3{color:#fff;font-size:13px;font-weight:700;}
.card-head p{color:rgba(255,255,255,0.55);font-size:12px;margin-top:2px;}
.photo-body{padding:24px 20px 26px;display:flex;flex-direction:column;align-items:center;gap:14px;}
.avatar-wrap{position:relative;width:116px;height:116px;}
.avatar-circle{width:116px;height:116px;border-radius:50%;border:3px solid var(--blue-bd);box-shadow:0 4px 16px rgba(0,58,107,0.15);background:linear-gradient(135deg,var(--blue-lt),#d0e7f5);display:flex;align-items:center;justify-content:center;overflow:hidden;}
.avatar-circle img{width:100%;height:100%;object-fit:cover;}
.avatar-circle svg{width:52px;height:52px;stroke:var(--blue);fill:none;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;}
.avatar-edit{position:absolute;bottom:4px;right:4px;width:30px;height:30px;border-radius:50%;background:var(--blue);border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,0.2);transition:background .15s;}
.avatar-edit:hover{background:var(--blue-dk);}
.avatar-edit svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.stu-name{font-size:15px;font-weight:800;color:var(--blue-dk);text-align:center;line-height:1.35;}
.stu-course{font-size:12px;color:var(--gray-400);text-align:center;margin-top:2px;}
.id-pill{background:var(--blue-lt);border:1px solid var(--blue-bd);border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700;color:var(--blue);}
.upload-zone{width:100%;border:2px dashed var(--gray-200);border-radius:var(--radius);padding:14px 10px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;position:relative;}
.upload-zone:hover{border-color:var(--blue);background:var(--blue-lt);}
.upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.uz-icon svg{width:26px;height:26px;stroke:var(--gray-400);fill:none;stroke-width:1.5;}
.uz-label{font-size:12.5px;color:var(--gray-600);margin-top:4px;font-weight:600;}
.uz-hint{font-size:11px;color:var(--gray-400);margin-top:2px;}
#photo-selected{font-size:12px;color:var(--blue);word-break:break-all;text-align:center;display:none;}
.btn-upload{width:100%;padding:9px;border:none;border-radius:var(--radius);background:var(--blue);color:#fff;font-size:13px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s;display:flex;align-items:center;justify-content:center;gap:6px;}
.btn-upload:hover{background:var(--blue-dk);}
.btn-upload svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;}
.form-body{padding:26px 28px 30px;}
.section-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.09em;color:var(--blue);padding-bottom:8px;border-bottom:1px solid var(--gray-100);margin-bottom:14px;margin-top:22px;}
.section-label:first-of-type{margin-top:0;}
.section-label .note{font-weight:400;text-transform:none;font-size:11px;color:var(--gray-400);margin-left:6px;letter-spacing:0;}
.field{margin-bottom:14px;}
.field label{display:flex;align-items:center;gap:5px;font-size:11px;font-weight:800;color:var(--gray-600);margin-bottom:5px;text-transform:uppercase;letter-spacing:0.04em;}
.field label svg{width:13px;height:13px;stroke:var(--gray-400);fill:none;stroke-width:2;}
.field input,.field select{width:100%;padding:9px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--gray-800);background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;}
.field input:focus,.field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.1);}
.field input[readonly]{background:var(--gray-50);color:var(--gray-400);cursor:not-allowed;}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.field-row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
.pw-wrap{position:relative;}
.pw-wrap input{padding-right:40px;}
.pw-eye{position:absolute;right:11px;top:50%;transform:translateY(-50%);cursor:pointer;display:flex;align-items:center;color:var(--gray-400);transition:color .15s;}
.pw-eye:hover{color:var(--blue);}
.pw-eye svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;}
hr.divider{border:none;border-top:1px solid var(--gray-100);margin:6px 0 18px;}
.btn-row{display:flex;gap:10px;}
.btn-save{flex:1;padding:11px;border:none;border-radius:var(--radius);background:var(--blue-dk);color:#fff;font-size:14px;font-weight:800;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s;display:flex;align-items:center;justify-content:center;gap:7px;}
.btn-save:hover{background:#002255;}
.btn-save svg{width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2;}
.btn-cancel{padding:11px 18px;border-radius:var(--radius);border:1.5px solid var(--gray-200);background:transparent;color:var(--gray-600);font-size:14px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:6px;}
.btn-cancel:hover{border-color:var(--blue);color:var(--blue);}
.btn-cancel svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;}

@media(max-width:760px){
  .profile-layout{grid-template-columns:1fr;}
  .field-row,.field-row3{grid-template-columns:1fr;}
  .form-body{padding:20px 18px 24px;}
  nav{padding:0 16px;}
}
@media(max-width:550px){
  .notif-dropdown{width:calc(100vw - 30px);right:-10px;}
  .notif-item{gap:10px;padding:12px 14px;}
  .notif-icon{width:30px;}
  .notif-title{font-size:13px;}
  .notif-desc{font-size:12px;}
}
</style>
</head>
<body>

<nav>
  <div class="nav-brand">Dashboard</div>
  <div class="nav-links">
    <div class="notif-wrap">
      <button class="notif-btn" onclick="toggleNotif()" id="notifBtn">
        <span class="bell-wrap">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <span class="red-dot" id="redDot" style="<?= $unread_count > 0 ? '' : 'display:none;' ?>"></span>
        </span>
        Notifications
        <span class="notif-badge <?= $unread_count > 0 ? 'show' : '' ?>" id="notifBadge">
          <?= $unread_count > 0 ? ($unread_count > 99 ? '99+' : $unread_count) : '' ?>
        </span>
      </button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-head">
          <span class="notif-head-title">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="2">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            Notifications
            <span class="notif-new-pill" id="newNotifPill" style="<?= $unread_count > 0 ? '' : 'display:none;' ?>"><?= $unread_count ?> new</span>
          </span>
          <span id="notifHeadRight">
            <?php if ($unread_count > 0): ?>
              <form method="POST" action="profile.php" style="margin:0;">
                <input type="hidden" name="mark_notif_read" value="1"/>
                <button type="submit" class="notif-mark">Mark all read</button>
              </form>
            <?php else: ?>
              <span class="notif-caught">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                All caught up
              </span>
            <?php endif; ?>
          </span>
        </div>
        <div class="notif-list" id="notifList">
          <div id="notifListContainer">
            <?php if (empty($notifications)): ?>
              <div class="notif-empty">No notifications yet.</div>
            <?php else: ?>
              <?php foreach ($notifications as $n):
                $parsed = parseNotification($n['message']);
                $ts = (int)strtotime($n['created_at']);
                $isUnread = $n['is_read'] == 0;
                $iconSvg = getNotificationIcon($n['message']);
              ?>
                <a href="notification_handler.php?id=<?= (int)$n['id'] ?>" class="notif-item <?= $isUnread ? 'unread' : 'read' ?>" data-id="<?= (int)$n['id'] ?>" data-read="<?= $n['is_read'] ?>">
                  <div class="notif-icon"><?= $iconSvg ?></div>
                  <div class="notif-content">
                    <div class="notif-title"><?= htmlspecialchars($parsed['title']) ?></div>
                    <?php if (!empty($parsed['description'])): ?>
                      <div class="notif-desc"><?= nl2br(htmlspecialchars($parsed['description'])) ?></div>
                    <?php endif; ?>
                    <div class="notif-date" data-ts="<?= $ts ?>"></div>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <a href="Homepage.php">Home</a>
    <a href="profile.php" class="active">Edit Profile</a>
    <a href="history.php">History</a>
    <a href="feedback.php">Feedback</a>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="page-body">
  <div class="page-header">
    <h1>Edit Profile</h1>
    <p>Manage your personal information and account settings</p>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success">
    <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-error">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <div class="profile-layout">
    <div class="card">
      <div class="card-head"><h3>Profile Photo</h3></div>
      <div class="photo-body">
        <div class="avatar-wrap">
          <div class="avatar-circle">
            <?php if ($photoSrc): ?>
              <img src="<?= $photoSrc ?>" alt="Profile Photo" id="avatarImg"/>
            <?php else: ?>
              <svg viewBox="0 0 24 24" id="avatarIcon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <img src="" alt="" id="avatarImg" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%;"/>
            <?php endif; ?>
          </div>
          <label class="avatar-edit" for="photo_file" title="Change photo">
            <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
          </label>
        </div>
        <div class="stu-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?></div>
        <div class="stu-course"><?= htmlspecialchars($_SESSION['course'] ?? '') ?> &bull; Year <?= htmlspecialchars($_SESSION['year_level'] ?? '') ?></div>
        <div class="id-pill">ID: <?= htmlspecialchars($_SESSION['id_number'] ?? '') ?></div>
        <form method="POST" action="update_profile.php" enctype="multipart/form-data" style="width:100%;display:flex;flex-direction:column;gap:10px;">
          <input type="hidden" name="action" value="upload_photo"/>
          <div class="upload-zone" id="uploadZone">
            <div class="uz-icon"><svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
            <div class="uz-label">Click or drag photo here</div>
            <div class="uz-hint">JPG, PNG, WEBP &middot; Max 2 MB</div>
            <input type="file" name="profile_photo" id="photo_file" accept="image/*"/>
          </div>
          <div id="photo-selected"></div>
          <button type="submit" class="btn-upload">
            <svg viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
            Upload Photo
          </button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h3>Personal Information</h3>
        <p>Fill in your details then click Save Changes</p>
      </div>
      <div class="form-body">
        <form method="POST" action="update_profile.php">
          <input type="hidden" name="action" value="save_info"/>

          <div class="section-label">Account</div>
          <div class="field">
            <label><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8.5" cy="10" r="2.5"/><path d="M4 19c0-2.2 2-4 4.5-4s4.5 1.8 4.5 4"/></svg>ID Number <small style="font-weight:400;text-transform:none;color:var(--gray-400);font-size:11px;">(cannot be changed)</small></label>
            <input type="text" value="<?= val($student,'id_number','id_number') ?>" readonly/>
          </div>

          <div class="section-label">Personal Details</div>
          <div class="field-row3">
            <div class="field"><label><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Last Name</label><input type="text" name="lastname" value="<?= val($student,'lastname') ?>" placeholder="Last name" required/></div>
            <div class="field"><label><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>First Name</label><input type="text" name="firstname" value="<?= val($student,'firstname') ?>" placeholder="First name" required/></div>
            <div class="field"><label><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Middle Name</label><input type="text" name="middlename" value="<?= val($student,'middlename') ?>" placeholder="Middle name"/></div>
          </div>
          <div class="field-row">
            <div class="field"><label><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Email Address</label><input type="email" name="email" value="<?= val($student,'email') ?>" placeholder="your@email.com" required/></div>
            <div class="field"><label><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>Address</label><input type="text" name="address" value="<?= val($student,'address') ?>" placeholder="Current home address"/></div>
          </div>

          <div class="section-label">Academic Information</div>
          <div class="field-row">
            <div class="field"><label><svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>Course</label><select name="course" required><option value="">Select course</option><?php foreach(['BSIT','BSCS'] as $c): $cur=$student['course']??$_SESSION['course']??''; ?><option value="<?=$c?>" <?=$cur===$c?'selected':''?>><?=$c?></option><?php endforeach; ?></select></div>
            <div class="field"><label><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Year Level</label><select name="year_level" required><?php $curY=(int)($student['year_level']??$_SESSION['year_level']??1); foreach(['1st Year'=>1,'2nd Year'=>2,'3rd Year'=>3,'4th Year'=>4] as $lbl=>$v): ?><option value="<?=$v?>" <?=$curY===$v?'selected':''?>><?=$lbl?></option><?php endforeach; ?></select></div>
          </div>

          <div class="section-label">Change Password <span class="note">(leave blank to keep current)</span></div>
          <div class="field-row">
            <div class="field"><label><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>New Password</label><div class="pw-wrap"><input type="password" name="new_password" id="pw1" placeholder="Min. 6 characters" autocomplete="new-password"/><span class="pw-eye" onclick="togglePw('pw1','eye1')"><svg id="eye1" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span></div></div>
            <div class="field"><label><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Confirm Password</label><div class="pw-wrap"><input type="password" name="confirm_password" id="pw2" placeholder="Repeat new password" autocomplete="new-password"/><span class="pw-eye" onclick="togglePw('pw2','eye2')"><svg id="eye2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span></div></div>
          </div>

          <hr class="divider"/>
          <div class="btn-row">
            <button type="reset" class="btn-cancel"><svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.9"/></svg>Reset</button>
            <button type="submit" name="edit_student" class="btn-save"><svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// FIXED: Proper time difference calculation
function relTime(ts) {
  if (!ts || ts <= 0) return '';
  var now = Math.floor(Date.now() / 1000);
  var diff = now - ts;
  
  if (diff < 0) return 'Just now';
  if (diff < 60) return 'Just now';
  if (diff < 3600) {
    var minutes = Math.floor(diff / 60);
    return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
  }
  if (diff < 86400) {
    var hours = Math.floor(diff / 3600);
    return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
  }
  if (diff < 604800) {
    var days = Math.floor(diff / 86400);
    return days + ' day' + (days > 1 ? 's' : '') + ' ago';
  }
  var d = new Date(ts * 1000);
  return d.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
}

function refreshTimestamps() {
  document.querySelectorAll('.notif-date[data-ts]').forEach(function(el) {
    var ts = parseInt(el.getAttribute('data-ts'), 10);
    if (!isNaN(ts) && ts > 0) {
      el.textContent = relTime(ts);
    }
  });
}

// REAL-TIME NOTIFICATION POLLING (No Page Refresh)
var currentUnreadCount = <?= $unread_count ?>;

function pollNotifications() {
  fetch('notification_ajax.php?action=fetch')
    .then(function(response) { return response.json(); })
    .then(function(data) {
      if (!data || !data.notifications) return;
      
      var container = document.getElementById('notifListContainer');
      var badge = document.getElementById('notifBadge');
      var redDot = document.getElementById('redDot');
      var newPill = document.getElementById('newNotifPill');
      var headRight = document.getElementById('notifHeadRight');
      
      currentUnreadCount = data.notifications.filter(function(n) { return parseInt(n.is_read) === 0; }).length;
      
      if (currentUnreadCount > 0) {
        badge.textContent = currentUnreadCount > 99 ? '99+' : currentUnreadCount;
        badge.classList.add('show');
        if (redDot) redDot.style.display = '';
        if (newPill) { newPill.style.display = ''; newPill.textContent = currentUnreadCount + ' new'; }
        headRight.innerHTML = '<form method="POST" action="profile.php" style="margin:0;"><input type="hidden" name="mark_notif_read" value="1"/><button type="submit" class="notif-mark">Mark all read</button></form>';
      } else {
        badge.classList.remove('show');
        badge.textContent = '';
        if (redDot) redDot.style.display = 'none';
        if (newPill) newPill.style.display = 'none';
        headRight.innerHTML = '<span class="notif-caught"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>All caught up</span>';
      }
      
      if (data.notifications.length > 0) {
        var html = '';
        data.notifications.forEach(function(n) {
          var parsed = parseNotificationJS(n.message);
          var iconSvg = getNotificationIconJS(n.message);
          var isUnreadClass = parseInt(n.is_read) === 0 ? 'unread' : 'read';
          html += '<a href="notification_handler.php?id=' + n.id + '" class="notif-item ' + isUnreadClass + '" data-id="' + n.id + '">' +
                  '<div class="notif-icon">' + iconSvg + '</div>' +
                  '<div class="notif-content">' +
                  '<div class="notif-title">' + escapeHtml(parsed.title) + '</div>' +
                  (parsed.description ? '<div class="notif-desc">' + escapeHtml(parsed.description).replace(/\n/g, '<br>') + '</div>' : '') +
                  '<div class="notif-date" data-ts="' + Math.floor(new Date(n.created_at.replace(' ', 'T')).getTime() / 1000) + '"></div>' +
                  '</div>' +
                  '</a>';
        });
        container.innerHTML = html;
      } else {
        container.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
      }
      
      refreshTimestamps();
    })
    .catch(function(error) {
      console.log('Polling error:', error);
    });
}

// JavaScript versions of PHP helper functions
function parseNotificationJS(text) {
  text = text.trim();
  text = text.replace(/^[\u{1F000}-\u{1FFFF}\u{2600}-\u{27FF}\u{FE00}-\u{FEFF}\u{1F300}-\u{1F9FF}\s]+/u, '');
  
  if (text.indexOf('New announcement from') === 0) {
    var parts = text.split(':');
    return { title: parts[0], description: parts[1] ? parts[1].trim() : '' };
  }
  if (text.indexOf('feedback') !== false) {
    var cleanText = text.replace(/^[💬]*\s*You received feedback from admin:\s*/i, '');
    return { title: 'Feedback Received', description: cleanText.trim() };
  }
  if (text.indexOf('logged out') !== false || text.indexOf('Logged out') !== false) {
    var cleanText = text.replace(/^[📤]*\s*/, '');
    return { title: 'Session Ended', description: cleanText.trim() };
  }
  var lines = text.split('\n');
  return { title: lines[0], description: lines[1] ? lines[1].trim() : '' };
}

function getNotificationIconJS(message) {
  var msgLower = message.toLowerCase();
  if (msgLower.indexOf('announcement') !== false) {
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
  } else if (msgLower.indexOf('feedback') !== false) {
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
  } else if (msgLower.indexOf('logged out') !== false || msgLower.indexOf('logout') !== false) {
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
  } else {
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
  }
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Initial load
refreshTimestamps();

// Start polling every 15 seconds
setInterval(refreshTimestamps, 30000);
setInterval(pollNotifications, 15000);
pollNotifications(); // Initial poll

var notifOpen = false;
function toggleNotif() {
  notifOpen = !notifOpen;
  document.getElementById('notifDropdown').classList.toggle('open', notifOpen);
  if (notifOpen) {
    refreshTimestamps();
  }
}
document.addEventListener('click', function(e) {
  var wrap = document.querySelector('.notif-wrap');
  if (wrap && !wrap.contains(e.target)) {
    notifOpen = false;
    document.getElementById('notifDropdown').classList.remove('open');
  }
});

function togglePw(inputId, iconId) {
  var input = document.getElementById(inputId);
  var icon = document.getElementById(iconId);
  if (input.type === 'password') {
    input.type = 'text';
    icon.innerHTML = '<line x1="1" y1="1" x2="23" y2="23"/><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>';
  } else {
    input.type = 'password';
    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}

document.getElementById('photo_file').addEventListener('change', function() {
  var file = this.files[0]; if (!file) return;
  var sel = document.getElementById('photo-selected');
  sel.textContent = file.name; sel.style.display = 'block';
  var reader = new FileReader();
  reader.onload = function(e) {
    var img = document.getElementById('avatarImg');
    var icon = document.getElementById('avatarIcon');
    img.src = e.target.result; img.style.display = 'block';
    if (icon) icon.style.display = 'none';
  };
  reader.readAsDataURL(file);
});
var zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.style.borderColor='var(--blue)'; zone.style.background='var(--blue-lt)'; });
zone.addEventListener('dragleave', function() { zone.style.borderColor=''; zone.style.background=''; });
zone.addEventListener('drop', function() { zone.style.borderColor=''; zone.style.background=''; });
</script>
</body>
</html>