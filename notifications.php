<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
  header('Location: login.php');
  exit;
}

$student_id = $_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notif_read'])) {
  try {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0")->execute([$student_id]);
  } catch (Exception $e) {}
}

try {
  $stmt = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 100");
  $stmt->execute([$student_id]);
  $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $notifications = [];
}

$unread_count = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));

function extractEmojiAndClean($text) {
  if (preg_match('/^([\p{Emoji}]+)\s*(.*)/u', $text, $matches)) {
    return ['emoji' => $matches[1], 'text' => trim($matches[2])];
  }
  return ['emoji' => '', 'text' => $text];
}

function parseNotification($text) {
  $lines = explode("\n", trim($text));
  $title = trim($lines[0]);
  $description = isset($lines[1]) ? trim($lines[1]) : '';
  if (empty($description) && strpos($title, ':') !== false) {
    [$title, $description] = explode(':', $title, 2);
    $description = trim($description);
  }
  return ['title' => $title, 'description' => $description];
}

function getNotifStyle($emoji) {
  $map = [
    '📤' => ['bg' => '#FFF0E0', 'stroke' => '#EA580C', 'icon' => 'upload'],
    '💬' => ['bg' => '#E6F1FB', 'stroke' => '#185FA5', 'icon' => 'message'],
    '📢' => ['bg' => '#EAF3DE', 'stroke' => '#3B6D11', 'icon' => 'announcement'],
    '✅' => ['bg' => '#EAF3DE', 'stroke' => '#3B6D11', 'icon' => 'check'],
    '⚠️' => ['bg' => '#FAEEDA', 'stroke' => '#854F0B', 'icon' => 'warning'],
    '📧' => ['bg' => '#E6F1FB', 'stroke' => '#185FA5', 'icon' => 'email'],
    '📝' => ['bg' => '#EEEDFE', 'stroke' => '#534AB7', 'icon' => 'note'],
    '🎉' => ['bg' => '#FBEAF0', 'stroke' => '#993556', 'icon' => 'celebrate'],
    '🔔' => ['bg' => '#FFF0E0', 'stroke' => '#EA580C', 'icon' => 'bell'],
  ];
  return $map[$emoji] ?? ['bg' => '#F1EFE8', 'stroke' => '#5F5E5A', 'icon' => 'bell'];
}

function getNotifIcon($icon, $stroke) {
  $icons = [
    'bell'         => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    'message'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'check'        => '<polyline points="20 6 9 17 4 12"/>',
    'warning'      => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    'email'        => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
    'note'         => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    'announcement' => '<path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    'upload'       => '<polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>',
    'celebrate'    => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
  ];
  $paths = $icons[$icon] ?? $icons['bell'];
  return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="'.$stroke.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
}

