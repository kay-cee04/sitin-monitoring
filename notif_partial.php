<?php
/**
 * notif_partial.php - Shared navigation + notifications + dark mode for all student pages
 */

if (defined('NOTIF_PARTIAL_LOADED')) return;
define('NOTIF_PARTIAL_LOADED', true);

$_np_student_id = (int)($_SESSION['student_id'] ?? 0);
$_np_notifications = [];
$_np_unread_count = 0;

if ($_np_student_id) {
    try {
        $np_stmt = $GLOBALS['pdo']->prepare(
            "SELECT id, message, is_read, created_at, type 
             FROM notifications 
             WHERE student_id = ? 
             ORDER BY created_at DESC 
             LIMIT 50"
        );
        $np_stmt->execute([$_np_student_id]);
        $_np_notifications = $np_stmt->fetchAll(PDO::FETCH_ASSOC);
        $_np_unread_count = count(array_filter($_np_notifications, fn($n) => $n['is_read'] == 0));
    } catch (Exception $e) {
        $_np_notifications = [];
    }
}

$_np_page = $GLOBALS['CURRENT_PAGE'] ?? 'home';

$_np_page_file = [
    'home' => 'Homepage.php',
    'profile' => 'profile.php',
    'history' => 'history.php',
    'feedback' => 'feedback.php',
    'reservation' => 'reservation.php',
    'software' => 'software.php',
][$_np_page] ?? 'Homepage.php';

function np_parseNotification($text, $type = '') {
    $text = trim($text);
    $text = preg_replace('/^[\x{1F000}-\x{1FFFF}\x{2600}-\x{27FF}\x{FE00}-\x{FEFF}\x{1F300}-\x{1F9FF}\s]+/u', '', $text);
    
    if ($type === 'announcement' || strpos($text, 'New announcement') === 0) {
        $parts = explode(':', $text, 2);
        return ['title' => '📢 New Announcement', 'description' => isset($parts[1]) ? trim($parts[1]) : trim($text)];
    }
    if ($type === 'reservation_approved') {
        return ['title' => '✅ Reservation Approved', 'description' => str_replace('Reservation approved:', '', $text)];
    }
    if ($type === 'reservation_rejected') {
        return ['title' => '❌ Reservation Rejected', 'description' => str_replace('Reservation rejected:', '', $text)];
    }
    if ($type === 'feedback' || strpos($text, 'feedback') !== false) {
        $clean = preg_replace('/^[💬]*\s*You received feedback from admin:\s*/i', '', $text);
        return ['title' => '💬 New Feedback', 'description' => trim($clean)];
    }
    if ($type === 'logout' || strpos($text, 'logged out') !== false) {
        return ['title' => '📤 Session Ended', 'description' => 'You have been logged out of your session.'];
    }
    if ($type === 'session_updated') {
        return ['title' => '📅 Session Updated', 'description' => $text];
    }
    
    $lines = explode("\n", $text, 2);
    return ['title' => trim($lines[0]), 'description' => isset($lines[1]) ? trim($lines[1]) : ''];
}

function np_getNotifIcon($type, $message = '') {
    $m = strtolower($type . ' ' . $message);
    if (strpos($m, 'announcement') !== false)
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
    if (strpos($m, 'reservation') !== false && strpos($m, 'approv') !== false)
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
    if (strpos($m, 'reservation') !== false && strpos($m, 'reject') !== false)
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="18" y1="6" x2="6" y2="18"/></svg>';
    if (strpos($m, 'feedback') !== false)
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    if (strpos($m, 'logout') !== false || strpos($m, 'session') !== false)
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
}

function np_active($page, $current) {
    return $page === $current ? ' class="active"' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;
  --gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-300:#b8c8dc;
  --gray-400:#8aaac8;--gray-500:#6b8fae;--gray-600:#3d607f;
  --gray-700:#2a4560;--gray-800:#1a2e45;--white:#fff;
  --radius:8px;--radius-lg:12px;
  --shadow:0 1px 3px rgba(0,58,107,0.08);--shadow-md:0 4px 20px rgba(0,58,107,0.11);
  --green:#16a34a;--green-lt:#f0fdf4;--red:#dc2626;--red-lt:#fef2f2;--yellow:#d97706;
}

