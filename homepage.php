<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}
require_once 'db.php';
$CURRENT_PAGE = 'home';

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

// ── Sit-in Summary Stats ─────────────────────────────────────
try {
    $sitin_rows = $pdo->prepare("SELECT login_time, logout_time, date, laboratory, sit_purpose FROM sit_in_history WHERE student_id = ? ORDER BY date DESC, login_time DESC");
    $sitin_rows->execute([$student_id]);
    $sitin_records = $sitin_rows->fetchAll();
} catch (Exception $e) { $sitin_records = []; }

$total_sessions = count($sitin_records);
$total_hours    = 0;
$longest_mins   = 0;
$durations      = [];
foreach ($sitin_records as $r) {
    if ($r['login_time'] && $r['logout_time']) {
        $mins = (strtotime($r['logout_time']) - strtotime($r['login_time'])) / 60;
        if ($mins > 0) {
            $total_hours += $mins;
            $durations[] = $mins;
            if ($mins > $longest_mins) $longest_mins = $mins;
        }
    }
}
$avg_duration  = count($durations) > 0 ? array_sum($durations) / count($durations) : 0;
$total_hours_h = floor($total_hours / 60);
$total_hours_m = (int)($total_hours % 60);
$avg_h = floor($avg_duration / 60);
$avg_m = (int)($avg_duration % 60);
$long_h = floor($longest_mins / 60);
$long_m = (int)($longest_mins % 60);

// ── Testimonials ─────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS testimonials (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        student_id  INT NOT NULL,
        fullname    VARCHAR(255) NOT NULL,
        course      VARCHAR(100) DEFAULT '',
        message     TEXT NOT NULL,
        rating      TINYINT(1) DEFAULT 5,
        profile_photo VARCHAR(255) DEFAULT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

// Handle new testimonial submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_testimonial'])) {
    $msg    = trim($_POST['testimonial_message'] ?? '');
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    if ($msg) {
        $pdo->prepare("INSERT INTO testimonials (student_id, fullname, course, message, rating, profile_photo) VALUES (?,?,?,?,?,?)")
            ->execute([$student_id, $_SESSION['fullname'], $_SESSION['course'], $msg, $rating, $_SESSION['profile_photo'] ?? null]);
        header('Location: Homepage.php?msg=thanks'); exit;
    }
}
$testimonials = $pdo->query("SELECT * FROM testimonials ORDER BY created_at DESC LIMIT 20")->fetchAll();

// Fetch initial notifications
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
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
    } elseif (strpos($msgLower, 'feedback') !== false) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    } elseif (strpos($msgLower, 'logged out') !== false || strpos($msgLower, 'logout') !== false) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    } else {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
    }
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
}

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
.notif-icon{display:inline-flex;align-items:flex-start;justify-content:center;width:36px;flex-shrink:0;}
.notif-icon svg{width:20px;height:20px;stroke:var(--gray-600);stroke-width:1.8;fill:none;}
.notif-content{flex:1;min-width:0;}
.notif-title{font-size:14px;color:var(--gray-800);font-weight:700;margin-bottom:6px;line-height:1.4;}
.notif-desc{font-size:13px;color:var(--gray-600);line-height:1.5;margin-bottom:8px;}
.notif-date{font-size:11px;color:var(--gray-400);display:flex;align-items:center;gap:6px;margin-top:4px;}
.notif-date::before{content:"";display:inline-block;width:4px;height:4px;background:var(--gray-400);border-radius:50%;}
.notif-empty{padding:48px 24px;text-align:center;font-size:13px;color:var(--gray-400);font-style:italic;}