function getTimeAgo($timestamp) {
  $diff = time() - $timestamp;
  if ($diff < 60)      return 'just now';
  if ($diff < 3600)    return floor($diff/60).'m ago';
  if ($diff < 86400)   return floor($diff/3600).'h ago';
  if ($diff < 604800)  return floor($diff/86400).'d ago';
  return date('M d, Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Notifications</title>
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

.page-body{max-width:720px;margin:0 auto;padding:36px 20px 60px;}

.panel{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow-md);overflow:hidden;}
.panel-header{padding:20px 24px 16px;border-bottom:1px solid var(--gray-100);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
.panel-header-left{flex:1;}
.panel-title{font-size:20px;font-weight:800;color:var(--blue-dk);}
.panel-sub{font-size:13px;color:var(--gray-400);margin-top:4px;}
.panel-icon{width:38px;height:38px;border-radius:var(--radius);border:1px solid var(--gray-200);display:flex;align-items:center;justify-content:center;flex-shrink:0;}

.mark-all-form{padding:12px 24px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:flex-end;}
.btn-mark{background:var(--blue-lt);color:var(--blue-dk);border:1px solid var(--blue-bd);font-size:12px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;padding:6px 14px;border-radius:6px;cursor:pointer;transition:background .15s;}
.btn-mark:hover{background:var(--blue-bd);}

.notif-list{padding:0 24px;}

.notif-item{display:flex;gap:14px;padding:16px 0;border-bottom:1px solid var(--gray-100);align-items:flex-start;text-decoration:none;color:inherit;transition:background .12s;}
.notif-item:last-child{border-bottom:none;}

.icon-circle{width:40px;height:40px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;}

.notif-body{flex:1;min-width:0;}
.notif-title{font-size:14px;font-weight:700;color:var(--gray-800);line-height:1.4;}
.notif-title.unread{color:var(--blue-dk);}
.notif-desc{font-size:13px;color:var(--gray-600);margin-top:3px;line-height:1.5;}
.notif-time{font-size:12px;color:var(--gray-400);margin-top:4px;font-weight:500;}

.notif-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;}
.notif-time-right{font-size:12px;color:var(--gray-400);white-space:nowrap;font-weight:500;}
.unread-dot{width:8px;height:8px;background:var(--blue);border-radius:50%;}

.empty-state{text-align:center;padding:60px 20px;}
.empty-icon{display:flex;justify-content:center;margin-bottom:16px;}
.empty-title{font-size:16px;font-weight:700;color:var(--gray-800);margin-bottom:6px;}
.empty-desc{font-size:13px;color:var(--gray-400);}

@media(max-width:600px){nav{padding:0 16px;}.notif-list,.panel-header,.mark-all-form{padding-left:16px;padding-right:16px;}}
</style>
</head>
<body>

<nav>
  <div class="nav-brand">Dashboard</div>
  <div class="nav-links">
    <a href="Homepage.php">Home</a>
    <a href="profile.php">Edit Profile</a>
    <a href="history.php">History</a>
    <a href="feedback.php">Feedback</a>
    <a href="notifications.php" class="active">Notifications</a>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="page-body">
  <div class="panel">
    <div class="panel-header">
      <div class="panel-header-left">
        <div class="panel-title">Notifications</div>
        <div class="panel-sub">
          <?php if ($unread_count > 0): ?>
            You have <strong><?= $unread_count ?></strong> unread notification<?= $unread_count !== 1 ? 's' : '' ?>.
          <?php else: ?>
            You're all caught up!
          <?php endif; ?>
        </div>
      </div>
      <div class="panel-icon">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--gray-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
      </div>
    </div>

    <?php if ($unread_count > 0): ?>
    <div class="mark-all-form">
      <form method="POST" action="notifications.php">
        <input type="hidden" name="mark_notif_read" value="1"/>
        <button type="submit" class="btn-mark">Mark all as read</button>
      </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($notifications)): ?>
    <div class="notif-list">
      <?php foreach ($notifications as $n):
        $ts        = (int)strtotime($n['created_at']);
        $emojiData = extractEmojiAndClean($n['message']);
        $parsed    = parseNotification($emojiData['text']);
        $style     = getNotifStyle($emojiData['emoji']);
        $iconHtml  = getNotifIcon($style['icon'], $style['stroke']);
        $isUnread  = $n['is_read'] == 0;
      ?>
      <a href="notification_handler.php?id=<?= (int)$n['id'] ?>" class="notif-item">
        <div class="icon-circle" style="background:<?= htmlspecialchars($style['bg']) ?>;">
          <?= $iconHtml ?>
        </div>
        <div class="notif-body">
          <div class="notif-title <?= $isUnread ? 'unread' : '' ?>"><?= htmlspecialchars($parsed['title']) ?></div>
          <?php if (!empty($parsed['description'])): ?>
            <div class="notif-desc"><?= htmlspecialchars($parsed['description']) ?></div>
          <?php endif; ?>
        </div>
        <div class="notif-right">
          <div class="notif-time-right"><?= getTimeAgo($ts) ?></div>
          <?php if ($isUnread): ?>
            <div class="unread-dot"></div>
          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--gray-400)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
      </div>
      <div class="empty-title">No notifications yet</div>
      <div class="empty-desc">You'll see your notifications here when they arrive.</div>
    </div>
    <?php endif; ?>

  </div>
</div>

</body>
</html>