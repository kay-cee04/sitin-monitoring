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

// Fetch notifications
try {
    $notif_stmt = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 30");
    $notif_stmt->execute([$student_id]);
    $notifications = $notif_stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}
$unread_count = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));

function stripLeadingEmoji(string $s): string {
    return trim(preg_replace('/^[\x{1F000}-\x{1FFFF}\x{2600}-\x{27FF}\x{FE00}-\x{FEFF}\x{1F300}-\x{1F9FF}\s]+/u', '', $s));
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
  --gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-400:#8aaac8;
  --gray-600:#3d607f;--gray-800:#1a2e45;--white:#fff;
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

/* ── BELL ── */
.notif-wrap{position:relative;}
.notif-btn{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);background:none;border:none;cursor:pointer;padding:6px 11px;border-radius:6px;font-family:'Plus Jakarta Sans',sans-serif;transition:all .15s;}
.notif-btn:hover{color:#fff;background:rgba(255,255,255,0.1);}
.bell-wrap{position:relative;display:inline-flex;align-items:center;}
.red-dot{position:absolute;top:-3px;right:-4px;width:9px;height:9px;background:#e53e3e;border-radius:50%;border:2px solid var(--blue-dk);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.7;transform:scale(1.25);}}
.notif-badge{background:#e53e3e;color:#fff;font-size:10px;font-weight:800;min-width:17px;height:17px;border-radius:99px;display:none;align-items:center;justify-content:center;padding:0 4px;}
.notif-badge.show{display:flex;}
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
.notif-item{display:flex;gap:12px;padding:12px 16px;border-bottom:1px solid var(--gray-100);transition:background .12s;align-items:flex-start;}
.notif-item:last-child{border-bottom:none;}
.notif-item:hover{background:var(--gray-50);}
.notif-dot{width:8px;height:8px;background:var(--blue);border-radius:50%;flex-shrink:0;margin-top:5px;}
.notif-item.read .notif-dot{background:transparent;border:2px solid var(--gray-200);}
.notif-content{flex:1;min-width:0;}
.notif-title{font-size:13px;color:var(--gray-800);font-weight:600;line-height:1.4;margin-bottom:2px;}
.notif-msg{font-size:12px;color:var(--gray-400);line-height:1.4;word-break:break-word;}
.announcement-highlight{color:#000;font-weight:700;}
.notif-time-right{font-size:12px;color:var(--gray-400);text-align:right;white-space:nowrap;font-weight:500;flex-shrink:0;}
.notif-empty{padding:32px 16px;text-align:center;font-size:13px;color:var(--gray-400);font-style:italic;}

/* ── PAGE ── */
.page-body{max-width:1100px;margin:0 auto;padding:32px 20px 60px;}
.page-header{margin-bottom:20px;}
.page-title{font-size:22px;font-weight:800;color:var(--blue-dk);letter-spacing:-0.02em;}
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
@media(max-width:700px){table{font-size:12px;}thead th,tbody td{padding:9px 10px;}nav{padding:0 16px;}.search-wrap input{width:150px;}}
</style>
</head>
<body>

<nav>
  <div class="nav-brand">CCS Sit-in Monitoring</div>
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
            Notifications
            <?php if ($unread_count > 0): ?>
              <span class="notif-new-pill"><?= $unread_count ?> new</span>
            <?php endif; ?>
          </span>
          <span id="notifHeadRight">
            <?php if ($unread_count > 0): ?>
              <form method="POST" action="history.php" style="margin:0;">
                <input type="hidden" name="mark_notif_read" value="1"/>
                <button type="submit" class="notif-mark">Mark all read</button>
              </form>
            <?php else: ?>
              <span class="notif-caught">All caught up ✓</span>
            <?php endif; ?>
          </span>
        </div>
        <div class="notif-list" id="notifList">
          <?php if (empty($notifications)): ?>
            <div class="notif-empty">No notifications yet.</div>
          <?php else: ?>
            <?php foreach ($notifications as $n):
              $ts  = (int)strtotime($n['created_at']);
              $msg = stripLeadingEmoji($n['message']);
            ?>
              <div class="notif-item <?= $n['is_read'] == 0 ? 'unread' : 'read' ?>" data-id="<?= (int)$n['id'] ?>">
                <div class="notif-content">
                  <div class="notif-msg"><?= htmlspecialchars($msg) ?></div>
                  <div class="notif-time" data-ts="<?= $ts ?>"></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <a href="Homepage.php">Home</a>
    <a href="profile.php">Edit Profile</a>
    <a href="history.php" class="active">History</a>
    <a href="reservation.php">Reservation</a>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="page-body">
  <div class="page-header">
    <div class="page-title">&#128203; Sit-in History</div>
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
            <td class="td-time"><?= !empty($r['login_time'])  ? date('M d, Y - h:i A', strtotime($r['login_time']))  : '&mdash;' ?></td>
            <td class="td-time">
              <?php if ($isOngoing): ?>
                <?= '&mdash;' ?>
              <?php else: ?>
                <?= date('M d, Y - h:i A', strtotime($r['logout_time'])) ?>
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
          <tr><td colspan="9" class="no-data">No sit-in history found for your account.</td></tr>
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
// ═══════════════════════════════════════════════════════════════════════════
// NOTIFICATION & HISTORY SYSTEM
// ═══════════════════════════════════════════════════════════════════════════
// 
// NOTIFICATIONS:
//   • Timestamps use data-ts (Unix integer) - JavaScript converts to relative times
//   • relTime() displays: "Just now", "5m ago", "2h ago", "3d ago", or "Jan 15, 2026"
//   • refreshTimestamps() updates every 60 seconds so times advance as you stay on page
//   • pollNotifications() runs every 30 seconds to fetch new notifications from server
//   • MySQL created_at ("2026-04-11 14:23:00") converted: .replace(' ','T') for parsing
//   • Emoji stripped from messages automatically (no icons shown)
//   • Badge shows unread count, red dot pulses when unread notifications exist
//   • "Mark all read" button redirects back to history.php with all notifications marked read
//
// HISTORY TABLE:
//   • Shows all sit-in records sorted by date DESC, created_at DESC
//   • Fields: #, ID Number, Full Name, Purpose, Laboratory, Login Time, Logout Time, Date
//   • Login Time: Displays as "5:25 PM" (12-hour format with AM/PM)
//   • Logout Time: Displays as "6:30 PM" or "Ongoing" badge if session still active
//   • Date: Shows formatted date like "Apr 11, 2026"
//   • Green-highlighted rows show active (ongoing) sessions
//   • Pageable and searchable - filter by purpose, lab, date, name, etc.
//
// ═══════════════════════════════════════════════════════════════════════════

// ── RELATIVE TIME ─────────────────────────────────────────────
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
  document.querySelectorAll('.notif-item').forEach(function(el) {
    var timeEl = el.querySelector('.notif-time-right');
    if (timeEl && timeEl.textContent) {
      // Already formatted, no need to update
    }
  });
}
// Initial refresh and then every minute
refreshTimestamps();
setInterval(refreshTimestamps, 60000);

// Initial poll on page load
window.addEventListener('load', function() {
  pollNotifications();
});

// ── STRIP LEADING EMOJI ───────────────────────────────────────
function stripEmoji(str) {
  return str.replace(/^[\u{1F000}-\u{1FFFF}\u{2600}-\u{27FF}\u{FE00}-\u{FEFF}\u{1F300}-\u{1F9FF}\s]+/gu, '').trim();
}

// ── NOTIFICATION POLLING ──────────────────────────────────────
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
function renderNotifItem(n) {
  var notif = parseNotification(n.message);
  var relTime = formatRelTime(n.created_at);
  var title = highlightAnnouncementTitle(escHtml(notif.title));
  var desc = escHtml(notif.desc);
  var cls = parseInt(n.is_read) === 0 ? 'notif-item unread' : 'notif-item read';
  return '<div class="' + cls + '" data-id="' + n.id + '">'
       + '<div class="notif-dot"></div>'
       + '<div class="notif-content">'
       + '<div class="notif-title">' + title + '</div>'
       + (desc ? '<div class="notif-msg">' + desc + '</div>' : '')
       + '</div>'
       + '<div class="notif-time-right">' + relTime + '</div>'
       + '</div>';
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
      refreshTimestamps();
      var unread = data.notifications.filter(function(n){ return parseInt(n.is_read) === 0; }).length;
      if (unread > 0) {
        badge.textContent = unread > 99 ? '99+' : String(unread);
        badge.classList.add('show');
        if (!document.getElementById('redDot')) {
          var dot = document.createElement('span'); dot.className='red-dot'; dot.id='redDot';
          document.querySelector('.bell-wrap').appendChild(dot);
        }
        headRight.innerHTML = '<form method="POST" action="history.php" style="margin:0;"><input type="hidden" name="mark_notif_read" value="1"/><button type="submit" class="notif-mark">Mark all read</button></form>';
      } else {
        badge.textContent = ''; badge.classList.remove('show');
        var dot = document.getElementById('redDot'); if (dot) dot.remove();
        headRight.innerHTML = '<span class="notif-caught">All caught up ✓</span>';
      }
    }).catch(function(){});
}
// Poll every 30 seconds for new notifications
setInterval(pollNotifications, 30000);

// ── BELL TOGGLE ───────────────────────────────────────────────
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

// ── TABLE PAGINATION & SEARCH ─────────────────────────────────
var allRows    = Array.from(document.querySelectorAll('#tableBody tr[data-index]'));
var filtered   = allRows.slice();
var perPage    = 10;
var currentPage = 1;
if (allRows.length > 0) render();

function applySearch(q) {
  q = q.toLowerCase().trim();
  filtered = q ? allRows.filter(function(r){ return r.textContent.toLowerCase().includes(q); }) : allRows.slice();
  currentPage = 1; render();
}
function changeEntries(val) { perPage = parseInt(val); currentPage = 1; render(); }
function render() {
  var total      = filtered.length;
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
  document.getElementById('showingLabel').textContent =
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