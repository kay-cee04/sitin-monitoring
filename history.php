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
    header('Location: history.php'); exit;
}

// Fetch sit-in history
$history = $pdo->prepare("SELECT * FROM sit_in_history WHERE student_id = ? ORDER BY date DESC, created_at DESC");
$history->execute([$student_id]);
$rows  = $history->fetchAll();
$total = count($rows);

// Fetch initial notifications
try {
    $notif_stmt = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 30");
    $notif_stmt->execute([$student_id]);
    $notifications = $notif_stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}
$unread_count = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Sit-in History</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;
  --gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-300:#b8c8dc;
  --gray-400:#8aaac8;--gray-500:#6b8fae;--gray-600:#3d607f;
  --gray-700:#2a4560;--gray-800:#1a2e45;--white:#fff;
  --radius:8px;--radius-lg:12px;
  --shadow:0 1px 3px rgba(0,58,107,0.08);--shadow-md:0 4px 20px rgba(0,58,107,0.11);
  --green:#16a34a;--green-lt:#dcfce7;--green-bd:#86efac;
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
.page-body{max-width:1100px;margin:0 auto;padding:32px 20px 60px;}
.page-header{margin-bottom:20px;}
.page-title{font-size:22px;font-weight:800;color:var(--blue-dk);letter-spacing:-0.02em;text-align:center;}
.page-sub{font-size:13px;color:var(--gray-400);margin-top:4px;}
.toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:10px;}
.entries-wrap{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-600);}
.entries-wrap select{padding:6px 10px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;outline:none;cursor:pointer;}
.entries-wrap select:focus{border-color:var(--blue);}
.search-wrap{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-600);}
.search-wrap input{padding:7px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;outline:none;width:220px;transition:border-color .15s,box-shadow .15s;}
.search-wrap input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.08);}
.card{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow);overflow:hidden;}
table{width:100%;border-collapse:collapse;}
thead tr{background:var(--blue);}
thead th{color:#fff;font-size:11.5px;font-weight:700;padding:12px 14px;text-align:left;white-space:nowrap;letter-spacing:0.04em;text-transform:uppercase;}
tbody tr{border-bottom:1px solid var(--gray-100);transition:background .12s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:var(--blue-lt);}
tbody tr.ongoing-row{background:#f0fdf4;}
tbody tr.ongoing-row:hover{background:#dcfce7;}
tbody td{padding:12px 14px;font-size:13px;color:var(--gray-600);}
.td-num{font-weight:700;color:var(--gray-400);font-size:12px;}
.td-id{font-weight:700;color:var(--blue-dk);}
.td-name{font-weight:600;color:var(--gray-800);}
.td-time{font-size:13px;color:var(--gray-600);font-weight:500;}
.badge-ongoing{display:inline-flex;align-items:center;gap:5px;background:var(--green-lt);color:var(--green);border:1px solid var(--green-bd);font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;white-space:nowrap;}
.badge-dot{width:6px;height:6px;background:var(--green);border-radius:50%;animation:pulse 1.5s infinite;}
.badge-completed{display:inline-block;background:#dcfce7;color:#16a34a;border:1px solid #86efac;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;white-space:nowrap;}
.no-data{text-align:center;padding:48px 16px;color:var(--gray-400);font-size:13px;font-style:italic;}
.table-footer{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid var(--gray-100);font-size:12.5px;color:var(--gray-400);flex-wrap:wrap;gap:8px;}
.pagination{display:flex;align-items:center;gap:4px;}
.page-btn{min-width:30px;height:30px;padding:0 8px;border-radius:6px;border:1.5px solid var(--gray-200);background:var(--white);font-size:12px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--gray-600);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;user-select:none;}
.page-btn:hover:not(.disabled){border-color:var(--blue);color:var(--blue);}
.page-btn.active{background:var(--blue);border-color:var(--blue);color:#fff;font-weight:700;}
.page-btn.disabled{opacity:.35;pointer-events:none;}

@media(max-width:700px){
  table{font-size:12px;}
  thead th,tbody td{padding:9px 10px;}
  nav{padding:0 16px;}
  .search-wrap input{width:150px;}
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
              <form method="POST" action="history.php" style="margin:0;">
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
    <a href="profile.php">Edit Profile</a>
    <a href="history.php" class="active">History</a>
    <a href="feedback.php">Feedback</a>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="page-body">
  <div class="page-header">
    <div class="page-title">Sit-in History</div>
    <div class="page-sub">Showing all <?= $total ?> sit-in record<?= $total !== 1 ? 's' : '' ?> for your account</div>
  </div>

  <div class="toolbar">
    <div class="entries-wrap">
      Show
      <select id="entriesSelect" onchange="changeEntries(this.value)">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
      entries per page
    </div>
    <div class="search-wrap">
      Search:
      <input type="text" id="searchInput" placeholder="Purpose, lab, date..." oninput="applySearch(this.value)"/>
    </div>
  </div>

  <div class="card">
    <table id="historyTable">
      <thead>
        <tr>
          <th>#</th>
          <th>ID Number</th>
          <th>Full Name</th>
          <th>Purpose</th>
          <th>Laboratory</th>
          <th>Login Time</th>
          <th>Logout Time</th>
          <th>Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <?php if ($rows): ?>
          <?php foreach ($rows as $i => $r):
            $isOngoing = empty($r['logout_time']);
          ?>
          <tr data-index="<?= $i ?>" class="<?= $isOngoing ? 'ongoing-row' : '' ?>">
            <td class="td-num"><?= $i + 1 ?></td>
            <td class="td-id"><?= htmlspecialchars($r['id_number']) ?></td>
            <td class="td-name"><?= htmlspecialchars($r['fullname']) ?></td>
            <td><?= htmlspecialchars($r['sit_purpose']) ?></td>
            <td><?= htmlspecialchars($r['laboratory']) ?></td>
            <td class="td-time">
              <?php if (!empty($r['login_time'])): ?>
                <?= date('h:i A', strtotime($r['login_time'])) ?>
              <?php else: ?>
                &mdash;
              <?php endif; ?>
            </td>
            <td class="td-time">
              <?php if ($isOngoing): ?>
                &mdash;
              <?php else: ?>
                <?= date('h:i A', strtotime($r['logout_time'])) ?>
              <?php endif; ?>
            </td>
            <td><?= !empty($r['date']) ? date('M d, Y', strtotime($r['date'])) : '&mdash;' ?></td>
            <td>
              <?php if ($isOngoing): ?>
                <span class="badge-ongoing"><span class="badge-dot"></span>Ongoing</span>
              <?php else: ?>
                <span class="badge-completed">Completed</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="9" class="no-data">No sit-in history found for your account.<?= htmlspecialchars($r['fullname']) ?></td>
        <?php endif; ?>
      </tbody>
    </table>
    <div class="table-footer">
      <span id="showingLabel"></span>
      <div class="pagination" id="pagination"></div>
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
        headRight.innerHTML = '<form method="POST" action="history.php" style="margin:0;"><input type="hidden" name="mark_notif_read" value="1"/><button type="submit" class="notif-mark">Mark all read</button></form>';
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

// TABLE PAGINATION & SEARCH
var allRows = Array.from(document.querySelectorAll('#tableBody tr[data-index]'));
var filtered = allRows.slice();
var perPage = 10;
var currentPage = 1;
if (allRows.length > 0) render();

function applySearch(q) {
  q = q.toLowerCase().trim();
  filtered = q ? allRows.filter(function(r){ return r.textContent.toLowerCase().includes(q); }) : allRows.slice();
  currentPage = 1; render();
}
function changeEntries(val) { perPage = parseInt(val); currentPage = 1; render(); }
function render() {
  var total = filtered.length;
  var totalPages = Math.max(1, Math.ceil(total / perPage));
  if (currentPage > totalPages) currentPage = totalPages;
  var start = (currentPage - 1) * perPage, end = start + perPage;
  allRows.forEach(function(r){ r.style.display = 'none'; });
  filtered.forEach(function(r, i) {
    r.style.display = (i >= start && i < end) ? '' : 'none';
    r.querySelector('.td-num').textContent = i + 1;
  });
  var noRow = document.getElementById('searchEmptyRow');
  if (total === 0) {
    if (!noRow) {
      noRow = document.createElement('tr'); noRow.id = 'searchEmptyRow';
      noRow.innerHTML = '<td colspan="9" class="no-data">No records match your search.</td>';
      document.getElementById('tableBody').appendChild(noRow);
    }
    noRow.style.display = '';
  } else { if (noRow) noRow.style.display = 'none'; }
  var from = total === 0 ? 0 : start + 1, to = Math.min(end, total);
  document.getElementById('showingLabel').innerHTML = 
    'Showing ' + from + ' to ' + to + ' of ' + total + ' entr' + (total === 1 ? 'y' : 'ies');
  buildPagination(totalPages);
}
function buildPagination(totalPages) {
  var pg = document.getElementById('pagination'); pg.innerHTML = '';
  if (totalPages <= 1) return;
  function btn(label, page, extra) {
    var el = document.createElement('div'); el.className = 'page-btn ' + (extra || '');
    el.innerHTML = label;
    if (!extra || (!extra.includes('disabled') && !extra.includes('active')))
      el.onclick = function(){ currentPage = page; render(); };
    return el;
  }
  pg.appendChild(btn('&laquo;', 1, currentPage===1?'disabled':''));
  pg.appendChild(btn('&lsaquo;', currentPage-1, currentPage===1?'disabled':''));
  var startP = Math.max(1, currentPage-2), endP = Math.min(totalPages, startP+4);
  if (endP-startP < 4) startP = Math.max(1, endP-4);
  for (var p = startP; p <= endP; p++) pg.appendChild(btn(p, p, p===currentPage?'active':''));
  pg.appendChild(btn('&rsaquo;', currentPage+1, currentPage===totalPages?'disabled':''));
  pg.appendChild(btn('&raquo;', totalPages, currentPage===totalPages?'disabled':''));
}
</script>
</body>
</html>