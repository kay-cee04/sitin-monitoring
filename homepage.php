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
    header('Location: Homepage.php'); exit;
}

// Refresh student session data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
$stmt->execute([$student_id]);
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

// Fetch notifications
try {
    $notif_stmt = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 30");
    $notif_stmt->execute([$student_id]);
    $notifications = $notif_stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}
$unread_count = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));

// Extract emoji and clean message
function extractEmojiAndClean($text) {
  if (preg_match('/^([\p{Emoji}]+)\s*(.*)/u', $text, $matches)) {
    return ['emoji' => $matches[1], 'text' => trim($matches[2])];
  }
  return ['emoji' => '🔔', 'text' => $text];
}

// Get icon based on message content
function getNotificationIcon($emoji) {
  $iconMap = [
    '📤' => '📤',
    '💬' => '💬',
    '📢' => '📢',
    '✅' => '✅',
    '⚠️' => '⚠️',
    '📧' => '📧',
    '📝' => '📝',
    '🎉' => '🎉',
  ];
  return $iconMap[$emoji] ?? '🔔';
}

// Parse notification to extract title and description
function parseNotification($text) {
  $lines = explode("\n", trim($text));
  $title = trim($lines[0]);
  $description = isset($lines[1]) ? trim($lines[1]) : (count($lines) > 1 ? '' : '');
  
  if (empty($description) && strpos($title, ':') !== false) {
    list($title, $description) = explode(':', $title, 2);
    $description = trim($description);
  }
  
  return ['title' => $title, 'description' => $description];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Home</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;
  --gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-400:#8aaac8;
  --gray-600:#3d607f;--gray-800:#1a2e45;--white:#fff;
  --radius:8px;--radius-lg:12px;
  --shadow:0 1px 3px rgba(0,58,107,0.08);--shadow-md:0 4px 20px rgba(0,58,107,0.11);
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

/* ── BELL ── */
.notif-wrap{position:relative;}
.notif-btn{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);background:none;border:none;cursor:pointer;padding:6px 11px;border-radius:6px;font-family:'Plus Jakarta Sans',sans-serif;transition:all .15s;}
.notif-btn:hover{color:#fff;background:rgba(255,255,255,0.1);}
.bell-wrap{position:relative;display:inline-flex;align-items:center;}
.red-dot{position:absolute;top:-3px;right:-4px;width:9px;height:9px;background:#e53e3e;border-radius:50%;border:2px solid var(--blue-dk);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.7;transform:scale(1.25);}}
.notif-badge{background:#e53e3e;color:#fff;font-size:10px;font-weight:800;min-width:17px;height:17px;border-radius:99px;display:none;align-items:center;justify-content:center;padding:0 4px;}
.notif-badge.show{display:flex;}

/* ── DROPDOWN ── */
.notif-dropdown{display:none;position:absolute;top:calc(100% + 8px);right:0;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);width:340px;z-index:300;overflow:hidden;}
.notif-dropdown.open{display:block;}
.notif-head{background:var(--blue-dk);padding:12px 16px;display:flex;align-items:center;justify-content:space-between;}
.notif-head-title{color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;}
.notif-new-pill{background:rgba(255,255,255,0.2);color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;}
.notif-mark{background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);color:#fff;font-size:11px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;padding:4px 10px;border-radius:5px;cursor:pointer;transition:background .15s;}
.notif-mark:hover{background:rgba(255,255,255,0.3);}
.notif-caught{font-size:11px;color:rgba(255,255,255,0.55);}
.notif-list{max-height:400px;overflow-y:auto;background:var(--white);}
.notif-list::-webkit-scrollbar{width:4px;}
.notif-list::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:99px;}

/* ── NOTIFICATION ROWS — simple clean style ── */
.notif-item{display:flex;gap:12px;padding:12px 8px;border-bottom:1px solid var(--gray-100);transition:background .12s;align-items:flex-start;text-decoration:none;color:inherit;cursor:pointer;background:transparent;border-radius:0;margin:0;}
.notif-item:first-child{padding-top:8px;}
.notif-item:last-child{border-bottom:none;padding-bottom:8px;}
.notif-item:hover{background:var(--gray-50);}
.notif-item.unread{background:transparent;}
.notif-icon{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;flex-shrink:0;margin-top:2px;}
.notif-icon.type-logout{background:#fff3e0;color:#e65100;}
.notif-icon.type-feedback{background:#e3f2fd;color:#1565c0;}
.notif-icon.type-announce{background:#e8f5e9;color:#2e7d32;}
.notif-icon.type-default{background:#f3e5f5;color:#6a1b9a;}
.notif-content{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px;}
.notif-title{font-size:13px;color:var(--gray-800);font-weight:700;line-height:1.4;word-break:break-word;}
.notif-desc{font-size:12px;color:var(--gray-600);line-height:1.4;word-break:break-word;font-weight:400;}
.notif-time-right{font-size:12px;color:var(--gray-500);text-align:right;white-space:nowrap;font-weight:500;flex-shrink:0;}
.notif-empty{padding:32px 16px;text-align:center;font-size:13px;color:var(--gray-400);font-style:italic;}

/* ── DASHBOARD GRID ── */
.dashboard{max-width:1280px;margin:0 auto;padding:24px 20px;display:grid;grid-template-columns:280px 1fr 300px;gap:20px;align-items:start;}
.card{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow);overflow:hidden;}
.card-head{background:var(--blue);padding:12px 16px;display:flex;align-items:center;gap:8px;}
.card-head h2{color:#fff;font-size:13px;font-weight:700;}
.card-head svg{width:15px;height:15px;stroke:rgba(255,255,255,0.8);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.student-avatar{display:flex;flex-direction:column;align-items:center;padding:22px 16px 18px;border-bottom:1px solid var(--gray-100);}
.avatar-circle{width:92px;height:92px;border-radius:50%;background:var(--blue-lt);border:3px solid var(--blue-bd);display:flex;align-items:center;justify-content:center;box-shadow:0 3px 12px rgba(0,58,107,0.15);overflow:hidden;}
.avatar-circle img{width:100%;height:100%;object-fit:cover;}
.avatar-circle svg{width:42px;height:42px;stroke:var(--blue);fill:none;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;}
.student-info-list{padding:12px 16px;}
.info-row{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--gray-100);}
.info-row:last-child{border-bottom:none;}
.info-icon{display:flex;align-items:flex-start;justify-content:center;width:18px;flex-shrink:0;padding-top:2px;}
.info-icon svg{width:13px;height:13px;stroke:var(--blue);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.info-content{display:flex;flex-direction:column;gap:1px;min-width:0;flex:1;}
.info-label{font-size:10px;font-weight:800;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.06em;}
.info-value{font-size:13px;color:var(--gray-800);font-weight:600;word-break:break-all;}
.ann-scroll{max-height:440px;overflow-y:auto;}
.ann-scroll::-webkit-scrollbar{width:4px;}
.ann-scroll::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:99px;}
.ann-item{padding:14px 16px;border-bottom:1px solid var(--gray-100);}
.ann-item:last-child{border-bottom:none;}
.ann-item:hover{background:var(--gray-50);}
.ann-meta{font-size:12px;font-weight:700;color:var(--blue);margin-bottom:7px;}
.ann-bubble{background:var(--gray-50);border:1px solid var(--gray-100);border-radius:6px;padding:10px 12px;font-size:13px;color:var(--gray-600);line-height:1.65;}
.ann-empty{font-size:13px;color:var(--gray-400);font-style:italic;}
.rules-scroll{max-height:440px;overflow-y:auto;padding:16px 18px;}
.rules-scroll::-webkit-scrollbar{width:4px;}
.rules-scroll::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:99px;}
.rules-header{text-align:center;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--gray-100);}
.rules-header h3{font-size:14px;font-weight:800;color:var(--blue-dk);}
.rules-header p{font-size:11px;font-weight:700;color:var(--gray-600);margin-top:3px;}
.rules-section-title{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:var(--blue);margin:14px 0 10px;}
.rules-intro{font-size:13px;color:var(--gray-600);line-height:1.65;margin-bottom:12px;}
.rules-list{display:flex;flex-direction:column;gap:10px;}
.rule-item{display:flex;gap:10px;font-size:13px;color:var(--gray-600);line-height:1.6;}
.rule-num{min-width:22px;height:22px;border-radius:50%;background:var(--blue-lt);color:var(--blue);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;border:1px solid var(--blue-bd);}
@media(max-width:900px){.dashboard{grid-template-columns:1fr 1fr;}.dashboard>.card:first-child{grid-column:1/-1;}}
@media(max-width:600px){.dashboard{grid-template-columns:1fr;}nav{padding:0 16px;}}
</style>
</head>
<body>

<nav>
  <div class="nav-brand">Dashboard</div>
  <div class="nav-links">
    <div class="notif-wrap">
      <button class="notif-btn" onclick="toggleNotif()" id="notifBtn">
        <span class="bell-wrap">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <?php if ($unread_count > 0): ?><span class="red-dot" id="redDot"></span><?php endif; ?>
        </span>
        Notifications
        <span class="notif-badge <?= $unread_count > 0 ? 'show' : '' ?>" id="notifBadge">
          <?= $unread_count > 0 ? ($unread_count > 99 ? '99+' : $unread_count) : '' ?>
        </span>
      </button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-head">
          <span class="notif-head-title">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            Notifications
          </span>
          <span id="notifHeadRight">
            <?php if ($unread_count > 0): ?>
              <form method="POST" action="Homepage.php" style="margin:0;">
                <input type="hidden" name="mark_notif_read" value="1"/>
                <button type="submit" class="notif-mark">Mark all read</button>
              </form>
            <?php else: ?>
              <span class="notif-caught">All caught up &#10003;</span>
            <?php endif; ?>
          </span>
        </div>
        <div class="notif-list" id="notifList">
          <?php if (empty($notifications)): ?>
            <div class="notif-empty">No notifications yet.</div>
          <?php else: ?>
            <?php foreach ($notifications as $n):
              // Extract emoji and message
              $emojiData = extractEmojiAndClean($n['message']);
              
              // Parse notification into title and description
              $parsed = parseNotification($emojiData['text']);
              $ts = (int)strtotime($n['created_at']);
            ?>
              <a href="notification_handler.php?id=<?= (int)$n['id'] ?>" class="notif-item <?= $n['is_read'] == 0 ? 'unread' : 'read' ?>" data-id="<?= (int)$n['id'] ?>">
                <div class="notif-icon"></div>
                <div class="notif-content">
                  <div class="notif-title"><?= htmlspecialchars($parsed['title']) ?></div>
                  <?php if (!empty($parsed['description'])): ?>
                    <div class="notif-desc"><?= htmlspecialchars($parsed['description']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="notif-time-right" data-ts="<?= $ts ?>"></div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <a href="Homepage.php" class="active">Home</a>
    <a href="profile.php">Edit Profile</a>
    <a href="history.php">History</a>
    <a href="feedback.php">Feedback</a>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="dashboard">
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
        <div class="info-content"><span class="info-label">ID Number</span><span class="info-value"><?= htmlspecialchars($_SESSION['id_number'] ?? '') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        <div class="info-content"><span class="info-label">Full Name</span><span class="info-value"><?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></span>
        <div class="info-content"><span class="info-label">Course</span><span class="info-value"><?= htmlspecialchars($_SESSION['course'] ?? '') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
        <div class="info-content"><span class="info-label">Year Level</span><span class="info-value"><?= htmlspecialchars($_SESSION['year_level'] ?? '') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
        <div class="info-content"><span class="info-label">Email Address</span><span class="info-value"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
        <div class="info-content"><span class="info-label">Address</span><span class="info-value"><?= htmlspecialchars($_SESSION['address'] ?? '') ?></span></div>
      </div>
      <div class="info-row">
        <span class="info-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
        <div class="info-content"><span class="info-label">Remaining Sessions</span><span class="info-value"><?= htmlspecialchars($_SESSION['session'] ?? '') ?></span></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <svg viewBox="0 0 24 24"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <h2>Announcement</h2>
    </div>
    <div class="ann-scroll">
      <?php if ($announcements): ?>
        <?php foreach ($announcements as $ann): ?>
        <div class="ann-item">
          <div class="ann-meta"><?= htmlspecialchars($ann['admin_name'] ?? 'CCS Admin') ?> &nbsp;|&nbsp; <?= date('M d, Y', strtotime($ann['created_at'])) ?></div>
          <?php if (!empty($ann['content'])): ?>
            <div class="ann-bubble"><?= htmlspecialchars($ann['content']) ?></div>
          <?php else: ?>
            <div class="ann-empty">No content provided.</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="ann-item"><div class="ann-empty" style="padding:16px;">No announcements yet.</div></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <h2>Rules and Regulations</h2>
    </div>
    <div class="rules-scroll">
      <div class="rules-header">
        <h3>University of Cebu</h3>
        <p>COLLEGE OF INFORMATION &amp; COMPUTER STUDIES</p>
      </div>
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

<script>
// NOTIFICATION SYSTEM

function relTime(ts) {
  if (!ts || ts <= 0) return '';
  var diff = Math.floor(Date.now() / 1000) - ts;
  if (diff < 60)     return 'Just now';
  if (diff < 3600)   return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400)  return Math.floor(diff / 3600) + 'h ago';
  if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
  var d = new Date(ts * 1000);
  return d.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
}

function refreshTimestamps() {
  document.querySelectorAll('.notif-time[data-ts], .notif-time-right[data-ts]').forEach(function(el) {
    var ts = parseInt(el.getAttribute('data-ts'), 10);
    el.textContent = relTime(ts);
  });
}
refreshTimestamps();
setInterval(refreshTimestamps, 60000);

// Initial poll on page load
window.addEventListener('load', function() {
  pollNotifications();
});

function stripEmoji(str) {
  return str.replace(/^[\u{1F000}-\u{1FFFF}\u{2600}-\u{27FF}\u{FE00}-\u{FEFF}\u{1F300}-\u{1F9FF}\s]+/gu, '').trim();
}

//  NOTIFICATION POLLING 
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function parseNotification(msg) {
  msg = stripEmoji(msg);
  if (msg.includes('New announcement from')) {
    var parts = msg.split(': ');
    return {
      title: parts[0],
      desc: parts[1] ? parts[1].substring(0, 85) : '',
    };
  }
  return {
    title: msg.substring(0, 45),
    desc: msg.substring(45, 100),
  };
}
function highlightAnnouncementTitle(title) {
  if (title.includes('New announcement from')) {
    return '<span class="announcement-highlight">' + escHtml(title) + '</span>';
  }
  return title;
}
function formatRelTime(created_at) {
  var ts = Math.floor(new Date(created_at.replace(' ','T')).getTime() / 1000);
  var now = Math.floor(Date.now() / 1000);
  var diff = now - ts;
  if (diff < 60) return 'Just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
  return 'Long ago';
}
function getNotifIconHtml(msg) {
  var m = msg.toLowerCase();
  if (m.indexOf('logged out') !== -1 || m.indexOf('log out') !== -1 || m.indexOf('logout') !== -1) {
    return '<div class="notif-icon type-logout"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></div>';
  } else if (m.indexOf('feedback') !== -1) {
    return '<div class="notif-icon type-feedback"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>';
  } else if (m.indexOf('announcement') !== -1) {
    return '<div class="notif-icon type-announce"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/></svg></div>';
  }
  return '<div class="notif-icon type-default"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>';
}

function renderNotifItem(n) {
  var notif = parseNotification(n.message);
  var relTime = formatRelTime(n.created_at);
  var title = escHtml(notif.title);
  var desc = escHtml(notif.desc);
  var cls = parseInt(n.is_read) === 0 ? 'notif-item unread' : 'notif-item read';
  return '<a href="notification_handler.php?id=' + n.id + '" class="' + cls + '" data-id="' + n.id + '" style="text-decoration:none;color:inherit;">'
       + getNotifIconHtml(n.message)
       + '<div class="notif-content">'
       + '<div class="notif-title">' + title + '</div>'
       + (desc ? '<div class="notif-desc">' + desc + '</div>' : '')
       + '</div>'
       + '<div class="notif-time-right">' + relTime + '</div>'
       + '</a>';
}

function pollNotifications() {
  fetch('notification_ajax.php?action=fetch')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (!data || !data.notifications) return;
      var list      = document.getElementById('notifList');
      var badge     = document.getElementById('notifBadge');
      var headRight = document.getElementById('notifHeadRight');

      list.innerHTML = data.notifications.length
        ? data.notifications.map(renderNotifItem).join('')
        : '<div class="notif-empty">No notifications yet.</div>';

      refreshTimestamps(); // stamp newly rendered rows

      var unread = data.notifications.filter(function(n){ return parseInt(n.is_read) === 0; }).length;
      if (unread > 0) {
        badge.textContent = unread > 99 ? '99+' : String(unread);
        badge.classList.add('show');
        if (!document.getElementById('redDot')) {
          var dot = document.createElement('span');
          dot.className = 'red-dot'; dot.id = 'redDot';
          document.querySelector('.bell-wrap').appendChild(dot);
        }
        headRight.innerHTML = '<form method="POST" action="Homepage.php" style="margin:0;"><input type="hidden" name="mark_notif_read" value="1"/><button type="submit" class="notif-mark">Mark all read</button></form>';
      } else {
        badge.textContent = ''; badge.classList.remove('show');
        var dot = document.getElementById('redDot'); if (dot) dot.remove();
        headRight.innerHTML = '<span class="notif-caught">All caught up ✓</span>';
      }
    })
    .catch(function(){});
}
setInterval(pollNotifications, 30000);

//  BELL TOGGLE 
var notifOpen = false;
function toggleNotif() {
  notifOpen = !notifOpen;
  document.getElementById('notifDropdown').classList.toggle('open', notifOpen);
}
document.addEventListener('click', function(e) {
  var wrap = document.querySelector('.notif-wrap');
  if (wrap && !wrap.contains(e.target)) {
    notifOpen = false;
    document.getElementById('notifDropdown').classList.remove('open');
  }
});
</script>
</body>
</html>