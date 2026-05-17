<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}
require_once 'db.php';
$CURRENT_PAGE = 'reservation';

$student_id = (int)$_SESSION['student_id'];

// ── Ensure reservations table exists ────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reservations (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        student_id  INT NOT NULL,
        id_number   VARCHAR(50) NOT NULL,
        purpose     VARCHAR(255) NOT NULL,
        laboratory  VARCHAR(100) NOT NULL,
        date        DATE NOT NULL,
        time_in     TIME NOT NULL,
        status      ENUM('pending','approved','rejected') DEFAULT 'pending',
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )");
    try { $pdo->exec("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS pc_number VARCHAR(20) DEFAULT NULL"); } catch(Exception $e){}
} catch (Exception $e) {}

// ── Check if reservations are enabled ───────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key   VARCHAR(100) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $resvSetting = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'reservations_open' LIMIT 1")->fetchColumn();
    $reservations_open = ($resvSetting === false) ? true : ($resvSetting === '1');
} catch (Exception $e) {
    $reservations_open = true;
}

// ── Handle mark-all-read ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notif_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0")->execute([$student_id]);
    header('Location: reservation.php'); exit;
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

// ── Handle new reservation ───────────────────────────────────
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reservation'])) {
    if (!$reservations_open) {
        $error = 'Reservations are currently disabled by the administrator.';
    } else {
        $purpose = trim($_POST['purpose'] ?? '');
        $lab     = trim($_POST['laboratory'] ?? '');
        $date    = trim($_POST['date'] ?? '');
        $time_in = trim($_POST['time_in'] ?? '');
        if (!$purpose || !$lab || !$date || !$time_in) {
            $error = 'Please fill in all required fields.';
        } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
            $error = 'Reservation date cannot be in the past.';
        } else {
            $dup = $pdo->prepare("SELECT id FROM reservations WHERE student_id=? AND date=? AND status != 'rejected' LIMIT 1");
            $dup->execute([$student_id, $date]);
            if ($dup->fetch()) {
                $error = 'You already have a reservation on that date.';
            } else {
                $pdo->prepare("INSERT INTO reservations (student_id, id_number, purpose, laboratory, date, time_in) VALUES (?,?,?,?,?,?)")
                    ->execute([$student_id, $_SESSION['id_number'], $purpose, $lab, $date, $time_in]);
                $success = 'Reservation submitted successfully! Waiting for admin approval.';
            }
        }
    }
}

// ── Fetch this student's reservations ───────────────────────
$my_reservations = $pdo->prepare("SELECT * FROM reservations WHERE student_id = ? ORDER BY date DESC, created_at DESC LIMIT 20");
$my_reservations->execute([$student_id]);
$my_reservations = $my_reservations->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Reservation</title>
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
  --input-bg:#ffffff;
  --input-border:#cddaec;
  --input-color:#1a2e45;
  --alert-success-bg:#f0fdf4;
  --alert-success-border:#bbf7d0;
  --alert-success-color:#16a34a;
  --alert-error-bg:#fef2f2;
  --alert-error-border:#fecaca;
  --alert-error-color:#dc2626;
  --alert-warning-bg:#fffbeb;
  --alert-warning-border:#fde68a;
  --alert-warning-color:#d97706;
  --table-head-bg:#1B5886;
  --table-row-hover:#f8fafc;
  --badge-pending-bg:#fef3c7;
  --badge-pending-color:#b45309;
  --badge-approved-bg:#dcfce7;
  --badge-approved-color:#15803d;
  --badge-rejected-bg:#fee2e2;
  --badge-rejected-color:#b91c1c;
  --disabled-notice-bg:#fffbeb;
  --disabled-notice-border:#fde68a;
  --disabled-notice-color:#92400e;
}
body.dark-mode {
  --blue:#3b82f6;
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
  --card-bg:#1e1e2e;
  --card-border:#334155;
  --text-primary:#f1f5f9;
  --text-secondary:#cbd5e1;
  --text-muted:#94a3b8;
  --border-light:#334155;
  --input-bg:#1e293b;
  --input-border:#334155;
  --input-color:#f1f5f9;
  --alert-success-bg:#14532d;
  --alert-success-border:#166534;
  --alert-success-color:#4ade80;
  --alert-error-bg:#7f1d1d;
  --alert-error-border:#991b1b;
  --alert-error-color:#f87171;
  --alert-warning-bg:#78350f;
  --alert-warning-border:#92400e;
  --alert-warning-color:#fbbf24;
  --table-head-bg:#1B5886;
  --table-row-hover:#334155;
  --badge-pending-bg:#78350f;
  --badge-pending-color:#fbbf24;
  --badge-approved-bg:#064e3b;
  --badge-approved-color:#34d399;
  --badge-rejected-bg:#7f1d1d;
  --badge-rejected-color:#f87171;
  --disabled-notice-bg:#78350f;
  --disabled-notice-border:#92400e;
  --disabled-notice-color:#fbbf24;
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
.page-body{max-width:1000px;margin:0 auto;padding:32px 20px 70px;}
.page-title{font-size:24px;font-weight:800;color:var(--text-primary);text-align:center;margin-bottom:6px;}
.page-sub{font-size:13px;color:var(--text-muted);text-align:center;margin-bottom:28px;}

/* Section Header - like testimonials style */
.section-header{margin:0 auto;padding:0 0 16px 0;max-width:100%;}
.section-header h2{font-size:20px;font-weight:800;color:var(--text-primary);display:flex;align-items:center;gap:10px;border-bottom:2px solid var(--blue);padding-bottom:10px;width:fit-content;}
.section-header h2 svg{width:22px;height:22px;stroke:var(--blue);fill:none;stroke-width:2;}

.alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--radius);font-size:13.5px;font-weight:600;margin-bottom:20px;}
.alert-success{background:var(--alert-success-bg);border:1px solid var(--alert-success-border);color:var(--alert-success-color);}
.alert-error{background:var(--alert-error-bg);border:1px solid var(--alert-error-border);color:var(--alert-error-color);}
.alert-warning{background:var(--alert-warning-bg);border:1px solid var(--alert-warning-border);color:var(--alert-warning-color);}

