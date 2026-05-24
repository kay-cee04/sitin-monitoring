<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}
require_once 'db.php';
$CURRENT_PAGE = 'history';

$student_id = (int)$_SESSION['student_id'];

// Handle mark-all-read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notif_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0")
        ->execute([$student_id]);
    header('Location: history.php'); exit;
}

// ── Handle student edit of sit-in record ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_edit_sitin'])) {
    $record_id = (int)$_POST['record_id'];
    $purpose   = trim($_POST['sit_purpose'] ?? '');
    $lab       = trim($_POST['laboratory']  ?? '');

    // Security: only allow editing own completed records
    $check = $pdo->prepare("SELECT id FROM sit_in_history WHERE id = ? AND student_id = ? AND logout_time IS NOT NULL");
    $check->execute([$record_id, $student_id]);
    if ($check->fetch()) {
        $pdo->prepare("UPDATE sit_in_history SET sit_purpose = ?, laboratory = ? WHERE id = ? AND student_id = ?")
            ->execute([$purpose, $lab, $record_id, $student_id]);
    }
    header('Location: history.php?edited=1'); exit;
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
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
    } elseif (strpos($msgLower, 'feedback') !== false) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    } elseif (strpos($msgLower, 'logged out') !== false || strpos($msgLower, 'logout') !== false) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    } else {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
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
  --ongoing-bg:#e8f4fb;
  --ongoing-border:#89CFF1;
  --ongoing-text:#1B5886;
  --completed-bg:#f1f5f9;
  --completed-border:#cbd5e1;
  --completed-text:#64748b;
  --notif-bg:#ffffff;
  --notif-border:#e2e8f0;
  --notif-hover:#f8fafc;
  --card-bg:#ffffff;
  --card-border:#cddaec;
  --text-primary:#1a2e45;
  --text-secondary:#3d607f;
  --text-muted:#6b8fae;
  --border-light:#e8f0f7;
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
  --ongoing-bg:#1e3a5f;
  --ongoing-border:#3b82f6;
  --ongoing-text:#93c5fd;
  --completed-bg:#334155;
  --completed-border:#475569;
  --completed-text:#94a3b8;
  --notif-bg:#1e293b;
  --notif-border:#334155;
  --notif-hover:#334155;
  --card-bg:#1e1e2e;
  --card-border:#334155;
  --text-primary:#f1f5f9;
  --text-secondary:#cbd5e1;
  --text-muted:#94a3b8;
  --border-light:#334155;
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
.page-body{max-width:1300px;margin:0 auto;padding:32px 24px 60px;}
.page-header{text-align:center;margin-bottom:28px;}
.page-title{font-size:26px;font-weight:800;color:var(--text-primary);letter-spacing:-0.02em;}
.page-sub{font-size:14px;color:var(--text-muted);margin-top:6px;}

/* Simple toolbar - no boxes */
.toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:16px;}
.entries-wrap{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text-secondary);}
.entries-wrap select{padding:6px 10px;border:1px solid var(--border-light);border-radius:6px;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;outline:none;cursor:pointer;background:var(--card-bg);color:var(--text-primary);}
.entries-wrap select:focus{border-color:var(--blue);}
.search-wrap{display:flex;align-items:center;gap:10px;}
.search-wrap input{padding:8px 10px;border:1px solid var(--border-light);border-radius:20px;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;outline:none;width:240px;background:var(--card-bg);color:var(--text-primary);transition:all 0.2s;}
.search-wrap input:focus{border-color:var(--blue);box-shadow:0 0 0 2px rgba(27,88,134,0.1);}
.search-wrap input::placeholder{color:var(--text-muted);}