/* DARK MODE - Full coverage */
body.dark-mode {
  --blue:#3b82f6;
  --blue-dk:#1e3a5f;
  --blue-lt:#1e293b;
  --blue-bd:#3b82f6;
  --gray-50:#0f172a;
  --gray-100:#1e293b;
  --gray-200:#334155;
  --gray-300:#475569;
  --gray-400:#94a3b8;
  --gray-500:#cbd5e1;
  --gray-600:#cbd5e1;
  --gray-700:#e2e8f0;
  --gray-800:#f1f5f9;
  --white:#1e293b;
  --shadow:0 1px 3px rgba(0,0,0,0.3);
  --shadow-md:0 4px 20px rgba(0,0,0,0.4);
  --green-lt:#064e3b;
  --red-lt:#7f1d1d;
}

*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;transition:background 0.2s, color 0.2s;}

nav{background:var(--blue-dk);height:58px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,0.15);}
.nav-brand{font-size:15px;font-weight:800;color:#fff;letter-spacing:-0.02em;}
.nav-links{display:flex;align-items:center;gap:2px;}
.nav-links a{font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);text-decoration:none;padding:6px 11px;border-radius:6px;transition:all .15s;white-space:nowrap;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.1);}
.nav-links a.active{color:#89CFF1;font-weight:600;}
.btn-logout{background:#e53e3e !important;color:#fff !important;font-weight:700 !important;border-radius:6px;padding:6px 16px !important;margin-left:6px;}
.btn-logout:hover{background:#c53030 !important;}
.btn-dark-toggle{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.75);border-radius:6px;padding:5px 9px;cursor:pointer;display:flex;align-items:center;transition:all .15s;margin-left:4px;}
.btn-dark-toggle:hover{background:rgba(255,255,255,0.2);color:#fff;}

/* NOTIFICATION BELL & DROPDOWN */
.notif-wrap{position:relative;}
.notif-btn{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);background:none;border:none;cursor:pointer;padding:6px 11px;border-radius:6px;font-family:'Plus Jakarta Sans',sans-serif;transition:all .15s;}
.notif-btn:hover{color:#fff;background:rgba(255,255,255,0.1);}
.bell-wrap{position:relative;display:inline-flex;align-items:center;}
.red-dot{position:absolute;top:-3px;right:-4px;width:9px;height:9px;background:#e53e3e;border-radius:50%;border:2px solid var(--blue-dk);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.7;transform:scale(1.25);}}
.notif-badge{background:#e53e3e;color:#fff;font-size:10px;font-weight:800;min-width:17px;height:17px;border-radius:99px;display:none;align-items:center;justify-content:center;padding:0 4px;}
.notif-badge.show{display:flex;}
.notif-dropdown{display:none;position:absolute;top:calc(100% + 8px);right:0;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);width:450px;max-width:calc(100vw - 40px);z-index:300;overflow:hidden;}
.notif-dropdown.open{display:block;}
.notif-head{background:var(--blue-dk);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.notif-head-title{color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;}
.notif-new-pill{background:rgba(255,255,255,0.2);color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;}
.notif-mark{background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);color:#fff;font-size:11px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;padding:4px 10px;border-radius:5px;cursor:pointer;transition:background .15s;}
.notif-mark:hover{background:rgba(255,255,255,0.3);}
.notif-caught{font-size:11px;color:rgba(255,255,255,0.55);display:flex;align-items:center;gap:5px;}
.notif-list{max-height:500px;overflow-y:auto;overflow-x:hidden;background:var(--white);}
.notif-list::-webkit-scrollbar{width:4px;}
.notif-list::-webkit-scrollbar-track{background:var(--gray-100);}
.notif-list::-webkit-scrollbar-thumb{background:var(--gray-300);border-radius:99px;}
.notif-item{display:flex;gap:14px;padding:16px 18px;border-bottom:1px solid var(--gray-100);transition:background .15s;text-decoration:none;color:inherit;cursor:pointer;width:100%;}
.notif-item:first-child{padding-top:16px;}
.notif-item:last-child{border-bottom:none;padding-bottom:16px;}
.notif-item:hover{background:var(--gray-50);}
.notif-item.unread{background:var(--blue-lt);}
.notif-item.unread:hover{background:#dceef9;}
body.dark-mode .notif-item.unread{background:#1e3a5f;}
body.dark-mode .notif-item.unread:hover{background:#2d4a6e;}
.notif-icon{display:inline-flex;align-items:flex-start;justify-content:center;width:36px;flex-shrink:0;color:var(--gray-600);}
.notif-icon svg{width:20px;height:20px;stroke:currentColor;stroke-width:1.8;fill:none;}
.notif-content{flex:1;min-width:0;}
.notif-title{font-size:14px;color:var(--gray-800);font-weight:700;margin-bottom:6px;line-height:1.4;}
.notif-desc{font-size:13px;color:var(--gray-600);line-height:1.5;margin-bottom:8px;}
.notif-date{font-size:11px;color:var(--gray-400);display:flex;align-items:center;gap:6px;margin-top:4px;}
.notif-date::before{content:"";display:inline-block;width:4px;height:4px;background:var(--gray-400);border-radius:50%;}
.notif-empty{padding:48px 24px;text-align:center;font-size:13px;color:var(--gray-400);font-style:italic;}

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
  <div class="nav-brand">CCS Monitoring System</div>
  <div class="nav-links">
    <div class="notif-wrap">
      <button class="notif-btn" onclick="toggleNotif()" id="notifBtn">
        <span class="bell-wrap">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <span class="red-dot" id="redDot" style="<?= $_np_unread_count > 0 ? '' : 'display:none;' ?>"></span>
        </span>
        Notifications
        <span class="notif-badge <?= $_np_unread_count > 0 ? 'show' : '' ?>" id="notifBadge">
          <?= $_np_unread_count > 0 ? ($_np_unread_count > 99 ? '99+' : $_np_unread_count) : '' ?>
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
            <span class="notif-new-pill" id="newNotifPill" style="<?= $_np_unread_count > 0 ? '' : 'display:none;' ?>"><?= $_np_unread_count ?> new</span>
          </span>
          <span id="notifHeadRight">
            <?php if ($_np_unread_count > 0): ?>
              <form method="POST" action="<?= $_np_page_file ?>" style="margin:0;">
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
            <?php if (empty($_np_notifications)): ?>
              <div class="notif-empty">No notifications yet.</div>
            <?php else: ?>
              <?php foreach ($_np_notifications as $n):
                $parsed = np_parseNotification($n['message'], $n['type'] ?? '');
                $ts = (int)strtotime($n['created_at']);
                $isUnread = $n['is_read'] == 0;
                $iconSvg = np_getNotifIcon($n['type'] ?? '', $n['message']);
              ?>
                <a href="notification_handler.php?id=<?= (int)$n['id'] ?>" class="notif-item <?= $isUnread ? 'unread' : 'read' ?>" data-id="<?= (int)$n['id'] ?>">
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
    <a href="Homepage.php"<?= np_active('home', $_np_page) ?>>Home</a>
    <a href="profile.php"<?= np_active('profile', $_np_page) ?>>Edit Profile</a>
    <a href="history.php"<?= np_active('history', $_np_page) ?>>History</a>
    <a href="feedback.php"<?= np_active('feedback', $_np_page) ?>>Feedback</a>
    <a href="reservation.php"<?= np_active('reservation', $_np_page) ?>>Reservation</a>
    <a href="software.php"<?= np_active('software', $_np_page) ?>>Lab Software</a>
    <button onclick="toggleDarkMode()" class="btn-dark-toggle" id="darkToggle" title="Toggle dark mode">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<script>
// Real-time notification polling
function relTime(ts) {
  if (!ts || ts <= 0) return '';
  var now = Math.floor(Date.now() / 1000);
  var diff = now - ts;
  if (diff < 0) return 'Just now';
  if (diff < 60) return 'Just now';
  if (diff < 3600) { var m = Math.floor(diff/60); return m + ' minute' + (m > 1 ? 's' : '') + ' ago'; }
  if (diff < 86400) { var h = Math.floor(diff/3600); return h + ' hour' + (h > 1 ? 's' : '') + ' ago'; }
  if (diff < 604800) { var d = Math.floor(diff/86400); return d + ' day' + (d > 1 ? 's' : '') + ' ago'; }
  var dt = new Date(ts * 1000);
  return dt.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
}

function refreshTimestamps() {
  document.querySelectorAll('.notif-date[data-ts]').forEach(function(el) {
    var ts = parseInt(el.getAttribute('data-ts'), 10);
    if (!isNaN(ts) && ts > 0) el.textContent = relTime(ts);
  });
}

var currentUnreadCount = <?= $_np_unread_count ?>;

function parseNotificationJS(text, type) {
  text = text.trim();
  text = text.replace(/^[\u{1F000}-\u{1FFFF}\u{2600}-\u{27FF}\u{FE00}-\u{FEFF}\u{1F300}-\u{1F9FF}\s]+/u, '');
  if (type === 'announcement' || text.indexOf('New announcement') === 0) {
    var parts = text.split(':');
    return { title: '📢 New Announcement', description: parts[1] ? parts[1].trim() : text };
  }
  if (type === 'reservation_approved') {
    return { title: '✅ Reservation Approved', description: text.replace('Reservation approved:', '') };
  }
  if (type === 'reservation_rejected') {
    return { title: '❌ Reservation Rejected', description: text.replace('Reservation rejected:', '') };
  }
  if (type === 'feedback' || text.indexOf('feedback') !== false) {
    return { title: '💬 New Feedback', description: text.replace(/^[💬]*\s*You received feedback from admin:\s*/i, '') };
  }
  if (type === 'logout' || text.indexOf('logged out') !== false) {
    return { title: '📤 Session Ended', description: 'You have been logged out of your session.' };
  }
  var lines = text.split('\n');
  return { title: lines[0], description: lines[1] ? lines[1].trim() : '' };
}

function getNotificationIconJS(type, message) {
  var m = (type + ' ' + message).toLowerCase();
  if (m.indexOf('announcement') !== -1) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
  if (m.indexOf('reservation') !== -1 && m.indexOf('approv') !== -1) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
  if (m.indexOf('reservation') !== -1 && m.indexOf('reject') !== -1) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="18" y1="6" x2="6" y2="18"/></svg>';
  if (m.indexOf('feedback') !== -1) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
  if (m.indexOf('logout') !== -1 || m.indexOf('session') !== -1) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
  return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

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
      var prevUnread = currentUnreadCount;
      currentUnreadCount = data.notifications.filter(function(n) { return parseInt(n.is_read) === 0; }).length;
      
      if (currentUnreadCount > 0) {
        badge.textContent = currentUnreadCount > 99 ? '99+' : currentUnreadCount;
        badge.classList.add('show');
        if (redDot) redDot.style.display = '';
        if (newPill) { newPill.style.display = ''; newPill.textContent = currentUnreadCount + ' new'; }
        headRight.innerHTML = '<form method="POST" action="<?= $_np_page_file ?>" style="margin:0;"><input type="hidden" name="mark_notif_read" value="1"/><button type="submit" class="notif-mark">Mark all read</button></form>';
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
          var parsed = parseNotificationJS(n.message, n.type);
          var iconSvg = getNotificationIconJS(n.type, n.message);
          var isUnreadClass = parseInt(n.is_read) === 0 ? 'unread' : 'read';
          var ts = Math.floor(new Date(n.created_at.replace(' ', 'T')).getTime() / 1000);
          html += '<a href="notification_handler.php?id=' + n.id + '" class="notif-item ' + isUnreadClass + '" data-id="' + n.id + '">' +
                  '<div class="notif-icon">' + iconSvg + '</div>' +
                  '<div class="notif-content">' +
                  '<div class="notif-title">' + escapeHtml(parsed.title) + '</div>' +
                  (parsed.description ? '<div class="notif-desc">' + escapeHtml(parsed.description).replace(/\n/g, '<br>') + '</div>' : '') +
                  '<div class="notif-date" data-ts="' + ts + '"></div>' +
                  '</div></a>';
        });
        container.innerHTML = html;
      } else {
        container.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
      }
      refreshTimestamps();
      
      // Show browser notification for new notifications
      if (currentUnreadCount > prevUnread && document.hidden) {
        if (Notification.permission === 'granted') {
          new Notification('New Notification', { body: 'You have ' + (currentUnreadCount - prevUnread) + ' new notification(s)', icon: '/favicon.ico' });
        }
      }
    })
    .catch(function(error) { console.log('Polling error:', error); });
}

// Dark mode
var SUN_SVG = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
var MOON_SVG = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

function applyDarkMode(on) {
  document.body.classList.toggle('dark-mode', on);
  var btn = document.getElementById('darkToggle');
  if (btn) btn.innerHTML = on ? SUN_SVG : MOON_SVG;
  localStorage.setItem('darkMode', on ? '1' : '0');
}
window.toggleDarkMode = function() {
  var on = !document.body.classList.contains('dark-mode');
  applyDarkMode(on);
};

if (localStorage.getItem('darkMode') === '1') applyDarkMode(true);

var notifOpen = false;
window.toggleNotif = function() {
  notifOpen = !notifOpen;
  document.getElementById('notifDropdown').classList.toggle('open', notifOpen);
  if (notifOpen) refreshTimestamps();
};
document.addEventListener('click', function(e) {
  var wrap = document.querySelector('.notif-wrap');
  if (wrap && !wrap.contains(e.target)) {
    notifOpen = false;
    document.getElementById('notifDropdown').classList.remove('open');
  }
});

refreshTimestamps();
setInterval(refreshTimestamps, 30000);
setInterval(pollNotifications, 10000);
pollNotifications();

// Request notification permission
if (Notification.permission === 'default') {
  Notification.requestPermission();
}
</script>
</body>
</html>