/* DASHBOARD GRID */
.dashboard{max-width:1280px;margin:0 auto;padding:24px 20px;display:grid;grid-template-columns:280px 1fr 300px;gap:20px;align-items:start;}
.card{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow);overflow:hidden;}
.card-head{background:var(--blue);padding:12px 16px;display:flex;align-items:center;gap:8px;}
.card-head h2{color:#fff;font-size:13px;font-weight:700;}
.card-head svg{width:15px;height:15px;stroke:rgba(255,255,255,0.8);fill:none;stroke-width:2;}
.student-avatar{display:flex;flex-direction:column;align-items:center;padding:22px 16px 18px;border-bottom:1px solid var(--gray-100);}
.avatar-circle{width:92px;height:92px;border-radius:50%;background:var(--blue-lt);border:3px solid var(--blue-bd);display:flex;align-items:center;justify-content:center;overflow:hidden;}
.avatar-circle img{width:100%;height:100%;object-fit:cover;}
.avatar-circle svg{width:42px;height:42px;stroke:var(--blue);fill:none;stroke-width:1.5;}
.student-info-list{padding:12px 16px;}
.info-row{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--gray-100);}
.info-row:last-child{border-bottom:none;}
.info-icon{width:18px;flex-shrink:0;padding-top:2px;}
.info-icon svg{width:13px;height:13px;stroke:var(--blue);fill:none;stroke-width:2;}
.info-content{flex:1;}
.info-label{font-size:10px;font-weight:800;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.06em;}
.info-value{font-size:13px;color:var(--gray-800);font-weight:600;}
.ann-scroll{max-height:440px;overflow-y:auto;}
.ann-scroll::-webkit-scrollbar{width:4px;}
.ann-scroll::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:99px;}
.ann-item{padding:14px 16px;border-bottom:1px solid var(--gray-100);}
.ann-item:last-child{border-bottom:none;}
.ann-item:hover{background:var(--gray-50);}
.ann-meta{font-size:12px;font-weight:700;color:var(--blue);margin-bottom:7px;}
.ann-bubble{background:var(--gray-50);border:1px solid var(--gray-100);border-radius:6px;padding:10px 12px;font-size:13px;color:var(--gray-700);line-height:1.65;}
.rules-scroll{max-height:440px;overflow-y:auto;padding:16px 18px;}
.rules-scroll::-webkit-scrollbar{width:4px;}
.rules-scroll::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:99px;}
.rules-header{text-align:center;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--gray-100);}
.rules-header h3{font-size:14px;font-weight:800;color:var(--blue-dk);}
.rules-header p{font-size:11px;font-weight:700;color:var(--gray-500);margin-top:3px;}
.rules-section-title{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:var(--blue);margin:14px 0 10px;}
.rules-intro{font-size:13px;color:var(--gray-700);line-height:1.65;margin-bottom:12px;}
.rules-list{display:flex;flex-direction:column;gap:10px;}
.rule-item{display:flex;gap:10px;font-size:13px;color:var(--gray-700);line-height:1.6;}
.rule-num{min-width:22px;height:22px;border-radius:50%;background:var(--blue-lt);color:var(--blue);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;border:1px solid var(--blue-bd);}

/* SIT-IN SUMMARY CARDS */
.summary-section{max-width:1280px;margin:0 auto;padding:0 20px 30px;}
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:30px;}
.summary-card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow);padding:20px 16px;text-align:center;}
.summary-icon{width:44px;height:44px;border-radius:12px;background:var(--blue-lt);border:1px solid var(--blue-bd);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;}
.summary-icon svg{width:22px;height:22px;stroke:var(--blue);fill:none;stroke-width:2;}
.summary-val{font-size:28px;font-weight:800;color:var(--blue-dk);line-height:1.2;margin-bottom:6px;}
.summary-label{font-size:12px;color:var(--gray-500);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;}

