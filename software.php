<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}
require_once 'db.php';
$CURRENT_PAGE = 'software';

$student_id = (int)$_SESSION['student_id'];

// ── Ensure software table exists ─────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lab_software (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        lab_name    VARCHAR(100) NOT NULL,
        software    VARCHAR(255) NOT NULL,
        category    VARCHAR(100) DEFAULT 'General',
        is_available TINYINT(1) DEFAULT 1,
        added_by    VARCHAR(100) DEFAULT 'Admin',
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// ── Handle mark-all-read ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notif_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0")->execute([$student_id]);
    header('Location: software.php'); exit;
}

// ── Fetch notifications ──────────────────────────────────────
try {
    $notif_stmt = $pdo->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 30");
    $notif_stmt->execute([$student_id]);
    $notifications = $notif_stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}
$unread_count = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));

function parseNotification($text) {
    $text = trim($text);
    $text = preg_replace('/^[\x{1F000}-\x{1FFFF}\x{2600}-\x{27FF}\x{FE00}-\x{FEFF}\x{1F300}-\x{1F9FF}\s]+/u', '', $text);
    if (strpos($text, 'New announcement from') === 0) {
        $parts = explode(':', $text, 2);
        return ['title' => trim($parts[0]), 'description' => isset($parts[1]) ? trim($parts[1]) : ''];
    }
    if (strpos($text, 'feedback') !== false) {
        $cleanText = preg_replace('/^[💬]*\s*You received feedback from admin:\s*/i', '', $text);
        return ['title' => 'Feedback Received', 'description' => trim($cleanText)];
    }
    if (strpos($text, 'logged out') !== false || strpos($text, 'Logged out') !== false) {
        $cleanText = preg_replace('/^[📤]*\s*/', '', $text);
        return ['title' => 'Session Ended', 'description' => trim($cleanText)];
    }
    $lines = explode("\n", $text, 2);
    return ['title' => trim($lines[0]), 'description' => isset($lines[1]) ? trim($lines[1]) : ''];
}

function getNotificationIcon($message) {
    $msgLower = strtolower($message);
    if (strpos($msgLower, 'announcement') !== false) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
    } elseif (strpos($msgLower, 'feedback') !== false) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    } elseif (strpos($msgLower, 'logged out') !== false || strpos($msgLower, 'logout') !== false) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    } else {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
    }
}