/* Table Card */
.card{background:var(--card-bg);border-radius:var(--radius-lg);border:1px solid var(--card-border);box-shadow:var(--shadow);overflow:hidden;}
table{width:100%;border-collapse:collapse;}
thead tr{background:var(--blue);}
thead th{color:#fff;font-size:12px;font-weight:700;padding:14px 16px;text-align:left;white-space:nowrap;letter-spacing:0.03em;text-transform:uppercase;}
tbody tr{border-bottom:1px solid var(--border-light);transition:background 0.15s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:var(--gray-100);}
tbody tr.ongoing-row{background:var(--ongoing-bg);}
tbody tr.ongoing-row:hover{background:var(--ongoing-bg);}
tbody td{padding:14px 16px;font-size:13px;color:var(--text-secondary);}
.td-num{font-weight:600;color:var(--text-muted);width:50px;}
.td-id{font-weight:700;color:#1B5886;font-family:monospace;}
body.dark-mode .td-id{color:#60a5fa;}
.td-name{font-weight:600;color:var(--text-primary);}
.badge-ongoing{display:inline-flex;align-items:center;gap:6px;background:var(--ongoing-bg);color:var(--ongoing-text);border:1px solid var(--ongoing-border);font-size:11px;font-weight:700;padding:4px 12px;border-radius:30px;white-space:nowrap;}
.badge-dot{width:7px;height:7px;background:var(--ongoing-text);border-radius:50%;animation:pulse 1.5s infinite;}
.badge-completed{display:inline-block;background:var(--completed-bg);color:var(--completed-text);border:1px solid var(--completed-border);font-size:11px;font-weight:700;padding:4px 12px;border-radius:30px;white-space:nowrap;}
.no-data{text-align:center;padding:60px 20px;color:var(--text-muted);font-size:13px;font-style:italic;}
.table-footer{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--border-light);font-size:13px;color:var(--text-muted);flex-wrap:wrap;gap:10px;}
.pagination{display:flex;align-items:center;gap:5px;}
.page-btn{min-width:32px;height:32px;padding:0 10px;border-radius:8px;border:1px solid var(--border-light);background:var(--card-bg);font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text-secondary);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s;}
.page-btn:hover:not(.disabled){border-color:var(--blue);color:var(--blue);background:var(--blue-lt);}
.page-btn.active{background:var(--blue);border-color:var(--blue);color:#fff;}
.page-btn.disabled{opacity:0.4;cursor:not-allowed;}

@media(max-width:700px){
  table{font-size:12px;}
  thead th,tbody td{padding:10px 12px;}
  nav{padding:0 16px;}
  .search-wrap input{width:180px;}
  .page-body{padding:20px 16px;}
  .toolbar{flex-direction:column;align-items:stretch;}
  .search-wrap input{width:100%;}
}
@media(max-width:550px){
  .notif-dropdown{width:calc(100vw - 30px);right:-10px;}
  .notif-item{gap:10px;padding:12px 14px;}
}

/* ── Edit button ── */
.btn-edit-sitin{background:var(--blue-lt);color:var(--blue-dk);border:1px solid var(--blue-bd);
  border-radius:5px;padding:4px 10px;font-size:11.5px;font-weight:600;cursor:pointer;
  font-family:'Plus Jakarta Sans',sans-serif;transition:all .15s;white-space:nowrap;}
.btn-edit-sitin:hover{background:var(--blue-dk);color:#fff;}

/* ── Edit Modal ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:500;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{background:var(--card-bg);border-radius:var(--radius-lg);box-shadow:0 8px 40px rgba(0,58,107,0.2);
  width:100%;max-width:440px;overflow:hidden;margin:20px;}
.modal-head{background:var(--blue-dk);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-head h3{color:#fff;font-size:14px;font-weight:700;}
.modal-close{background:none;border:none;color:rgba(255,255,255,0.75);font-size:20px;cursor:pointer;line-height:1;padding:0;}
.modal-body{padding:20px 18px;display:flex;flex-direction:column;gap:14px;}
.modal-field label{display:block;font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em;}
.modal-field input,.modal-field select{width:100%;padding:9px 12px;border:1px solid var(--border-light);border-radius:6px;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;background:var(--card-bg);color:var(--text-primary);outline:none;transition:border .15s;}
.modal-field input:focus,.modal-field select:focus{border-color:var(--blue);}
.modal-footer{padding:14px 18px;border-top:1px solid var(--border-light);display:flex;justify-content:flex-end;gap:10px;}
.btn-save{background:var(--blue-dk);color:#fff;border:none;border-radius:6px;padding:9px 22px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;}
.btn-save:hover{background:var(--blue);}
.btn-cancel{background:var(--gray-100);color:var(--text-secondary);border:1px solid var(--border-light);border-radius:6px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;}
.btn-cancel:hover{border-color:var(--gray-300);}
.flash-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:18px;}
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
    <a href="history.php" class="active">History</a>
    <a href="feedback.php">Feedback</a>
    <a href="reservation.php">Reservation</a>
    <a href="software.php">Lab Software</a>
    <button onclick="toggleDarkMode()" class="btn-dark-toggle" id="darkToggle" title="Toggle dark mode"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="page-body">
  <div class="page-header">
    <div class="page-title">Sit-in History</div>
    <div class="page-sub">Showing all <?= $total ?> sit-in record<?= $total !== 1 ? 's' : '' ?></div>
  </div>

  <?php if (isset($_GET['edited'])): ?>
    <div class="flash-success">✅ Record updated successfully.</div>
  <?php endif; ?>

  <!-- Simple toolbar - no boxes/cards -->
  <div class="toolbar">
    <div class="entries-wrap">
      Show
      <select id="entriesSelect" onchange="changeEntries(this.value)">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100" selected>100</option>
      </select>
      entries per page
    </div>
    <div class="search-wrap">
      <input type="text" id="searchInput" placeholder="Search by purpose, lab, or date..." oninput="applySearch(this.value)"/>
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
          <th>Duration</th>
          <th>Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <?php if ($rows): ?>
          <?php foreach ($rows as $i => $r):
            $isOngoing = empty($r['logout_time']);

            // ── Duration calculation ──────────────────────────────
            $duration = '—';
            if (!$isOngoing && !empty($r['login_time']) && !empty($r['logout_time'])) {
                // login_time may be stored as time-only or full datetime
                $loginStr  = (strpos($r['login_time'],  ' ') !== false)
                             ? $r['login_time']
                             : $r['date'] . ' ' . $r['login_time'];
                $logoutStr = (strpos($r['logout_time'], ' ') !== false)
                             ? $r['logout_time']
                             : $r['date'] . ' ' . $r['logout_time'];

                $tIn  = strtotime($loginStr);
                $tOut = strtotime($logoutStr);

                if ($tIn && $tOut && $tOut > $tIn) {
                    $diff = $tOut - $tIn;          // seconds
                    $h    = (int)($diff / 3600);
                    $m    = (int)(($diff % 3600) / 60);
                    $s    = (int)($diff % 60);
                    if ($h > 0)      $duration = $h . 'h ' . $m . 'm';
                    elseif ($m > 0)  $duration = $m . 'm ' . $s . 's';
                    else             $duration = $s . 's';
                }
            } elseif ($isOngoing) {
                $duration = '<span style="color:#1B5886;font-weight:600;">Ongoing</span>';
            }
          ?>
          <tr data-index="<?= $i ?>" class="<?= $isOngoing ? 'ongoing-row' : '' ?>">
            <td class="td-num"><?= $i + 1 ?></td>
            <td class="td-id"><?= htmlspecialchars($r['id_number']) ?></td>
            <td class="td-name"><?= htmlspecialchars($r['fullname']) ?></td>
            <td><?= htmlspecialchars($r['sit_purpose'] ?: '—') ?></td>
            <td><?= htmlspecialchars($r['laboratory'] ?: '—') ?></td>
            <td><?= !empty($r['login_time']) ? date('h:i A', strtotime($r['login_time'])) : '—' ?></td>
            <td><?= !$isOngoing && !empty($r['logout_time']) ? date('h:i A', strtotime($r['logout_time'])) : '—' ?></td>
            <td><?= $duration ?></td>
            <td><?= !empty($r['date']) ? date('M d, Y', strtotime($r['date'])) : '—' ?></td>
            <td>
              <?php if ($isOngoing): ?>
                <span class="badge-ongoing"><span class="badge-dot"></span>Ongoing</span>
              <?php else: ?>
                <span class="badge-completed">Completed</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!$isOngoing): ?>
                <button class="btn-edit-sitin"
                  onclick="openEditSitin(<?= $r['id'] ?>,'<?= addslashes(htmlspecialchars($r['sit_purpose'])) ?>','<?= addslashes(htmlspecialchars($r['laboratory'])) ?>')"
                  title="Edit record">
                  Edit
                </button>
              <?php else: ?>
                <span style="font-size:11px;color:var(--text-muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="11" class="no-data">No sit-in history found for your account.</td></tr>
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

// TABLE PAGINATION & SEARCH
var allRows = Array.from(document.querySelectorAll('#tableBody tr[data-index]'));
var filtered = allRows.slice();
var perPage = 100;
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
    var numCell = r.querySelector('.td-num');
    if (numCell) numCell.textContent = i + 1;
  });
  var noRow = document.getElementById('searchEmptyRow');
  if (total === 0) {
    if (!noRow) {
      noRow = document.createElement('tr'); noRow.id = 'searchEmptyRow';
      noRow.innerHTML = '<td colspan="11" class="no-data">No records match your search.';
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
  pg.appendChild(btn('«', 1, currentPage===1?'disabled':''));
  pg.appendChild(btn('‹', currentPage-1, currentPage===1?'disabled':''));
  var startP = Math.max(1, currentPage-2), endP = Math.min(totalPages, startP+4);
  if (endP-startP < 4) startP = Math.max(1, endP-4);
  for (var p = startP; p <= endP; p++) pg.appendChild(btn(p.toString(), p, p===currentPage?'active':''));
  pg.appendChild(btn('›', currentPage+1, currentPage===totalPages?'disabled':''));
  pg.appendChild(btn('»', totalPages, currentPage===totalPages?'disabled':''));
}

// Dark Mode
var SUN_SVG2 = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
var MOON_SVG2 = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

function applyDarkMode(on) {
  document.body.classList.toggle('dark-mode', on);
  var btn = document.getElementById('darkToggle');
  if (btn) btn.innerHTML = on ? SUN_SVG2 : MOON_SVG2;
  if (notifOpen) document.getElementById('notifDropdown').classList.add('open');
}
function toggleDarkMode() {
  var on = !document.body.classList.contains('dark-mode');
  localStorage.setItem('darkMode', on ? '1' : '0');
  applyDarkMode(on);
}
if (localStorage.getItem('darkMode') === '1') applyDarkMode(true);

// ── Edit Sit-in Modal ──────────────────────────────────────────
function openEditSitin(id, purpose, lab) {
  document.getElementById('edit_record_id').value = id;

  // Set purpose dropdown
  var purposeSel = document.getElementById('edit_purpose');
  purposeSel.value = purpose;
  // If not in list, fall back to blank
  if (purposeSel.value !== purpose) purposeSel.value = '';

  // Set lab dropdown
  var labSel = document.getElementById('edit_lab');
  labSel.value = lab;
  if (labSel.value !== lab) labSel.value = '';

  document.getElementById('editSitinModal').classList.add('open');
}
function closeEditSitin() {
  document.getElementById('editSitinModal').classList.remove('open');
}
document.getElementById('editSitinModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditSitin();
});
</script>

<!-- ── Edit Sit-in Modal ── -->
<div class="modal-overlay" id="editSitinModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Edit Sit-in Record</h3>
      <button class="modal-close" onclick="closeEditSitin()">×</button>
    </div>
    <form method="POST" action="history.php">
      <input type="hidden" name="student_edit_sitin" value="1"/>
      <input type="hidden" name="record_id" id="edit_record_id"/>
      <div class="modal-body">
        <div class="modal-field">
          <label>Purpose</label>
          <select name="sit_purpose" id="edit_purpose">
            <option value="">— Select purpose —</option>
            <?php
            $purposes = ['C Programming','Java Programming','PHP Programming','Python Programming',
                         'Database','Web Design','Research','Self-Study','Others'];
            foreach ($purposes as $p) echo '<option value="'.htmlspecialchars($p).'">'.htmlspecialchars($p).'</option>';
            ?>
          </select>
        </div>
        <div class="modal-field">
          <label>Laboratory</label>
          <select name="laboratory" id="edit_lab">
            <option value="">— Select laboratory —</option>
            <?php
            $labs = ['Lab 517','Lab 518','Lab 519','Lab 520','Lab 521','Lab 522','Lab 524','Lab 526','Lab 528','Lab 530','Mac Lab'];
            foreach ($labs as $l) echo '<option value="'.htmlspecialchars($l).'">'.htmlspecialchars($l).'</option>';
            ?>
          </select>
        </div>
        <p style="font-size:11.5px;color:var(--text-muted);margin-top:-4px;">
          Only completed sit-in records can be edited. Changes will reflect in the admin panel.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeEditSitin()">Cancel</button>
        <button type="submit" class="btn-save">Save Changes</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>