/* SESSIONS TABLE - ORGANIZED */
.sessions-container{max-width:1280px;margin:0 auto;padding:0 20px 40px;}
.sessions-card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;}
.sessions-header{background:var(--blue);padding:16px 20px;border-bottom:1px solid var(--gray-100);}
.sessions-header h2{color:#fff;font-size:16px;font-weight:700;display:flex;align-items:center;gap:10px;}
.sessions-header h2 svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2;}
.sessions-table-wrapper{overflow-x:auto;}
.sessions-table{width:100%;border-collapse:collapse;}
.sessions-table th{background:var(--gray-100);color:var(--gray-700);font-size:12px;font-weight:700;padding:14px 16px;text-align:left;border-bottom:1px solid var(--gray-200);text-transform:uppercase;letter-spacing:0.05em;}
.sessions-table td{padding:12px 16px;font-size:13px;color:var(--gray-700);border-bottom:1px solid var(--gray-100);}
.sessions-table tr:last-child td{border-bottom:none;}
.sessions-table tr:hover{background:var(--gray-50);}
.status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;}
.status-badge.active{background:#dcfce7;color:#15803d;}
.status-badge.done{background:#f1f5f9;color:#64748b;}
body.dark-mode .status-badge.active{background:#065f46;color:#a7f3d0;}
body.dark-mode .status-badge.done{background:#334155;color:#94a3b8;}
.empty-table{text-align:center;padding:48px 20px;color:var(--gray-500);font-style:italic;}

/* TESTIMONIALS - ORGANIZED */
.testimonials-section{max-width:1280px;margin:0 auto;padding:0 20px 50px;}
.testimonials-header{margin-bottom:24px;padding-bottom:12px;border-bottom:2px solid var(--blue);display:inline-block;}
.testimonials-header h2{font-size:20px;font-weight:800;color:var(--blue-dk);display:flex;align-items:center;gap:10px;}
.testimonials-header h2 svg{width:22px;height:22px;stroke:var(--blue);fill:none;stroke-width:2;}
.testimonials-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-bottom:30px;}
.testimonial-card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow);padding:20px;transition:transform 0.2s, box-shadow 0.2s;}
.testimonial-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);}
.testimonial-profile{display:flex;align-items:center;gap:14px;margin-bottom:16px;}
.testimonial-avatar{width:52px;height:52px;border-radius:50%;background:var(--blue-lt);border:2px solid var(--blue-bd);display:flex;align-items:center;justify-content:center;overflow:hidden;}
.testimonial-avatar img{width:100%;height:100%;object-fit:cover;}
.testimonial-avatar svg{width:30px;height:30px;stroke:var(--blue);fill:none;stroke-width:1.5;}
.testimonial-info{flex:1;}
.testimonial-name{font-size:15px;font-weight:800;color:var(--blue-dk);}
.testimonial-course{font-size:11px;color:var(--gray-500);margin-top:2px;}
.testimonial-date{font-size:10px;color:var(--gray-400);margin-top:2px;}
.testimonial-stars{color:#f59e0b;font-size:14px;margin-bottom:12px;letter-spacing:2px;}
.testimonial-message{font-size:13.5px;color:var(--gray-700);line-height:1.6;font-style:italic;}
.testimonial-message::before{content:"\201C";font-size:20px;color:var(--blue);opacity:0.5;}
.testimonial-message::after{content:"\201D";font-size:20px;color:var(--blue);opacity:0.5;vertical-align:top;}

.testimonial-form-card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow);padding:24px;margin-top:10px;}
.testimonial-form-card h3{font-size:16px;font-weight:800;color:var(--blue-dk);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.testimonial-form-card textarea{width:100%;padding:12px 14px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--gray-800);background:var(--white);resize:vertical;min-height:100px;outline:none;transition:border-color 0.15s;}
.testimonial-form-card textarea:focus{border-color:var(--blue);}
.rating-section{display:flex;align-items:center;gap:12px;margin:15px 0;}
.rating-label{font-size:13px;font-weight:600;color:var(--gray-600);}
.star-rating{display:flex;flex-direction:row-reverse;gap:8px;}
.star-rating input{display:none;}
.star-rating label{cursor:pointer;font-size:26px;color:#d1d5db;transition:color 0.15s;}
.star-rating input:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label{color:#f59e0b;}
.btn-submit-testimonial{margin-top:12px;padding:10px 24px;border:none;border-radius:var(--radius);background:var(--blue-dk);color:#fff;font-size:13px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background 0.15s;display:inline-flex;align-items:center;gap:8px;}
.btn-submit-testimonial:hover{background:#002255;}

@media(max-width:900px){.dashboard{grid-template-columns:1fr;}}
@media(max-width:700px){.summary-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:550px){
  .notif-dropdown{width:calc(100vw - 30px);right:-10px;}
  .notif-item{gap:10px;padding:12px 14px;}
  .notif-icon{width:30px;}
  .testimonials-grid{grid-template-columns:1fr;}
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
              <form method="POST" action="Homepage.php" style="margin:0;">
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
    <a href="Homepage.php" class="active">Home</a>
    <a href="profile.php">Edit Profile</a>
    <a href="history.php">History</a>
    <a href="feedback.php">Feedback</a>
    <a href="reservation.php">Reservation</a>
    <a href="software.php">Lab Software</a>
    <button onclick="toggleDarkMode()" class="btn-dark-toggle" id="darkToggle" title="Toggle dark mode">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
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
      <div class="info-row"><div class="info-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8.5" cy="10" r="2.5"/><path d="M4 19c0-2.2 2-4 4.5-4s4.5 1.8 4.5 4"/></svg></div><div class="info-content"><div class="info-label">ID Number</div><div class="info-value"><?= htmlspecialchars($_SESSION['id_number'] ?? '') ?></div></div></div>
      <div class="info-row"><div class="info-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div><div class="info-content"><div class="info-label">Full Name</div><div class="info-value"><?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></div></div></div>
      <div class="info-row"><div class="info-icon"><svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></div><div class="info-content"><div class="info-label">Course</div><div class="info-value"><?= htmlspecialchars($_SESSION['course'] ?? '') ?></div></div></div>
      <div class="info-row"><div class="info-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div><div class="info-content"><div class="info-label">Year Level</div><div class="info-value"><?= htmlspecialchars($_SESSION['year_level'] ?? '') ?></div></div></div>
      <div class="info-row"><div class="info-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div><div class="info-content"><div class="info-label">Email Address</div><div class="info-value"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div></div></div>
      <div class="info-row"><div class="info-icon"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div><div class="info-content"><div class="info-label">Address</div><div class="info-value"><?= htmlspecialchars($_SESSION['address'] ?? '') ?></div></div></div>
      <div class="info-row"><div class="info-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div><div class="info-content"><div class="info-label">Remaining Sessions</div><div class="info-value"><?= htmlspecialchars($_SESSION['session'] ?? '') ?></div></div></div>
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
            <div class="ann-bubble"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
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

<!-- SIT-IN SUMMARY SECTION -->
<div class="summary-section">
  <div class="summary-grid">
    <div class="summary-card">
      <div class="summary-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
      <div class="summary-val"><?= $total_sessions ?></div>
      <div class="summary-label">Total Sessions</div>
    </div>
    <div class="summary-card">
      <div class="summary-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      <div class="summary-val"><?= $total_hours_h ?>h <?= $total_hours_m ?>m</div>
      <div class="summary-label">Total Hours</div>
    </div>
    <div class="summary-card">
      <div class="summary-icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></div>
      <div class="summary-val"><?= $avg_h ?>h <?= $avg_m ?>m</div>
      <div class="summary-label">Average Duration</div>
    </div>
    <div class="summary-card">
      <div class="summary-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
      <div class="summary-val"><?= $long_h ?>h <?= $long_m ?>m</div>
      <div class="summary-label">Longest Session</div>
    </div>
  </div>
</div>

<!-- MY SESSIONS TABLE - ORGANIZED -->
<div class="sessions-container">
  <div class="sessions-card">
    <div class="sessions-header">
      <h2>
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        My Sessions
      </h2>
    </div>
    <div class="sessions-table-wrapper">
      <table class="sessions-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Duration</th>
            <th>Laboratory</th>
            <th>Purpose</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($sitin_records): $i=0; foreach ($sitin_records as $r): $i++;
            $duration = '—';
            if ($r['login_time'] && $r['logout_time']) {
              $mins = (strtotime($r['logout_time']) - strtotime($r['login_time'])) / 60;
              if ($mins > 0) {
                $dh = floor($mins / 60); $dm = (int)($mins % 60);
                $duration = ($dh > 0 ? $dh.'h ' : '') . $dm . 'm';
              }
            }
            $isActive = empty($r['logout_time']);
          ?>
          <tr>
            <td><?= $i ?></td>
            <td><?= $r['date'] ? date('M d, Y', strtotime($r['date'])) : '—' ?></td>
            <td><?= $r['login_time'] ? date('h:i A', strtotime($r['login_time'])) : '—' ?></td>
            <td><?= $r['logout_time'] ? date('h:i A', strtotime($r['logout_time'])) : '—' ?></td>
            <td><strong><?= $duration ?></strong></td>
            <td><?= htmlspecialchars($r['laboratory'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['sit_purpose'] ?? '—') ?></td>
            <td>
              <?php if ($isActive): ?>
                <span class="status-badge active">● Active</span>
              <?php else: ?>
                <span class="status-badge done">✓ Completed</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="empty-table">No sit-in sessions recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- STUDENT TESTIMONIALS - ORGANIZED -->
<div class="testimonials-section">
  <div class="testimonials-header">
    <h2>
      <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Student Testimonials
    </h2>
  </div>

  <?php if ($testimonials): ?>
  <div class="testimonials-grid">
    <?php foreach ($testimonials as $t): ?>
    <div class="testimonial-card">
      <div class="testimonial-profile">
        <div class="testimonial-avatar">
          <?php if (!empty($t['profile_photo']) && file_exists('uploads/profiles/'.$t['profile_photo'])): ?>
            <img src="uploads/profiles/<?= htmlspecialchars($t['profile_photo']) ?>" alt="Profile">
          <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <?php endif; ?>
        </div>
        <div class="testimonial-info">
          <div class="testimonial-name"><?= htmlspecialchars($t['fullname']) ?></div>
          <div class="testimonial-course"><?= htmlspecialchars($t['course']) ?></div>
          <div class="testimonial-date"><?= date('M d, Y', strtotime($t['created_at'])) ?></div>
        </div>
      </div>
      <div class="testimonial-stars"><?= str_repeat('★', (int)$t['rating']) . str_repeat('☆', 5 - (int)$t['rating']) ?></div>
      <div class="testimonial-message"><?= htmlspecialchars($t['message']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-table" style="background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);margin-bottom:30px;">No testimonials yet. Be the first to share your experience!</div>
  <?php endif; ?>

  <!-- Submit Testimonial Form -->
  <div class="testimonial-form-card">
    <h3>
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      Share Your Experience
    </h3>
    <form method="POST">
      <div class="rating-section">
        <span class="rating-label">Your Rating:</span>
        <div class="star-rating">
          <input type="radio" name="rating" id="star5" value="5"><label for="star5">★</label>
          <input type="radio" name="rating" id="star4" value="4"><label for="star4">★</label>
          <input type="radio" name="rating" id="star3" value="3"><label for="star3">★</label>
          <input type="radio" name="rating" id="star2" value="2"><label for="star2">★</label>
          <input type="radio" name="rating" id="star1" value="1"><label for="star1">★</label>
        </div>
      </div>
      <textarea name="testimonial_message" placeholder="Write your testimonial here... Share your experience with the computer laboratories, facilities, and services." required></textarea>
      <button type="submit" name="submit_testimonial" class="btn-submit-testimonial">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="white" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Submit Testimonial
      </button>
    </form>
  </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'thanks'): ?>
<div style="position:fixed;bottom:20px;right:20px;background:#16a34a;color:white;padding:12px 20px;border-radius:8px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.2);z-index:1000;" id="toastMsg">
  Thank you for your testimonial!
</div>
<script>setTimeout(function(){document.getElementById('toastMsg')?.remove();},3000);</script>
<?php endif; ?>

<script>
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
        headRight.innerHTML = '<form method="POST" action="Homepage.php" style="margin:0;"><input type="hidden" name="mark_notif_read" value="1"/><button type="submit" class="notif-mark">Mark all read</button></form>';
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
  if (msgLower.indexOf('announcement') !== false) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
  if (msgLower.indexOf('feedback') !== false) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
  if (msgLower.indexOf('logged out') !== false || msgLower.indexOf('logout') !== false) return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
  return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#4B5563" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

refreshTimestamps();
setInterval(refreshTimestamps, 30000);
setInterval(pollNotifications, 15000);
pollNotifications();

var notifOpen = false;
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