// ── Fetch software grouped by lab ────────────────────────────
$software_rows = $pdo->query("SELECT * FROM lab_software WHERE is_available = 1 ORDER BY lab_name, category, software")->fetchAll();
$labs = [];
foreach ($software_rows as $row) {
    $labs[$row['lab_name']][$row['category']][] = $row['software'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Software Availability</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --blue:#1B5886;
  --blue-dk:#003A6B;
  --blue-lt:#e8f4fb;
  --blue-bd:#89CFF1;
  --gray-50:#f4f8fc;
  --gray-100:#e8f0f7;
  --gray-200:#cddaec;
  --gray-300:#b8c8dc;
  --gray-400:#8aaac8;
  --gray-500:#6b8fae;
  --gray-600:#3d607f;
  --gray-700:#2a4560;
  --gray-800:#1a2e45;
  --white:#fff;
  --radius:8px;
  --radius-lg:12px;
  --shadow:0 1px 3px rgba(0,58,107,0.08);
  --shadow-md:0 4px 20px rgba(0,58,107,0.11);
  --notif-bg:#ffffff;
  --notif-border:#e2e8f0;
  --notif-hover:#f8fafc;
  --card-bg:#ffffff;
  --card-border:#cddaec;
  --text-primary:#1a2e45;
  --text-secondary:#3d607f;
  --text-muted:#6b8fae;
  --border-light:#e8f0f7;
  --pill-bg:#f1f5f9;
  --pill-border:#cbd5e1;
  --pill-color:#475569;
  --category-color:#1B5886;
}
body.dark-mode {
  --blue:#1B5886;
  --blue-dk:#003A6B;
  --blue-lt:#1e293b;
  --blue-bd:#3b82f6;
  --gray-50:#0f172a;
  --gray-100:#1e293b;
  --gray-200:#334155;
  --gray-300:#475569;
  --gray-400:#64748b;
  --gray-500:#94a3b8;
  --gray-600:#cbd5e1;
  --gray-700:#e2e8f0;
  --gray-800:#f1f5f9;
  --white:#1e293b;
  --shadow:0 1px 3px rgba(0,0,0,0.5);
  --shadow-md:0 10px 25px -5px rgba(0,0,0,0.5);
  --notif-bg:#1e293b;
  --notif-border:#334155;
  --notif-hover:#334155;
  --card-bg:#1e293b;
  --card-border:#334155;
  --text-primary:#f1f5f9;
  --text-secondary:#cbd5e1;
  --text-muted:#94a3b8;
  --border-light:#334155;
  --pill-bg:#334155;
  --pill-border:#475569;
  --pill-color:#cbd5e1;
  --category-color:#89CFF1;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--text-primary);min-height:100vh;font-size:14px;transition:background 0.2s, color 0.2s;}

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
.notif-dropdown{display:none;position:absolute;top:calc(100% + 8px);right:0;background:var(--notif-bg);border:1px solid var(--notif-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);width:450px;max-width:calc(100vw - 40px);z-index:300;overflow:hidden;}
.notif-dropdown.open{display:block;}
.notif-head{background:var(--blue-dk);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.notif-head-title{color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;}
.notif-new-pill{background:rgba(255,255,255,0.2);color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;}
.notif-mark{background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);color:#fff;font-size:11px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;padding:4px 10px;border-radius:5px;cursor:pointer;transition:background .15s;}
.notif-mark:hover{background:rgba(255,255,255,0.3);}
.notif-caught{font-size:11px;color:rgba(255,255,255,0.55);display:flex;align-items:center;gap:5px;}
.notif-list{max-height:500px;overflow-y:auto;overflow-x:hidden;background:var(--notif-bg);}
.notif-list::-webkit-scrollbar{width:4px;}
.notif-list::-webkit-scrollbar-track{background:var(--gray-100);}
.notif-list::-webkit-scrollbar-thumb{background:var(--gray-300);border-radius:99px;}
.notif-item{display:flex;gap:14px;padding:16px 18px;border-bottom:1px solid var(--notif-border);transition:background .15s;text-decoration:none;color:inherit;cursor:pointer;width:100%;}
.notif-item:first-child{padding-top:16px;}
.notif-item:last-child{border-bottom:none;padding-bottom:16px;}
.notif-item:hover{background:var(--notif-hover);}
.notif-item.unread{background:var(--blue-lt);}
body.dark-mode .notif-item.unread{background:#1e3a5f;}
body.dark-mode .notif-item.unread:hover{background:#2d4a6e;}
.notif-icon{display:inline-flex;align-items:flex-start;justify-content:center;width:36px;flex-shrink:0;}
.notif-icon svg{width:20px;height:20px;stroke:var(--text-secondary);stroke-width:1.8;fill:none;}
.notif-content{flex:1;min-width:0;}
.notif-title{font-size:14px;color:var(--text-primary);font-weight:700;margin-bottom:6px;line-height:1.4;word-break:break-word;white-space:normal;}
.notif-desc{font-size:13px;color:var(--text-secondary);line-height:1.5;word-break:break-word;white-space:normal;margin-bottom:8px;}
.notif-date{font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:6px;margin-top:4px;}
.notif-date::before{content:"";display:inline-block;width:4px;height:4px;background:var(--text-muted);border-radius:50%;}
.notif-empty{padding:48px 24px;text-align:center;font-size:13px;color:var(--text-muted);font-style:italic;}

/* PAGE STYLES */
.page-body{max-width:1100px;margin:0 auto;padding:32px 20px 70px;}
.page-title{font-size:24px;font-weight:800;color:var(--text-primary);text-align:center;margin-bottom:6px;}
.page-sub{font-size:13px;color:var(--text-muted);text-align:center;margin-bottom:28px;}

.search-bar{
  display:flex; align-items:center; gap:10px;
  background:var(--card-bg); border:1.5px solid var(--border-light);
  border-radius:40px; padding:10px 18px;
  margin-bottom:24px; box-shadow:var(--shadow);
  transition:background .2s, border-color .2s;
}
.search-bar svg{width:18px; height:18px; stroke:var(--text-muted); fill:none; stroke-width:2; flex-shrink:0;}
.search-bar input{
  border:none; outline:none;
  font-size:14px; font-family:'Plus Jakarta Sans',sans-serif;
  width:100%; color:var(--text-primary);
  background:transparent;
}
.search-bar input::placeholder{color:var(--text-muted);}

/* Lab Tabs - Matching the "Make a Reservation" button style */
.lab-tabs{display:flex; gap:12px; flex-wrap:wrap; margin-bottom:28px;}
.lab-tab{
  padding:8px 22px; border-radius:40px;
  border:1.5px solid var(--border-light);
  background:var(--card-bg);
  font-size:13px; font-weight:600; color:var(--text-secondary);
  cursor:pointer; transition:all 0.2s ease;
}
.lab-tab:hover{
  border-color:var(--blue);
  color:var(--blue);
  background:var(--blue-lt);
}
.lab-tab.active{
  background:#1B5886;
  border-color:#1B5886;
  color:#fff;
}
body.dark-mode .lab-tab.active{
  background:#1B5886;
  border-color:#1B5886;
  color:#fff;
}

.labs-grid{display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:24px;}

.lab-card{background:var(--card-bg); border-radius:var(--radius-lg); border:1px solid var(--card-border); box-shadow:var(--shadow); overflow:hidden;}
.lab-card-head{
  background:#1B5886;
  padding:14px 18px; display:flex; align-items:center; gap:10px;
}
.lab-card-head svg{width:20px; height:20px; stroke:#fff; fill:none; stroke-width:2;}
.lab-card-head h3{color:#fff; font-size:15px; font-weight:800;}
.lab-card-body{padding:18px; background:var(--card-bg);}

.category-label{
  font-size:11px; font-weight:800; text-transform:uppercase;
  letter-spacing:0.08em; color:var(--category-color);
  margin:14px 0 8px; padding-bottom:4px;
  border-bottom:1px solid var(--border-light);
}
.category-label:first-child{margin-top:0;}

.software-list{display:flex; flex-wrap:wrap; gap:8px;}
.software-pill{
  background:var(--pill-bg); border:1px solid var(--pill-border);
  border-radius:30px; padding:5px 14px;
  font-size:12px; font-weight:500; color:var(--pill-color);
  display:inline-flex; align-items:center; gap:6px;
  transition:all .2s;
}
.software-pill svg{width:12px; height:12px; stroke:#16a34a; fill:none; stroke-width:2.5;}
body.dark-mode .software-pill svg{stroke:#34d399;}

.empty-state{text-align:center; padding:60px 20px; color:var(--text-muted);}
.empty-state svg{width:56px; height:56px; stroke:var(--text-muted); fill:none; stroke-width:1.5; margin-bottom:16px;}
.empty-state p{font-size:14px;}

@media(max-width:550px){
  .lab-tabs{gap:8px;}
  .lab-tab{padding:6px 14px; font-size:12px;}
  .labs-grid{grid-template-columns:1fr;}
  .page-body{padding:20px 16px;}
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
              <form method="POST" action="software.php" style="margin:0;">
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
    <a href="Homepage.php">Home</a>
    <a href="profile.php">Edit Profile</a>
    <a href="history.php">History</a>
    <a href="feedback.php">Feedback</a>
    <a href="reservation.php">Reservation</a>
    <a href="software.php" class="active">Lab Software</a>
    <button onclick="toggleDarkMode()" class="btn-dark-toggle" id="darkToggle" title="Toggle dark mode"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="page-body">
  <div class="page-title">Software Availability</div>
  <div class="page-sub">Browse available software across all computer labs</div>

  <div class="search-bar">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" id="softwareSearch" placeholder="Search software or lab…" oninput="filterSoftware(this.value)"/>
  </div>

  <div class="lab-tabs" id="labTabs">
    <div class="lab-tab active" onclick="filterLab('all',this)">All Labs</div>
    <?php foreach (array_keys($labs) as $labName): ?>
    <div class="lab-tab" onclick="filterLab(<?= json_encode($labName) ?>,this)"><?= htmlspecialchars($labName) ?></div>
    <?php endforeach; ?>
  </div>

  <?php if ($labs): ?>
  <div class="labs-grid" id="labsGrid">
    <?php foreach ($labs as $labName => $categories): ?>
    <div class="lab-card" data-lab="<?= htmlspecialchars($labName) ?>">
      <div class="lab-card-head">
        <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        <h3><?= htmlspecialchars($labName) ?></h3>
      </div>
      <div class="lab-card-body">
        <?php foreach ($categories as $cat => $softwares): ?>
        <div class="category-label"><?= htmlspecialchars($cat) ?></div>
        <div class="software-list">
          <?php foreach ($softwares as $sw): ?>
          <span class="software-pill">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <?= htmlspecialchars($sw) ?>
          </span>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
    <p>No software information available yet.<br>The admin will update this soon.</p>
  </div>
  <?php endif; ?>
</div>

<script>
var notifOpen = false;

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

var currentUnreadCount = <?= $unread_count ?>;

function parseNotificationJS(text) {
  text = text.trim();
  text = text.replace(/^[\u{1F000}-\u{1FFFF}\u{2600}-\u{27FF}\u{FE00}-\u{FEFF}\u{1F300}-\u{1F9FF}\s]+/u, '');
  if (text.indexOf('New announcement from') === 0) {
    var parts = text.split(':'); return { title: parts[0], description: parts[1] ? parts[1].trim() : '' };
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
  if (msgLower.indexOf('announcement') !== false) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
  if (msgLower.indexOf('feedback') !== false) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
  if (msgLower.indexOf('logged out') !== false || msgLower.indexOf('logout') !== false) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
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
      currentUnreadCount = data.notifications.filter(function(n) { return parseInt(n.is_read) === 0; }).length;
      if (currentUnreadCount > 0) {
        badge.textContent = currentUnreadCount > 99 ? '99+' : currentUnreadCount;
        badge.classList.add('show');
        if (redDot) redDot.style.display = '';
        if (newPill) { newPill.style.display = ''; newPill.textContent = currentUnreadCount + ' new'; }
        headRight.innerHTML = '<form method="POST" action="software.php" style="margin:0;"><input type="hidden" name="mark_notif_read" value="1"/><button type="submit" class="notif-mark">Mark all read</button></form>';
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
                  '</div></a>';
        });
        container.innerHTML = html;
      } else {
        container.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
      }
      refreshTimestamps();
    })
    .catch(function(error) { console.log('Polling error:', error); });
}

refreshTimestamps();
setInterval(refreshTimestamps, 30000);
setInterval(pollNotifications, 15000);
pollNotifications();

function toggleNotif() {
  notifOpen = !notifOpen;
  document.getElementById('notifDropdown').classList.toggle('open', notifOpen);
  if (notifOpen) refreshTimestamps();
}
document.addEventListener('click', function(e) {
  var wrap = document.querySelector('.notif-wrap');
  if (wrap && !wrap.contains(e.target)) {
    notifOpen = false;
    document.getElementById('notifDropdown').classList.remove('open');
  }
});

function filterLab(lab, el) {
  document.querySelectorAll('.lab-tab').forEach(function(t){ t.classList.remove('active'); });
  el.classList.add('active');
  document.querySelectorAll('.lab-card').forEach(function(c){
    c.style.display = (lab === 'all' || c.dataset.lab === lab) ? '' : 'none';
  });
}
function filterSoftware(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.lab-card').forEach(function(card){
    card.style.display = card.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
  if (q) {
    document.querySelectorAll('.lab-tab').forEach(function(t){ t.classList.remove('active'); });
    var first = document.querySelector('.lab-tab');
    if (first) first.classList.add('active');
  }
}

var SUN_SVG = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
var MOON_SVG = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

function applyDarkMode(on) {
  document.body.classList.toggle('dark-mode', on);
  var btn = document.getElementById('darkToggle');
  if (btn) btn.innerHTML = on ? SUN_SVG : MOON_SVG;
  if (notifOpen) document.getElementById('notifDropdown').classList.add('open');
}
function toggleDarkMode() {
  var on = !document.body.classList.contains('dark-mode');
  localStorage.setItem('darkMode', on ? '1' : '0');
  applyDarkMode(on);
}
if (localStorage.getItem('darkMode') === '1') applyDarkMode(true);
</script>
</body>
</html>