.card{background:var(--card-bg);border-radius:var(--radius-lg);border:1px solid var(--card-border);box-shadow:var(--shadow-md);overflow:hidden;margin-bottom:24px;}
.card-head{background:#1B5886;padding:13px 18px;display:flex;align-items:center;gap:8px;}
.card-head h2{color:#fff;font-size:13px;font-weight:700;}
.card-head svg{width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2;}
.card-body{padding:22px 24px;background:var(--card-bg);}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.field{margin-bottom:0;}
.field label{display:block;font-size:11px;font-weight:800;color:var(--text-muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:0.04em;}
.field input,.field select{width:100%;padding:9px 12px;border:1.5px solid var(--input-border);border-radius:var(--radius);font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--input-color);background:var(--input-bg);outline:none;transition:border-color .15s, background .2s;}
.field input:focus,.field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(59,130,246,0.1);}
.field.full{grid-column:1/-1;}

.btn-submit{margin-top:18px;padding:11px 30px;border:none;border-radius:var(--radius);background:var(--blue-dk);color:#fff;font-size:14px;font-weight:800;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s;}
.btn-submit:hover{background:#002255;}
body.dark-mode .btn-submit:hover{background:#1e40af;}
.btn-submit:disabled{background:var(--text-muted);cursor:not-allowed;}

/* Clean & Organized Table Styles */
.reservations-table-wrapper{overflow-x:auto;padding:0;}
.reservations-table{width:100%;border-collapse:collapse;}
.reservations-table th{background:var(--table-head-bg);color:#fff;font-size:12px;font-weight:700;padding:14px 16px;text-align:left;white-space:nowrap;letter-spacing:0.04em;text-transform:uppercase;}
.reservations-table td{padding:14px 16px;font-size:13px;color:var(--text-secondary);border-bottom:1px solid var(--border-light);}
.reservations-table tr:last-child td{border-bottom:none;}
.reservations-table tr:hover{background:var(--table-row-hover);}
.reservations-table .no-data{text-align:center;padding:60px 20px;color:var(--text-muted);font-style:italic;}

.badge{display:inline-block;padding:5px 14px;border-radius:30px;font-size:11px;font-weight:700;white-space:nowrap;}
.badge-pending{background:var(--badge-pending-bg);color:var(--badge-pending-color);}
.badge-approved{background:var(--badge-approved-bg);color:var(--badge-approved-color);}
.badge-rejected{background:var(--badge-rejected-bg);color:var(--badge-rejected-color);}

.disabled-notice{background:var(--disabled-notice-bg);border:1px solid var(--disabled-notice-border);border-radius:var(--radius);padding:16px;display:flex;align-items:center;gap:12px;margin-bottom:20px;}
.disabled-notice svg{width:22px;height:22px;stroke:var(--badge-pending-color);fill:none;stroke-width:2;flex-shrink:0;}
.disabled-notice p{font-size:13.5px;color:var(--disabled-notice-color);font-weight:600;}

@media(max-width:640px){
  .form-grid{grid-template-columns:1fr;}
  .field.full{grid-column:1;}
  .page-body{padding:20px 16px;}
  .reservations-table th,.reservations-table td{padding:10px 12px;}
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
              <form method="POST" action="reservation.php" style="margin:0;">
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
    <a href="reservation.php" class="active">Reservation</a>
    <a href="software.php">Lab Software</a>
    <button onclick="toggleDarkMode()" class="btn-dark-toggle" id="darkToggle" title="Toggle dark mode"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="page-body">
  <div class="page-title">Lab Reservation</div>
  <div class="page-sub">Reserve a laboratory slot in advance</div>

  <?php if ($success): ?>
  <div class="alert alert-success">
    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-error">
    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <?php if (!$reservations_open): ?>
  <div class="disabled-notice">
    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>Reservations are currently <strong>closed</strong> by the administrator. Please check back later.</p>
  </div>
  <?php endif; ?>

  <!-- Make a Reservation -->
  <div class="card">
    <div class="card-head">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <h2>Make a Reservation</h2>
    </div>
    <div class="card-body">
      <form method="POST">
        <div class="form-grid">
          <div class="field">
            <label>Purpose *</label>
            <input type="text" name="purpose" placeholder="e.g. C Programming, Thesis" required <?= !$reservations_open ? 'disabled' : '' ?>/>
          </div>
          <div class="field">
            <label>Laboratory *</label>
            <select name="laboratory" required <?= !$reservations_open ? 'disabled' : '' ?>>
              <option value="">— Select Lab —</option>
              <option value="Lab 517">Lab 517</option>
              <option value="Lab 524">Lab 524</option>
              <option value="Lab 526">Lab 526</option>
              <option value="Lab 528">Lab 528</option>
              <option value="Lab 530">Lab 530</option>
              <option value="Lab 542">Lab 542</option>
              <option value="Lab 544">Lab 544</option>
            </select>
          </div>
          <div class="field">
            <label>Date *</label>
            <input type="date" name="date" min="<?= date('Y-m-d') ?>" required <?= !$reservations_open ? 'disabled' : '' ?>/>
          </div>
          <div class="field">
            <label>Time In *</label>
            <input type="time" name="time_in" required <?= !$reservations_open ? 'disabled' : '' ?>/>
          </div>
        </div>
        <button type="submit" name="submit_reservation" class="btn-submit" <?= !$reservations_open ? 'disabled' : '' ?>>
          Submit Reservation
        </button>
      </form>
    </div>
  </div>

  <!-- My Reservations - Standalone Header outside the card -->
  <div class="section-header">
    <h2>
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      My Reservations
    </h2>
  </div>

  <!-- Reservations Table Card -->
  <div class="card">
    <div class="reservations-table-wrapper">
      <table class="reservations-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Purpose</th>
            <th>Laboratory</th>
            <th>Date</th>
            <th>Time In</th>
            <th>Status</th>
            <th>Submitted</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($my_reservations): $i = 0; foreach ($my_reservations as $r): $i++; ?>
          <tr>
            <td style="width: 50px;"><?= $i ?></td>
            <td><strong><?= htmlspecialchars($r['purpose']) ?></strong></td>
            <td><?= htmlspecialchars($r['laboratory']) ?></td>
            <td><?= date('M d, Y', strtotime($r['date'])) ?></td>
            <td><?= date('h:i A', strtotime($r['time_in'])) ?></td>
            <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
            <td style="font-size: 12px; color: var(--text-muted);"><?= date('M d, Y g:i A', strtotime($r['created_at'])) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="7" class="no-data">📋 No reservations yet. Make one above!</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
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
        headRight.innerHTML = '<form method="POST" action="reservation.php" style="margin:0;"><input type="hidden" name="mark_notif_read" value="1"/><button type="submit" class="notif-mark">Mark all read</button></form>';
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