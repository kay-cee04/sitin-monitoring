<?php
session_start();
require_once 'db.php';

// Handle logout first
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Also handle POST logout
if (isset($_POST['admin_logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// One-time schema fix: allow NULL student_id for walk-in students
try { $pdo->exec("ALTER TABLE sit_in_history MODIFY student_id INT NULL DEFAULT NULL"); } catch (Exception $e) {}

// Schema migration: add arrived/absent columns to reservations if missing
try { $pdo->exec("ALTER TABLE reservations ADD COLUMN arrived TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE reservations ADD COLUMN arrived_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE reservations ADD COLUMN absent TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}


// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Post announcement
    if (isset($_POST['add_announcement'])) {
        $content    = trim($_POST['content'] ?? '');
        $admin_name = $_SESSION['admin_username'];
        $pdo->prepare("INSERT INTO announcements (admin_name, content) VALUES (?, ?)")
            ->execute([$admin_name, $content ?: null]);

        // Push a notification to every student
        $notif_msg    = '📢 New announcement from ' . $admin_name . ($content ? ': ' . mb_substr($content, 0, 100) . (mb_strlen($content) > 100 ? '…' : '') : '.');
        $all_students = $pdo->query("SELECT id FROM students")->fetchAll();
        $ins = $pdo->prepare("INSERT INTO notifications (student_id, message) VALUES (?, ?)");
        foreach ($all_students as $stu) {
            $ins->execute([$stu['id'], $notif_msg]);
        }

        header('Location: admin_dashboard.php?page=home&msg=announced'); exit;
    }

    // Delete announcement
    if (isset($_POST['delete_announcement'])) {
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([(int)$_POST['ann_id']]);
        header('Location: admin_dashboard.php?page=home&msg=ann_deleted'); exit;
    }

    // Add student
    if (isset($_POST['add_student'])) {
        $id_num  = trim($_POST['id_number'] ?? '');
        $ln      = trim($_POST['lastname'] ?? '');
        $fn      = trim($_POST['firstname'] ?? '');
        $mn      = trim($_POST['middlename'] ?? '');
        $course  = trim($_POST['course'] ?? '');
        $year    = (int)($_POST['year_level'] ?? 1);
        $email   = trim($_POST['email'] ?? '');
        $pw      = password_hash(trim($_POST['password'] ?? 'Password123'), PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO students (id_number,lastname,firstname,middlename,course,year_level,email,password,session)
                       VALUES (?,?,?,?,?,?,?,?,30)")
            ->execute([$id_num,$ln,$fn,$mn,$course,$year,$email,$pw]);
        header('Location: admin_dashboard.php?page=students&msg=added'); exit;
    }

    // Edit student
    if (isset($_POST['edit_student'])) {
        $id      = (int)$_POST['student_id'];
        $ln      = trim($_POST['lastname'] ?? '');
        $fn      = trim($_POST['firstname'] ?? '');
        $mn      = trim($_POST['middlename'] ?? '');
        $course  = trim($_POST['course'] ?? '');
        $year    = (int)($_POST['year_level'] ?? 1);
        $email   = trim($_POST['email'] ?? '');
        $session = max(0, (int)($_POST['session'] ?? 0));
        $pdo->prepare("UPDATE students SET lastname=?,firstname=?,middlename=?,course=?,year_level=?,email=?,session=? WHERE id=?")
            ->execute([$ln,$fn,$mn,$course,$year,$email,$session,$id]);
        header('Location: admin_dashboard.php?page=students&msg=edited'); exit;
    }

    // Delete student
    if (isset($_POST['delete_student'])) {
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([(int)$_POST['student_id']]);
        header('Location: admin_dashboard.php?page=students&msg=deleted'); exit;
    }

    // Edit session only
    if (isset($_POST['edit_session_only'])) {
        $id      = (int)$_POST['student_id'];
        $session = max(0, min(30, (int)($_POST['session'] ?? 0)));
        $pdo->prepare("UPDATE students SET session = ? WHERE id = ?")->execute([$session, $id]);
        header('Location: admin_dashboard.php?page=students&msg=session_updated'); exit;
    }

    // Reset ONE student session
    if (isset($_POST['reset_session'])) {
        $pdo->prepare("UPDATE students SET session = 30 WHERE id = ?")->execute([(int)$_POST['student_id']]);
        header('Location: admin_dashboard.php?page=students&msg=reset'); exit;
    }

    // Reset ALL sessions
    if (isset($_POST['reset_all_sessions'])) {
        $pdo->exec("UPDATE students SET session = 30");
        header('Location: admin_dashboard.php?page=students&msg=all_reset'); exit;
    }

      // ── Export Reports ──────────────────────────────────────────
    if (isset($_POST['export_report'])) {
        $format   = $_POST['export_format'] ?? 'csv';
        $from     = trim($_POST['report_from'] ?? '');
        $to       = trim($_POST['report_to']   ?? '');
        $params   = [];
        $where    = "WHERE 1=1";
        if ($from) { $where .= " AND date >= ?"; $params[] = $from; }
        if ($to)   { $where .= " AND date <= ?"; $params[] = $to; }
        $stmt = $pdo->prepare("SELECT id_number, fullname, sit_purpose, laboratory, login_time, logout_time, date FROM sit_in_history $where ORDER BY date DESC, login_time DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="sitin_report_'.date('Ymd').'.csv"');
            $out = fopen('php://output','w');
            fputcsv($out, ['ID Number','Full Name','Purpose','Laboratory','Time In','Time Out','Date']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['id_number'],$r['fullname'],$r['sit_purpose'],$r['laboratory'],$r['login_time'],$r['logout_time'],$r['date']]);
            }
            fclose($out); exit;
        } else { // PDF via HTML print
            header('Content-Type: text/html');
            echo '<!DOCTYPE html><html><head><title>Sit-in Report</title>';
            echo '<style>body{font-family:Arial,sans-serif;font-size:12px;padding:20px;}h2{color:#003A6B;margin-bottom:4px;}p{color:#666;margin-bottom:16px;}table{width:100%;border-collapse:collapse;}th{background:#003A6B;color:#fff;padding:7px 10px;text-align:left;font-size:11px;}td{padding:6px 10px;border-bottom:1px solid #eee;}</style>';
            echo '</head><body onload="window.print()">';
            echo '<h2>CCS Sit-in Report</h2>';
            echo '<p>Generated: '.date('F j, Y g:i A').($from ? ' | From: '.$from : '').($to ? ' To: '.$to : '').'</p>';
            echo '<table><thead><tr><th>ID Number</th><th>Full Name</th><th>Purpose</th><th>Lab</th><th>Time In</th><th>Time Out</th><th>Date</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                echo '<tr><td>'.htmlspecialchars($r['id_number']).'</td><td>'.htmlspecialchars($r['fullname']).'</td><td>'.htmlspecialchars($r['sit_purpose']).'</td><td>'.htmlspecialchars($r['laboratory']).'</td><td>'.htmlspecialchars($r['login_time']).'</td><td>'.htmlspecialchars($r['logout_time'] ?? '—').'</td><td>'.htmlspecialchars($r['date']).'</td></tr>';
            }
            echo '</tbody></table></body></html>'; exit;
        }
    }

    // ── Reservation approve/reject ──────────────────────────────
    if (isset($_POST['approve_reservation'])) {
        $pdo->prepare("UPDATE reservations SET status='approved' WHERE id=?")->execute([(int)$_POST['reservation_id']]);
        header('Location: admin_dashboard.php?page=reservation&msg=approved'); exit;
    }
    if (isset($_POST['reject_reservation'])) {
        $pdo->prepare("UPDATE reservations SET status='rejected' WHERE id=?")->execute([(int)$_POST['reservation_id']]);
        header('Location: admin_dashboard.php?page=reservation&msg=rejected'); exit;
    }

    // ── Lab status toggle ───────────────────────────────────────
    if (isset($_POST['toggle_lab_status'])) {
        $lab_name   = trim($_POST['lab_name'] ?? '');
        $new_status = (int)($_POST['new_status'] ?? 1);
        if ($lab_name) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS lab_status (lab_name VARCHAR(100) PRIMARY KEY, is_active TINYINT(1) DEFAULT 1, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
                $pdo->prepare("INSERT INTO lab_status (lab_name, is_active) VALUES (?,?) ON DUPLICATE KEY UPDATE is_active=?")->execute([$lab_name, $new_status, $new_status]);
            } catch(Exception $e) {}
        }
        header('Location: admin_dashboard.php?page=reservation&msg=toggled'); exit;
    }

    // ── Lab slots update ────────────────────────────────────────
    if (isset($_POST['update_lab_slots'])) {
        $ln2  = trim($_POST['lab_name'] ?? '');
        $ns   = max(1, min(500, (int)($_POST['new_slots'] ?? 50)));
        if ($ln2) {
            $key2 = 'slots_' . str_replace(' ', '_', $ln2);
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
                $pdo->prepare("INSERT INTO system_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$key2, $ns, $ns]);
            } catch(Exception $e) {}
        }
        header('Location: admin_dashboard.php?page=reservation'); exit;
    }

    // ── Mark arrived / absent ───────────────────────────────────
    if (isset($_POST['mark_arrived'])) {
        $resv_id = (int)$_POST['reservation_id'];
        try { $pdo->prepare("UPDATE reservations SET arrived=1, arrived_at=NOW() WHERE id=?")->execute([$resv_id]); } catch(Exception $e) {}
        header('Location: admin_dashboard.php?page=reservation&msg=arrived'); exit;
    }
    if (isset($_POST['mark_absent'])) {
        $resv_id = (int)$_POST['reservation_id'];
        try { $pdo->prepare("UPDATE reservations SET arrived=0, absent=1 WHERE id=?")->execute([$resv_id]); } catch(Exception $e) {}
        header('Location: admin_dashboard.php?page=reservation&msg=absent'); exit;
    }

    // ── Toggle Reservations open/closed ────────────────────────
    if (isset($_POST['toggle_reservations'])) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
        } catch(Exception $e){}
        $cur = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='reservations_open'")->fetchColumn();
        $newVal = ($cur === '1' || $cur === false) ? '0' : '1';
        $pdo->prepare("INSERT INTO system_settings (setting_key,setting_value) VALUES ('reservations_open',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$newVal,$newVal]);
        header('Location: admin_dashboard.php?page=reservation&msg=toggled'); exit;
    }

    // ── Software upload ─────────────────────────────────────────
    if (isset($_POST['add_software'])) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS lab_software (id INT AUTO_INCREMENT PRIMARY KEY, lab_name VARCHAR(100) NOT NULL, software VARCHAR(255) NOT NULL, category VARCHAR(100) DEFAULT 'General', is_available TINYINT(1) DEFAULT 1, added_by VARCHAR(100) DEFAULT 'Admin', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        } catch(Exception $e){}
        $lab  = trim($_POST['sw_lab'] ?? '');
        $sw   = trim($_POST['sw_name'] ?? '');
        $cat  = trim($_POST['sw_category'] ?? 'General');
        if ($lab && $sw) {
            $pdo->prepare("INSERT INTO lab_software (lab_name, software, category, added_by) VALUES (?,?,?,?)")
                ->execute([$lab, $sw, $cat, $_SESSION['admin_username']]);
        }
        header('Location: admin_dashboard.php?page=software&msg=sw_added'); exit;
    }
    if (isset($_POST['delete_software'])) {
        $pdo->prepare("DELETE FROM lab_software WHERE id=?")->execute([(int)$_POST['sw_id']]);
        header('Location: admin_dashboard.php?page=software&msg=sw_deleted'); exit;
    }
      if (isset($_POST['do_sitin'])) {
          $id_num  = trim($_POST['id_number'] ?? '');
          $name    = trim($_POST['student_name'] ?? '');
          $purpose = trim($_POST['purpose'] ?? '');
          $lab     = trim($_POST['lab'] ?? '');
          $current_page = $_POST['current_page'] ?? 'home';

          if (!$id_num || !$name || !$purpose || !$lab) {
              header('Location: admin_dashboard.php?page=' . $current_page . '&msg=sitin_err');
              exit;
          }

          try {
              // Try to find a registered student by ID number
              $stu = $pdo->prepare("SELECT * FROM students WHERE id_number = ? LIMIT 1");
              $stu->execute([$id_num]);
              $found = $stu->fetch();

              if ($found) {
                  // Get current session count BEFORE deducting (for history)
                  $currentSession = (int)$found['session'];
                  
                  // Registered student — deduct session and log sit-in
                  $pdo->prepare("UPDATE students SET session = session - 1 WHERE id = ? AND session > 0")
                      ->execute([$found['id']]);
                  
                  // Insert with session_at_login = current session before deduction
                  $pdo->prepare("INSERT INTO sit_in_history (student_id, id_number, fullname, sit_purpose, laboratory, login_time, date, session_at_login) VALUES (?,?,?,?,?,NOW(),CURDATE(),?)")
                      ->execute([$found['id'], $id_num, $name, $purpose, $lab, $currentSession]);
              } else {
                  // Walk-in — no session tracking
                  try {
                      $pdo->exec("ALTER TABLE sit_in_history MODIFY student_id INT NULL");
                  } catch (Exception $e) { /* already nullable */ }
                  $pdo->prepare("INSERT INTO sit_in_history (student_id, id_number, fullname, sit_purpose, laboratory, login_time, date, session_at_login) VALUES (NULL,?,?,?,?,NOW(),CURDATE(),NULL)")
                      ->execute([$id_num, $name, $purpose, $lab]);
              }
          } catch (PDOException $e) {
              error_log('Sit-in error: ' . $e->getMessage());
          }

          header('Location: admin_dashboard.php?page=' . $current_page . '&msg=sittin');
          exit;
      }

    // Logout a sit-in record
    if (isset($_POST['logout_sitin'])) {
        $pdo->prepare("UPDATE sit_in_history SET logout_time = NOW() WHERE id = ? AND logout_time IS NULL")
            ->execute([(int)$_POST['sitin_id']]);
        header('Location: admin_dashboard.php?page=sitin&msg=logout'); exit;
    }

    // Edit a sit-in history record
    if (isset($_POST['edit_sitin_record'])) {
        $sid      = (int)$_POST['sitin_record_id'];
        $purpose  = trim($_POST['edit_sit_purpose'] ?? '');
        $lab      = trim($_POST['edit_laboratory']  ?? '');
        $login_t  = trim($_POST['edit_login_time']  ?? '');
        $logout_t = trim($_POST['edit_logout_time'] ?? '');
        $date     = trim($_POST['edit_date']         ?? '');
        // Build logout_time value (null if empty)
        $logout_val = $logout_t ? $date . ' ' . $logout_t : null;
        $login_val  = $login_t  ? $date . ' ' . $login_t  : null;
        $pdo->prepare("UPDATE sit_in_history SET sit_purpose=?, laboratory=?, login_time=?, logout_time=?, date=? WHERE id=?")
            ->execute([$purpose, $lab, $login_val, $logout_val, $date, $sid]);
        header('Location: admin_dashboard.php?page=sitin&msg=history_edited'); exit;
    }
}

// ── Fetch data ───────────────────────────────────────────────
$total_students  = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$currently_sitin = (int)$pdo->query("SELECT COUNT(*) FROM sit_in_history WHERE logout_time IS NULL AND date = CURDATE()")->fetchColumn();
$total_sitin     = (int)$pdo->query("SELECT COUNT(*) FROM sit_in_history")->fetchColumn();

// Purpose breakdown for pie chart
$purpose_rows = $pdo->query("SELECT sit_purpose, COUNT(*) as cnt FROM sit_in_history GROUP BY sit_purpose ORDER BY cnt DESC LIMIT 6")->fetchAll();

$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
$students      = $pdo->query("SELECT * FROM students ORDER BY id_number ASC")->fetchAll();
$current_sitin = $pdo->query("
    SELECT s.*, 
           COALESCE(st.session, '—') as remaining_session,
           COALESCE(st.id, 0) as student_db_id
    FROM sit_in_history s 
    LEFT JOIN students st ON s.id_number = st.id_number
    WHERE s.logout_time IS NULL AND s.date = CURDATE() 
    ORDER BY s.login_time DESC
")->fetchAll();
$all_sitin     = $pdo->query("SELECT * FROM sit_in_history ORDER BY created_at DESC LIMIT 100")->fetchAll();
$reservations  = $pdo->query("SELECT r.*, s.firstname, s.lastname FROM reservations r JOIN students s ON r.student_id = s.id ORDER BY r.created_at DESC")->fetchAll();

$page = $_GET['page'] ?? 'home';

$flash_map = [
    'announced'  => '✅ Announcement posted.',
    'ann_deleted'=> '✅ Announcement deleted.',
    'added'      => '✅ Student added.',
    'edited'     => '✅ Student updated.',
    'deleted'    => '✅ Student deleted.',
    'reset'      => '✅ Session reset to 30.',
    'all_reset'  => '✅ All sessions reset to 30.',
    'sittin'     => '✅ Student logged in successfully.',
    'logout'     => '✅ Student logged out.',
    'sitin_err'  => '❌ Please fill in all required fields (ID, Name, Purpose, Lab).',
    'session_updated' => '✅ Session updated successfully.',
    'history_edited'  => '✅ Sit-in record updated successfully.',
    'approved'   => '✅ Reservation approved.',
    'rejected'   => '✅ Reservation rejected.',
    'toggled'    => '✅ Reservation setting updated.',
    'sw_added'   => '✅ Software added.',
    'sw_deleted' => '✅ Software removed.',
    'arrived'    => '✅ Session started — student has arrived.',
    'absent'     => '✅ Student marked as absent.',
];

// ── Fetch Software ────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lab_software (id INT AUTO_INCREMENT PRIMARY KEY, lab_name VARCHAR(100) NOT NULL, software VARCHAR(255) NOT NULL, category VARCHAR(100) DEFAULT 'General', is_available TINYINT(1) DEFAULT 1, added_by VARCHAR(100) DEFAULT 'Admin', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $software_list = $pdo->query("SELECT * FROM lab_software ORDER BY lab_name, category, software")->fetchAll();
} catch(Exception $e) { $software_list = []; }

// ── Reservation toggle status ─────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    $resvOpen = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='reservations_open'")->fetchColumn();
    $reservations_open = ($resvOpen === false) ? true : ($resvOpen === '1');
} catch(Exception $e) { $reservations_open = true; }

// ── Testimonials ──────────────────────────────────────────────
try {
    $testimonials_admin = $pdo->query("SELECT * FROM testimonials ORDER BY created_at DESC")->fetchAll();
} catch(Exception $e) { $testimonials_admin = []; }

// ── Analytics: daily sit-in last 7 days ──────────────────────
try {
    $daily_stats = $pdo->query("SELECT date, COUNT(*) as cnt FROM sit_in_history WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY date ORDER BY date ASC")->fetchAll();
} catch(Exception $e) { $daily_stats = []; }
$flash_msg = $flash_map[$_GET['msg'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{
  --blue:#1B5886;
  --blue-dk:#003A6B;
  --blue-lt:#e8f4fb;
  --blue-bd:#89CFF1;
  --gray-50:#f4f8fc;
  --gray-100:#e8f0f7;
  --gray-200:#cddaec;
  --gray-400:#8aaac8;
  --gray-600:#3d607f;
  --gray-800:#1a2e45;
  --white:#fff;
  --radius:6px;
  --shadow:0 1px 3px rgba(0,58,107,0.09);
  --shadow-md:0 4px 16px rgba(0,58,107,0.12);
  --red:#dc2626;
  --green:#16a34a;
  --green-lt:#f0fdf4;
}

*{
  box-sizing:border-box;
  margin:0;padding:0;
}

body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--gray-50);
  color:var(--gray-800);
  font-size:14px;
}

/* ── NAV ── */
nav{
  background:var(--blue-dk);
  height:50px;
  padding:0 16px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  position:sticky;
  top:0;z-index:200;
  gap:10px;
}

.nav-brand{
  font-size:13px;
  font-weight:700;
  color:#fff;
  white-space:nowrap;
  flex-shrink:0;
}
.nav-links{
  display:flex;
  align-items:center;
  gap:1px;
  flex-wrap:nowrap;
  overflow-x:auto;
  scrollbar-width:none;
}
.nav-links::-webkit-scrollbar{display:none;}

.nav-links a{
  font-size:11.5px;
  font-weight:500;
  color:rgba(255,255,255,0.82);
  text-decoration:none;
  padding:5px 8px;
  border-radius:4px;
  white-space:nowrap;
  transition:all .15s;
  flex-shrink:0;
}

.nav-links a:hover{
  color:#fff;
  background:rgba(255,255,255,0.13);
}

.nav-links a.active{
  color:#89CFF1;
  font-weight:700;
  background:rgba(137,207,241,0.1);
}

/* Logout button styles */
.btn-logout-nav{
  background:#e8b800 !important;
  color:#1a1a00 !important;
  font-weight:700 !important;
  border-radius:4px;
  padding:4px 12px !important;
  margin-left:4px;
  flex-shrink:0;
}

.btn-logout-nav:hover{
  background:#ffd000 !important;
  color:#1a1a00 !important;
}

/* Search / Sit-in nav links — act like buttons but styled as nav links */
.nav-search-link,
.nav-sitin-link{
  cursor:pointer;
}

/* ── FLASH ── */
.flash{background:var(--green-lt);
border:1px solid #bbf7d0;
color:var(--green);
padding:9px 16px;
border-radius:var(--radius);
font-size:13px;
margin-bottom:18px;
font-weight:500;
}

/* ── PAGE BODY ── */
.page-body{
  max-width:1280px;
  margin:0 auto;
  padding:22px 20px 60px;
}

.page-section{
  display:none;
}

.page-section.active{
  display:block;
}
.page-title{
  font-size:20px;
  font-weight:700;
  color:var(--blue-dk);
  margin-bottom:20px;
  text-align:center;
}

/* ── HOME: two-column grid ── */
.home-grid{
  display:grid;
  grid-template-columns:1fr 1fr;gap:20px;
}

/* ── CARD ── */
.card{
  background:var(--white);
  border:1px solid var(--gray-200);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  overflow:hidden;
  margin-bottom:20px;
}

.card-head{
  background:var(--blue);
  padding:10px 16px;
  display:flex;
  align-items:center;
  justify-content:space-between;
}

.card-head h2{
  color:#fff;
  font-size:13px;
  font-weight:600;
}

.card-body{
  padding:16px;
}

/* ── STATS ── */
.stat-row{
  display:flex;
  flex-direction:column;
  gap:0;padding:0;
}

.stat-item{
  display:flex;
  align-items:center;
  gap:12px;
  padding:12px 16px;
  border-bottom:1px solid var(--gray-100);
}

.stat-item:last-child{
  border-bottom:none;
}

.stat-icon-box{
  width:36px;
  height:36px;
  border-radius:8px;
  display:flex;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
}

.stat-icon-box svg{
  width:18px;
  height:18px;
  fill:none;
  stroke-width:2;
  stroke-linecap:round;
  stroke-linejoin:round;
}

.stat-icon-blue{
  background:#e8f4fb;
  border:1px solid #89CFF1;
}

.stat-icon-blue svg{
  stroke:#1B5886;
}

.stat-icon-green{
  background:#f0fdf4;
  border:1px solid #86efac;
}

.stat-icon-green svg{
  stroke:#16a34a;
}

.stat-icon-orange{
  background:#fff7ed;
  border:1px solid #fed7aa;
}

.stat-icon-orange svg{
  stroke:#ea580c;
}

.stat-text{
  flex:1;
}

.stat-label{
  font-size:12px;
  color:var(--gray-400);
  font-weight:500;
}

.stat-value{
  font-size:22px;
  font-weight:800;
  color:var(--blue-dk);
  line-height:1.1;
  letter-spacing:-0.02em;
}

/* ── PIE CHART ── */
.chart-wrap{
  padding:12px 16px 16px;
  display:flex;
  justify-content:center;
}

.chart-wrap canvas{
  max-width:280px;
}

/* ── ANNOUNCE FORM ── */
.ann-form{
  padding:14px 16px;
}

.ann-form textarea{
  width:100%;
  padding:9px 11px;
  border:1px solid var(--gray-200);
  border-radius:var(--radius);
  font-family:'Inter',sans-serif;
  font-size:13px;
  resize:vertical;
  min-height:80px;
  outline:none;
  transition:border-color .15s;
}

.ann-form textarea:focus{
  border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(27,88,134,0.10);
}

.btn-submit{
  padding:7px 20px;
  border:none;
  border-radius:var(--radius);
  background:#16a34a;
  color:#fff;
  font-size:13px;
  font-weight:600;
  font-family:'Inter',sans-serif;
  cursor:pointer;
  margin-top:8px;
  transition:background .15s;
}

.btn-submit:hover{
  background:#15803d;
}

/* ── ANN LIST ── */
.ann-posted-title{
  font-size:16px;
  font-weight:700;
  padding:12px 16px 4px;
}

.ann-item{
  padding:10px 16px;
  border-top:1px solid var(--gray-100);
}

.ann-meta{
  font-size:12.5px;
  font-weight:600;
  color:var(--blue-dk);
  margin-bottom:4px;
}

.ann-content{
  font-size:13px;
  color:var(--gray-600);
}

.ann-del{
  float:right;
}

/* ── TABLE ── */
.table-wrap{
  overflow-x:auto;
}

table{
  width:100%;
  border-collapse:collapse;
}

thead th{
  background:var(--gray-50);
  color:var(--gray-600);
  font-size:11.5px;
  font-weight:700;
  padding:9px 12px;
  text-align:left;
  border-bottom:2px solid var(--gray-200);
  white-space:nowrap;
  letter-spacing:0.03em;
}

tbody tr{
  border-bottom:1px solid var(--gray-100);
  transition:background .1s;
}

tbody tr:hover{
  background:var(--gray-50);
}

tbody td{
  padding:9px 12px;
  font-size:13px;
  color:var(--gray-600);
}

.no-data{
  text-align:center;
  padding:28px;
  color:var(--gray-400);
  font-size:13px;
  font-style:italic;
}

/* Sortable header arrow */
thead th.sortable{
  cursor:pointer;
  user-select:none;
}

thead th.sortable::after{
  content:' ⇅';
  font-size:10px;
  opacity:0.5;
}

/* ── TOOLBAR ── */
.toolbar{
  display:flex;
  align-items:center;
  gap:10px;
  margin-bottom:14px;
  flex-wrap:wrap;
}

.toolbar-right{
  margin-left:auto;
  display:flex;
  align-items:center;
  gap:8px;
}

.entries-select{
  padding:5px 8px;
  border:1px solid var(--gray-200);
  border-radius:var(--radius);
  font-size:13px;
  font-family:'Inter',sans-serif;
}

.search-input{
  padding:6px 11px;
  border:1px solid var(--gray-200);
  border-radius:var(--radius);
  font-size:13px;
  font-family:'Inter',sans-serif;
  width:180px;
  outline:none;
}

.search-input:focus{
  border-color:var(--blue);
}

/* ── BUTTONS ── */
.btn{
  padding:7px 16px;
  border:none;
  border-radius:var(--radius);
  font-size:13px;
  font-weight:600;
  font-family:'Inter',sans-serif;
  cursor:pointer;
  transition:all .15s;
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  gap:5px;
}

.btn-blue{
  background:#1976d2;
  color:#fff;
}

.btn-blue:hover{
  background:#1558a0;
}

.btn-red{
  background:var(--red);
  color:#fff;
}

.btn-red:hover{
  background:#b91c1c;
}

.btn-green{
  background:#16a34a;
  color:#fff;
}

.btn-green:hover{
  background:#15803d;
}

.btn-sm{
  padding:4px 11px;
  font-size:12px;
}

/* ── MODAL ── */
.modal-overlay{
  display:none;
  position:fixed;
  inset:0;
  background:rgba(0,0,0,0.45);
  z-index:500;
  align-items:center;
  justify-content:center;
}

.modal-overlay.open{
  display:flex;
}

.modal{
  background:var(--white);
  border-radius:8px;
  box-shadow:var(--shadow-md);
  width:100%;
  max-width:480px;
  padding:0;
  overflow:hidden;
}

.modal-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:14px 20px;
  border-bottom:1px solid var(--gray-200);
}

.modal-head h3{
  font-size:15px;
  font-weight:700;
  color:var(--gray-800);
}

.modal-close{
  background:none;
  border:none;
  font-size:20px;
  cursor:pointer;
  color:var(--gray-400);
  line-height:1;
  padding:0 4px;
}

.modal-close:hover{
  color:var(--gray-800);
}

.modal-body{
  padding:20px;
}

.modal-footer{
  padding:12px 20px;
  border-top:1px solid var(--gray-100);
  display:flex;
  justify-content:flex-end;
  gap:8px;
}

/* ── FORM FIELDS ── */
.field{
  margin-bottom:14px;
}

.field label{
  display:block;
  font-size:12px;
  font-weight:600;
  color:var(--gray-600);
  margin-bottom:5px;
}

.field input,.field select{
  width:100%;
  padding:9px 11px;
  border:1px solid var(--gray-200);
  border-radius:var(--radius);
  font-size:13px;
  font-family:'Inter',sans-serif;
  color:var(--gray-800);
  outline:none;
  transition:border-color .15s;
}

.field input:focus,.field select:focus{
  border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(27,88,134,0.10);
}

.field input[readonly]{
  background:var(--gray-50);
  color:var(--gray-400);
}

.field-row{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:12px;
}

/* ── STATUS BADGE ── */
.badge{
  display:inline-block;
  padding:2px 9px;
  border-radius:20px;
  font-size:11.5px;
  font-weight:600;
}

.badge-pending{
  background:#fef9c3;
  color:#854d0e;
}

.badge-approved{
  background:#dcfce7;
  color:#15803d;
}

.badge-rejected{
  background:#fee2e2;
  color:var(--red);
}

/* ── PAGINATION ── */
.page-btn{
  width:30px;
  height:30px;
  border-radius:6px;
  border:1.5px solid var(--gray-200);
  background:var(--white);
  font-size:13px;
  font-family:'Plus Jakarta Sans',sans-serif;
  color:var(--gray-600);
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  transition:all .15s;
  flex-shrink:0;
}

.page-btn:hover{
  border-color:var(--blue);
  color:var(--blue);
}

.page-btn.active{
  background:var(--blue);
  border-color:var(--blue);
  color:#fff;
  font-weight:700;
}

@media(max-width:900px){
  .home-grid{
    grid-template-columns:1fr;
  }}

@media(max-width:640px){
  nav{
    padding:0 12px;
  }
  .nav-brand{
    font-size:13px;
  }
  .nav-links a{
    padding:4px 7px;
    font-size:11.5px;
  }}
  
</style>
</head>
<body>

<!-- ══════════════ NAV ══════════════ -->
<nav>
  <div class="nav-brand">CCS Admin Panel</div>
  <div class="nav-links">
    <a href="?page=home" class="<?= $page==='home'?'active':'' ?>">Home</a>
    <a href="javascript:void(0)" onclick="openModal('searchModal')" class="nav-search-link">Search</a>
    <a href="?page=students" class="<?= $page==='students'?'active':'' ?>">Students</a>
    <a href="javascript:void(0)" onclick="openBlankSitin()" class="nav-sitin-link">Sit-in</a>
    <a href="?page=records" class="<?= $page==='records'?'active':'' ?>">View History</a>
    <a href="?page=reports" class="<?= $page==='reports'?'active':'' ?>">Reports</a>
    <a href="?page=analytics" class="<?= $page==='analytics'?'active':'' ?>">Analytics</a>
    <a href="?page=software" class="<?= $page==='software'?'active':'' ?>">Lab Software</a>
    <a href="?page=testimonials" class="<?= $page==='testimonials'?'active':'' ?>">Testimonials</a>
    <a href="?page=reservation" class="<?= $page==='reservation'?'active':'' ?>">Reservation</a>
    
    <!-- Logout button - POST method for security -->
    <form method="POST" style="display:inline; margin-left:8px;">
      <button type="submit" name="admin_logout" class="btn-logout-nav" 
              onclick="return confirm('Are you sure you want to logout?')"
              style="background:#e8b800; color:#1a1a00; border:none; padding:4px 12px; border-radius:4px; cursor:pointer; font-weight:700; font-size:11.5px;">
         Logout
      </button>
    </form>
    
    <!-- Alternative GET logout (commented, using POST is more secure)
    <a href="?logout=true" onclick="return confirm('Logout?')" class="btn-logout-nav">Logout</a>
    -->
  </div>
</nav>

<!-- ══════════════ BODY ══════════════ -->
<div class="page-body">

<?php if ($flash_msg): ?>
  <div class="flash"><?= $flash_msg ?></div>
<?php endif; ?>

<!-- ════════════ HOME ════════════ -->
<div id="page-home" class="page-section <?= $page==='home'?'active':'' ?>">
  <div class="home-grid">

    <!-- LEFT: Stats + Pie Chart -->
    <div>
      <div class="card">
        <div class="card-head">
          <h2>
            <svg style="width:14px;height:14px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;vertical-align:middle;margin-right:5px;" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Statistics
          </h2>
        </div>

        <!-- Inline label style matching reference image -->
        <div style="padding:16px 18px;display:flex;flex-direction:column;gap:8px;border-bottom:1px solid var(--gray-100);">
          <div style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--gray-800);">
            <div class="stat-icon-box stat-icon-blue" style="width:30px;height:30px;flex-shrink:0;">
              <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <span><strong>Students Registered:</strong> <?= $total_students ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--gray-800);">
            <div class="stat-icon-box stat-icon-green" style="width:30px;height:30px;flex-shrink:0;">
              <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            </div>
            <span><strong>Currently Sit-in:</strong> <?= $currently_sitin ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--gray-800);">
            <div class="stat-icon-box stat-icon-orange" style="width:30px;height:30px;flex-shrink:0;">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span><strong>Total Sit-in:</strong> <?= $total_sitin ?></span>
          </div>
        </div>

        <div class="chart-wrap">
          <canvas id="purposeChart"></canvas>
        </div>
      </div>
    </div>

    <!-- RIGHT: Announcement -->
    <div>
      <div class="card">
        <div class="card-head">
          <h2>
            <svg style="width:15px;height:15px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;vertical-align:middle;margin-right:6px;" viewBox="0 0 24 24"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
            Announcement
          </h2>
        </div>
        <div class="ann-form">
          <form method="POST">
            <textarea name="content" placeholder="New Announcement"></textarea>
            <button type="submit" name="add_announcement" class="btn-submit">Post Announcement</button>
          </form>
        </div>
        <div class="ann-posted-title">Posted Announcement</div>
        <?php foreach ($announcements as $ann): ?>
        <div class="ann-item">
          <div class="ann-meta">
            <?= htmlspecialchars($ann['admin_name']) ?> | <?= date('Y-M-d', strtotime($ann['created_at'])) ?>
            <form method="POST" style="display:inline;float:right;">
              <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>"/>
              <button type="submit" name="delete_announcement" class="btn btn-sm btn-red" onclick="return confirm('Delete?')">✕</button>
            </form>
          </div>
          <?php if ($ann['content']): ?>
            <div class="ann-content"><?= htmlspecialchars($ann['content']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$announcements): ?>
          <div class="ann-item"><span style="color:var(--gray-400);font-size:13px;">No announcements yet.</span></div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- ════════════ STUDENTS ════════════ -->
<div id="page-students" class="page-section <?= $page==='students'?'active':'' ?>">
  <div class="page-title">Students Information</div>
  <div class="toolbar">
    <button class="btn btn-blue" onclick="openModal('addStudentModal')">Add Students</button>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Reset ALL student sessions to 30?')">
      <button type="submit" name="reset_all_sessions" class="btn btn-red">Reset All Session</button>
    </form>
    <div class="toolbar-right">
      <select class="entries-select" onchange="setEntries(this.value)">
        <option>10</option><option>25</option><option>50</option><option>100</option>
      </select>
      <span style="font-size:13px;color:var(--gray-600);">entries per page</span>
      <span style="font-size:13px;color:var(--gray-600);margin-left:12px;">Search:</span>
      <input type="text" class="search-input" id="studentSearch" oninput="filterTable('studentTable',this.value)" placeholder=""/>
    </div>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table id="studentTable">
        <thead>
          <tr>
            <th class="sortable">ID Number</th>
            <th class="sortable">Name</th>
            <th class="sortable">Year Level</th>
            <th class="sortable">Course</th>
            <th class="sortable">Remaining Session</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($students): foreach ($students as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['id_number']) ?></td>
            <td><?= htmlspecialchars($s['firstname'].' '.$s['middlename'].' '.$s['lastname']) ?></td>
            <td><?= htmlspecialchars($s['year_level']) ?></td>
            <td><?= htmlspecialchars($s['course']) ?></td>
            <td><?= htmlspecialchars($s['session']) ?></td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
              <button class="btn btn-blue btn-sm"
                onclick="openEditStudent(<?= $s['id'] ?>,'<?= addslashes($s['id_number']) ?>','<?= addslashes($s['firstname']) ?>','<?= addslashes($s['middlename']) ?>','<?= addslashes($s['lastname']) ?>','<?= addslashes($s['course']) ?>','<?= $s['year_level'] ?>','<?= addslashes($s['email']) ?>','<?= (int)$s['session'] ?>')">Edit</button>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="student_id" value="<?= $s['id'] ?>"/>
                <button type="submit" name="delete_student" class="btn btn-red btn-sm" onclick="return confirm('Delete this student?')">Delete</button>
              </form>
             </td>
           </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" class="no-data">No students registered yet.</td></tr>
          <?php endif; ?>
        </tbody>
       </table>
    </div>
  </div>
</div>

<!-- ════════════ SIT-IN ════════════ -->
<div id="page-sitin" class="page-section <?= $page==='sitin'?'active':'' ?>">
  <div class="page-title">Current Sit in</div>
  <div class="toolbar">
    <button class="btn btn-blue" onclick="openBlankSitin()">
      <svg style="width:14px;height:14px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      Sit In Student
    </button>
    <div class="toolbar-right">
      <select class="entries-select"><option>10</option><option>25</option><option>50</option></select>
      <span style="font-size:13px;color:var(--gray-600);">entries per page</span>
      <span style="font-size:13px;color:var(--gray-600);margin-left:12px;">Search:</span>
      <input type="text" class="search-input" oninput="filterTable('sitinTable',this.value)" placeholder=""/>
    </div>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table id="sitinTable">
        <thead>
          <tr>
            <th class="sortable">ID Number</th>
            <th class="sortable">Name</th>
            <th class="sortable">Purpose</th>
            <th class="sortable">Lab</th>
            <th class="sortable">Login Time</th>
            <th class="sortable">Remaining Session</th>
            <th class="sortable">Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($current_sitin): foreach ($current_sitin as $si):
            // Get fresh session count directly from students table
            $stuRow = null;
            if ($si['student_id'] > 0) {
                $stuStmt = $pdo->prepare("SELECT id, session FROM students WHERE id_number = ? LIMIT 1");
                $stuStmt->execute([$si['id_number']]);
                $stuRow = $stuStmt->fetch();
            }
            $sessNum   = $stuRow ? (int)$stuRow['session'] : null;
            $stuDbId   = $stuRow ? (int)$stuRow['id'] : 0;
            $sessColor = $sessNum !== null ? ($sessNum <= 5 ? '#dc2626' : ($sessNum <= 10 ? '#ea580c' : '#16a34a')) : '';
          ?>
          <tr>
            <td><?= htmlspecialchars($si['id_number']) ?></td>
            <td><?= htmlspecialchars($si['fullname']) ?></td>
            <td><?= htmlspecialchars($si['sit_purpose']) ?></td>
            <td><?= htmlspecialchars($si['laboratory']) ?></td>
            <td><?= htmlspecialchars($si['login_time'] ?? '—') ?></td>
            <td>
              <?php if ($sessNum !== null): ?>
                <span style="font-weight:700;color:<?= $sessColor ?>;"><?= $sessNum ?></span>
                <span style="font-size:11px;color:var(--gray-400);"> / 30</span>
              <?php else: ?>
                <span style="color:var(--gray-400);font-size:12px;">Walk-in</span>
              <?php endif; ?>
             </td>
            <td><span class="badge badge-approved">Active</span></td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php if ($stuDbId > 0): ?>
                <button class="btn btn-blue btn-sm"
                  onclick="openEditSession(<?= $stuDbId ?>,'<?= addslashes($si['fullname']) ?>','<?= $sessNum ?>')">
                  Edit Session
                </button>
              <?php endif; ?>
              <button class="btn btn-sm" style="background:#7c3aed;color:#fff;"
                onclick="openEditSitin(<?= $si['id'] ?>,'<?= addslashes($si['sit_purpose']) ?>','<?= addslashes($si['laboratory']) ?>','<?= $si['login_time'] ?>','<?= $si['logout_time'] ?>','<?= $si['date'] ?>')">
                Edit Record
              </button>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="sitin_id" value="<?= $si['id'] ?>"/>
                <button type="submit" name="logout_sitin" class="btn btn-red btn-sm"
                  onclick="return confirm('Log out this student?')">Log Out</button>
              </form>
             </td>
           </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="no-data">No data available</td></tr>
          <?php endif; ?>
        </tbody>
       </table>
    </div>
    <div style="padding:10px 14px;font-size:12.5px;color:var(--gray-400);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <span>Showing 1 to <?= count($current_sitin) ?> of <?= count($current_sitin) ?> entr<?= count($current_sitin)===1?'y':'ies' ?></span>
      <span style="display:flex;align-items:center;gap:12px;font-size:12px;">
        <span><span style="color:#16a34a;font-weight:700;">●</span> &gt;10 sessions</span>
        <span><span style="color:#ea580c;font-weight:700;">●</span> 6–10 sessions</span>
        <span><span style="color:#dc2626;font-weight:700;">●</span> ≤5 sessions</span>
      </span>
    </div>
  </div>
</div>

<!-- ════════════ RECORDS ════════════ -->
<div id="page-records" class="page-section <?= $page==='records'?'active':'' ?>">
  <div class="page-title">Current Sit in</div>
  <div class="toolbar">
    <div style="display:flex;align-items:center;gap:8px;">
      <select class="entries-select" id="recordsEntries" onchange="paginateRecords()">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
      <span style="font-size:13px;color:var(--gray-600);">entries per page</span>
    </div>
    <div class="toolbar-right">
      <span style="font-size:13px;color:var(--gray-600);">Search:</span>
      <input type="text" class="search-input" id="recordsSearch" oninput="filterRecords(this.value)" placeholder=""/>
    </div>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table id="recordsTable">
        <thead>
          <tr>
            <th class="sortable">Sit ID Number</th>
            <th class="sortable">ID Number</th>
            <th class="sortable">Name</th>
            <th class="sortable">Purpose</th>
            <th class="sortable">Sit Lab</th>
            <th class="sortable">Session</th>
            <th class="sortable">Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="recordsBody">
          <?php if ($all_sitin): foreach ($all_sitin as $r):
            $isActive = empty($r['logout_time']);
          ?>
          <tr>
            <td><?= htmlspecialchars($r['id']) ?></td>
            <td><?= htmlspecialchars($r['id_number']) ?></td>
            <td><?= htmlspecialchars($r['fullname']) ?></td>
            <td><?= htmlspecialchars($r['sit_purpose']) ?></td>
            <td><?= htmlspecialchars($r['laboratory']) ?></td>
            <td><?= htmlspecialchars($r['login_time'] ?? '—') ?></td>
            <td>
              <?php if ($isActive): ?>
                <span class="badge badge-approved">Active</span>
              <?php else: ?>
                <span class="badge" style="background:#f1f5f9;color:#64748b;">Done</span>
              <?php endif; ?>
             </td>
            <td>
              <?php if ($isActive): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="sitin_id" value="<?= $r['id'] ?>"/>
                  <button type="submit" name="logout_sitin" class="btn btn-red btn-sm"
                    onclick="return confirm('Log out this student?')">Log Out</button>
                </form>
              <?php else: ?>
                <span style="font-size:12px;color:var(--gray-400);">—</span>
              <?php endif; ?>
             </td>
           </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="no-data">No data available</td></tr>
          <?php endif; ?>
        </tbody>
       </table>
    </div>
    <div style="padding:12px 16px;border-top:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <span style="font-size:12.5px;color:var(--gray-400);" id="recordsInfo"></span>
      <div style="display:flex;align-items:center;gap:4px;">
        <button class="page-btn" onclick="goRecordsPage('first')">«</button>
        <button class="page-btn" onclick="goRecordsPage('prev')">‹</button>
        <span id="recordsPageBtns" style="display:flex;gap:4px;"></span>
        <button class="page-btn" onclick="goRecordsPage('next')">›</button>
        <button class="page-btn" onclick="goRecordsPage('last')">»</button>
      </div>
    </div>
  </div>
</div>

<!-- ════════════ REPORTS ════════════ -->
<div id="page-reports" class="page-section <?= $page==='reports'?'active':'' ?>">
  <div class="page-title">Sit-in Reports</div>

  <!-- Export toolbar -->
  <div class="card" style="margin-bottom:18px;">
    <div class="card-head"><h2>Export Report</h2></div>
    <div class="card-body">
      <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="field" style="margin:0;">
          <label style="font-size:12px;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">From Date</label>
          <input type="date" name="report_from" style="padding:7px 11px;border:1px solid var(--gray-200);border-radius:var(--radius);font-family:inherit;font-size:13px;"/>
        </div>
        <div class="field" style="margin:0;">
          <label style="font-size:12px;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">To Date</label>
          <input type="date" name="report_to" style="padding:7px 11px;border:1px solid var(--gray-200);border-radius:var(--radius);font-family:inherit;font-size:13px;"/>
        </div>
        <button type="submit" name="export_report" value="1" onclick="document.querySelector('[name=export_format]').value='csv'" class="btn btn-green">
          ⬇ Export CSV
        </button>
        <button type="submit" name="export_report" value="1" onclick="document.querySelector('[name=export_format]').value='pdf';this.form.target='_blank';" class="btn btn-blue">
          🖨 Print PDF
        </button>
        <input type="hidden" name="export_format" value="csv"/>
      </form>
    </div>
  </div>

  <div class="home-grid">
    <div class="card">
      <div class="card-head"><h2>Purpose Breakdown</h2></div>
      <div class="chart-wrap"><canvas id="reportsChart" style="max-width:300px;"></canvas></div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Summary</h2></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Purpose</th><th>Count</th></tr></thead>
          <tbody>
            <?php foreach ($purpose_rows as $p): ?>
            <tr><td><?= htmlspecialchars($p['sit_purpose']) ?></td><td><?= $p['cnt'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$purpose_rows): ?>
            <tr><td colspan="2" class="no-data">No data yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ════════════ ANALYTICS ════════════ -->
<div id="page-analytics" class="page-section <?= $page==='analytics'?'active':'' ?>">
  <div class="page-title">Analytics</div>
  <div class="home-grid">
    <div class="card">
      <div class="card-head"><h2>Daily Sit-ins (Last 7 Days)</h2></div>
      <div class="chart-wrap"><canvas id="analyticsBarChart" style="max-width:400px;max-height:250px;"></canvas></div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Purpose Breakdown</h2></div>
      <div class="chart-wrap"><canvas id="analyticsPieChart" style="max-width:280px;"></canvas></div>
    </div>
  </div>
  <div class="home-grid">
    <div class="card">
      <div class="card-head"><h2>Top Labs by Usage</h2></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Laboratory</th><th>Total Sessions</th></tr></thead>
          <tbody>
            <?php
            try {
              $lab_stats = $pdo->query("SELECT laboratory, COUNT(*) as cnt FROM sit_in_history GROUP BY laboratory ORDER BY cnt DESC LIMIT 10")->fetchAll();
              foreach ($lab_stats as $ls): ?>
              <tr><td><?= htmlspecialchars($ls['laboratory']) ?></td><td><?= $ls['cnt'] ?></td></tr>
            <?php endforeach;
            if (!$lab_stats): ?><tr><td colspan="2" class="no-data">No data yet.</td></tr><?php endif;
            } catch(Exception $e) {}
            ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Overview</h2></div>
      <div style="padding:16px;display:flex;flex-direction:column;gap:12px;">
        <?php
        $avg_session = $pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, login_time, logout_time)) FROM sit_in_history WHERE logout_time IS NOT NULL")->fetchColumn();
        $avg_m = $avg_session ? round($avg_session) : 0;
        $avg_display = ($avg_m >= 60) ? floor($avg_m/60).'h '.($avg_m%60).'m' : $avg_m.'m';
        ?>
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--gray-100);padding-bottom:10px;"><span style="font-size:13px;color:var(--gray-600);">Total Registered Students</span><strong><?= $total_students ?></strong></div>
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--gray-100);padding-bottom:10px;"><span style="font-size:13px;color:var(--gray-600);">Total Sit-in Records</span><strong><?= $total_sitin ?></strong></div>
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--gray-100);padding-bottom:10px;"><span style="font-size:13px;color:var(--gray-600);">Currently Sitting In</span><strong style="color:#16a34a;"><?= $currently_sitin ?></strong></div>
        <div style="display:flex;justify-content:space-between;"><span style="font-size:13px;color:var(--gray-600);">Avg. Session Duration</span><strong><?= $avg_display ?></strong></div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════ LAB SOFTWARE ════════════ -->
<div id="page-software" class="page-section <?= $page==='software'?'active':'' ?>">
  <div class="page-title">Lab Software Management</div>
  <div class="home-grid">
    <div class="card">
      <div class="card-head"><h2>Add Software</h2></div>
      <div class="card-body">
        <form method="POST">
          <div class="field">
            <label>Laboratory</label>
            <select name="sw_lab" required style="width:100%;padding:8px 11px;border:1px solid var(--gray-200);border-radius:var(--radius);font-family:inherit;font-size:13px;">
              <option value="">— Select Lab —</option>
              <?php foreach (['Lab 517','Lab 524','Lab 526','Lab 528','Lab 530','Lab 542','Lab 544'] as $l): ?>
              <option value="<?= $l ?>"><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Software Name</label>
            <input type="text" name="sw_name" placeholder="e.g. Microsoft Visual Studio" required/>
          </div>
          <div class="field">
            <label>Category</label>
            <select name="sw_category" style="width:100%;padding:8px 11px;border:1px solid var(--gray-200);border-radius:var(--radius);font-family:inherit;font-size:13px;">
              <option value="General">General</option>
              <option value="Programming IDE">Programming IDE</option>
              <option value="Office">Office</option>
              <option value="Graphics & Design">Graphics & Design</option>
              <option value="Database">Database</option>
              <option value="Networking">Networking</option>
              <option value="Security">Security</option>
              <option value="Multimedia">Multimedia</option>
            </select>
          </div>
          <button type="submit" name="add_software" class="btn btn-blue" style="width:100%;">Add Software</button>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Installed Software List</h2></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Lab</th><th>Category</th><th>Software</th><th>Action</th></tr></thead>
          <tbody>
            <?php if ($software_list): foreach ($software_list as $sw): ?>
            <tr>
              <td><?= htmlspecialchars($sw['lab_name']) ?></td>
              <td style="color:var(--blue);font-size:12px;font-weight:600;"><?= htmlspecialchars($sw['category']) ?></td>
              <td><?= htmlspecialchars($sw['software']) ?></td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="sw_id" value="<?= $sw['id'] ?>"/>
                  <button type="submit" name="delete_software" class="btn btn-red btn-sm" onclick="return confirm('Remove this software?')">✕</button>
                </form>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="no-data">No software added yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ════════════ TESTIMONIALS ════════════ -->
<div id="page-testimonials" class="page-section <?= $page==='testimonials'?'active':'' ?>">
  <div class="page-title">Student Testimonials</div>
  <?php
  try { $testimonials_admin = $pdo->query("SELECT * FROM testimonials ORDER BY created_at DESC")->fetchAll(); }
  catch(Exception $e) { $testimonials_admin = []; }
  ?>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Student</th><th>Course</th><th>Rating</th><th>Message</th><th>Date</th></tr></thead>
        <tbody>
          <?php if ($testimonials_admin): $i=0; foreach ($testimonials_admin as $t): $i++; ?>
          <tr>
            <td><?= $i ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($t['fullname']) ?></td>
            <td><?= htmlspecialchars($t['course']) ?></td>
            <td style="color:#f59e0b;"><?= str_repeat('★', (int)$t['rating']) ?></td>
            <td style="max-width:300px;font-size:12.5px;color:var(--gray-600);"><?= htmlspecialchars($t['message']) ?></td>
            <td style="font-size:12px;color:var(--gray-400);"><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" class="no-data">No testimonials yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ════════════ RESERVATION ════════════ -->
<div id="page-reservation" class="page-section <?= $page==='reservation'?'active':'' ?>">

<?php
  $pending_reservations  = array_filter($reservations, fn($r) => $r['status'] === 'pending');
  $approved_reservations = array_filter($reservations, fn($r) => $r['status'] === 'approved');
  $rejected_reservations = array_filter($reservations, fn($r) => $r['status'] === 'rejected');

  $lab_activity = [];
  try {
    $lab_rows = $pdo->query("SELECT laboratory, COUNT(*) as active FROM sit_in_history WHERE logout_time IS NULL AND date = CURDATE() GROUP BY laboratory")->fetchAll();
    foreach ($lab_rows as $lr) $lab_activity[$lr['laboratory']] = (int)$lr['active'];
  } catch(Exception $e) {}

  $lab_statuses = [];
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lab_status (lab_name VARCHAR(100) PRIMARY KEY, is_active TINYINT(1) DEFAULT 1, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    $ls_rows = $pdo->query("SELECT lab_name, is_active FROM lab_status")->fetchAll();
    foreach ($ls_rows as $ls) $lab_statuses[$ls['lab_name']] = (bool)$ls['is_active'];
  } catch(Exception $e) {}

  $all_pc_labs   = ['Lab 524','Lab 526','Lab 528','Lab 530','Lab 542','Lab 544'];
  $total_labs    = count($all_pc_labs);
  $slots_per_lab = 50;
  $system_log    = array_slice($reservations, 0, 20);

  // Per-lab slot capacity stored in system_settings
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
  } catch(Exception $e){}
  $lab_slots = [];
  foreach ($all_pc_labs as $ln) {
    $key = 'slots_' . str_replace(' ','_',$ln);
    $sv = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key=?");
    $sv->execute([$key]);
    $lab_slots[$ln] = (int)($sv->fetchColumn() ?: 50);
  }

  // Fetch student profile pictures for system log avatars
  $student_profiles = [];
  try {
    $sp = $pdo->query("SELECT id_number, profile_picture FROM students WHERE profile_picture IS NOT NULL AND profile_picture != ''");
    foreach ($sp->fetchAll() as $row) $student_profiles[$row['id_number']] = $row['profile_picture'];
  } catch(Exception $e){}

  // Handle POST actions inside reservation page
  // (All POST handlers moved to top of file to prevent headers-already-sent errors)
?>

<style>
/* ── PAGE HEADER ── */
.resv-page-header{margin-bottom:0;}
.resv-page-eyebrow{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.12em;color:var(--blue);display:flex;align-items:center;gap:6px;margin-bottom:4px;}
.resv-page-title{font-size:22px;font-weight:800;color:var(--blue-dk);letter-spacing:-0.02em;line-height:1.15;}
.resv-refresh-btn{padding:7px 16px;border:1.5px solid var(--gray-200);border-radius:7px;background:#fff;color:var(--gray-600);font-size:12px;font-weight:600;font-family:inherit;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all .15s;white-space:nowrap;}
.resv-refresh-btn:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-lt);}

/* ── MAIN TABLE ── */
.resv-table{width:100%;border-collapse:separate;border-spacing:0;border:1.5px solid var(--gray-200);border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 2px 12px rgba(27,88,134,0.08);}

/* Header row */
.resv-table thead tr th{
  background:var(--blue-dk);
  color:#fff;
  padding:13px 18px;
  font-size:11px;
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:0.1em;
  border-right:1px solid rgba(255,255,255,0.12);
  vertical-align:middle;
}
.resv-table thead tr th:last-child{border-right:none;}

.th-inner{display:flex;align-items:center;gap:8px;}
.th-badge{background:rgba(255,255,255,0.18);color:#fff;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;margin-left:auto;white-space:nowrap;}
.th-badge-yellow{background:rgba(254,249,195,0.25);color:#fef08a;}
.th-badge-realtime{background:rgba(137,207,241,0.22);color:#89CFF1;font-size:9.5px;}

/* Body cells */
.resv-table tbody tr td{
  vertical-align:top;
  padding:16px;
  border-right:1.5px solid var(--gray-100);
  border-top:1.5px solid var(--gray-100);
  background:#fff;
  width:33.333%;
}
.resv-table tbody tr:first-child td{border-top:none;}
.resv-table tbody tr td:last-child{border-right:none;}

/* The single body row fills height naturally */
.resv-table tbody tr td .col-scroll{
  max-height:640px;
  overflow-y:auto;
  padding-right:3px;
}
.resv-table tbody tr td .col-scroll::-webkit-scrollbar{width:4px;}
.resv-table tbody tr td .col-scroll::-webkit-scrollbar-track{background:transparent;}
.resv-table tbody tr td .col-scroll::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:4px;}

/* ── CCS LABS BANNER ── */
.labs-total-banner{background:var(--blue-dk);border-radius:9px;padding:13px 15px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;}
.labs-total-banner-left{display:flex;align-items:center;gap:11px;}
.labs-total-banner-icon{width:36px;height:36px;background:rgba(137,207,241,0.18);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.labs-total-banner-icon svg{width:18px;height:18px;fill:none;stroke:#89CFF1;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.labs-total-banner-eyebrow{font-size:9px;font-weight:700;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:0.1em;}
.labs-total-banner-count{font-size:17px;font-weight:800;color:#fff;line-height:1.15;}
.labs-total-banner-sub{font-size:9.5px;color:rgba(255,255,255,0.4);margin-top:2px;}
.labs-total-banner-num{font-size:34px;font-weight:900;color:rgba(255,255,255,0.12);letter-spacing:-2px;}

/* ── LAB CARDS ── */
.lab-card{background:#fff;border:1px solid var(--gray-200);border-radius:9px;margin-bottom:7px;position:relative;overflow:hidden;transition:border-color .15s,box-shadow .15s;}
.lab-card:hover{border-color:var(--blue-bd);box-shadow:0 2px 8px rgba(27,88,134,0.09);}
.lab-card-accent{position:absolute;left:0;top:0;bottom:0;width:4px;}
.lab-card-accent-active{background:#16a34a;}
.lab-card-accent-inactive{background:#e5e7eb;}
.lab-card-inner{padding:11px 13px 11px 17px;}
.lab-card-row1{display:flex;align-items:center;justify-content:space-between;}
.lab-card-name{font-size:13px;font-weight:800;color:var(--gray-800);}
.lab-card-floor{font-size:9px;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.07em;margin-top:1px;}
.lab-slots-row{display:flex;align-items:center;justify-content:space-between;margin-top:7px;}
.lab-slots-text{font-size:10.5px;color:#6b7280;}
.lab-slots-num{font-size:12px;font-weight:800;color:var(--gray-800);}
.lab-slots-num-ok{color:var(--gray-800);}
.lab-slots-num-warn{color:#ea580c;}
.lab-slots-num-full{color:#dc2626;}
.lab-slot-bar{display:none;}
.lab-slot-bar-fill{display:none;}
/* Editable slot capacity input */
.lab-slots-editable{
  width:36px;font-size:12px;font-weight:800;color:var(--blue-dk);
  border:none;border-bottom:1.5px dashed var(--blue-bd);
  background:transparent;text-align:center;font-family:inherit;
  padding:0 1px;cursor:text;outline:none;
  -moz-appearance:textfield;
}
.lab-slots-editable::-webkit-outer-spin-button,
.lab-slots-editable::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}
.lab-slots-editable:focus{border-bottom-color:var(--blue);background:var(--blue-lt);border-radius:3px;}

/* ── PENDING QUEUE CARDS ── */
.pq-card{background:#fff;border:1px solid var(--gray-200);border-radius:11px;padding:14px;margin-bottom:10px;box-shadow:0 1px 3px rgba(27,88,134,0.05);transition:box-shadow .15s;}
.pq-card:hover{box-shadow:0 4px 14px rgba(27,88,134,0.10);}
.pq-card-header{display:flex;align-items:center;gap:11px;margin-bottom:11px;}
.pq-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--blue-dk));color:#fff;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.pq-name{font-size:13.5px;font-weight:800;color:var(--gray-800);}
.pq-idnum{font-size:11px;color:var(--gray-400);margin-top:2px;}
.pq-details{display:flex;flex-direction:column;gap:5px;margin-bottom:12px;padding:9px 11px;background:var(--gray-50);border-radius:7px;border:1px solid var(--gray-100);}
.pq-detail-row{display:flex;align-items:center;gap:7px;font-size:11.5px;color:var(--gray-600);}
.pq-detail-icon{width:13px;height:13px;flex-shrink:0;opacity:0.55;}
.pq-detail-val{font-weight:600;color:var(--gray-800);}
.pq-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.pq-actions form{margin:0;}
.pq-btn-approve{width:100%;padding:9px 0;border:none;border-radius:8px;background:#16a34a;color:#fff;font-size:12px;font-weight:700;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:background .15s;}
.pq-btn-approve:hover{background:#15803d;}
.pq-btn-decline{width:100%;padding:9px 0;border:1.5px solid #fca5a5;border-radius:8px;background:#fff;color:#dc2626;font-size:12px;font-weight:700;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .15s;}
.pq-btn-decline:hover{background:#fee2e2;}
.pq-empty{text-align:center;padding:52px 16px;color:var(--gray-400);}

/* ── SYSTEM LOG CARDS ── */
.syslog-card{background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:0;margin-bottom:9px;box-shadow:0 1px 3px rgba(27,88,134,0.04);overflow:hidden;transition:border-color .15s,box-shadow .15s;}
.syslog-card:hover{border-color:var(--blue-bd);box-shadow:0 3px 10px rgba(27,88,134,0.08);}
/* Header strip */
.syslog-card-header{display:flex;align-items:center;gap:10px;padding:11px 13px 10px;border-bottom:1px solid var(--gray-100);}
.syslog-avatar{width:36px;height:36px;border-radius:50%;background:var(--blue-lt);border:2px solid var(--blue-bd);color:var(--blue-dk);font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;}
.syslog-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.syslog-name{font-size:12.5px;font-weight:700;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.syslog-idnum{font-size:10.5px;color:var(--gray-400);margin-top:1px;}
.syslog-badge-row{display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-top:3px;}
.syslog-time-area{margin-left:auto;text-align:right;flex-shrink:0;}
.syslog-date-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);}
.syslog-date-val{font-size:12.5px;font-weight:800;color:var(--blue-dk);}
.syslog-time-val{font-size:10px;color:var(--gray-400);margin-top:1px;}
.syslog-pc-tag{font-size:9.5px;color:var(--gray-400);margin-top:2px;}
/* Detail body */
.syslog-body{padding:8px 13px;font-size:11.5px;color:var(--gray-600);border-bottom:1px solid var(--gray-100);background:var(--gray-50);}
.syslog-body strong{color:var(--blue-dk);}
/* Actions footer — perfectly equal two-button grid */
.syslog-actions{display:grid;grid-template-columns:1fr 1fr;gap:0;}
.syslog-actions form{margin:0;}
.syslog-btn-start{
  width:100%;padding:8px 0;
  border:none;border-right:1px solid var(--gray-100);
  border-radius:0 0 0 9px;
  background:var(--blue-dk);color:#fff;
  font-size:11px;font-weight:700;font-family:inherit;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:5px;
  transition:background .15s;
}
.syslog-btn-start:hover{background:var(--blue);}
.syslog-btn-absent{
  width:100%;padding:8px 0;
  border:none;
  border-radius:0 0 9px 0;
  background:#fff;color:#dc2626;
  font-size:11px;font-weight:700;font-family:inherit;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:5px;
  transition:all .15s;
}
.syslog-btn-absent:hover{background:#fee2e2;}
/* Status footer strip — full width, colored background */
.syslog-status-done{
  padding:8px 13px;
  display:flex;align-items:center;gap:5px;
  font-size:11.5px;font-weight:700;
  border-top:1px solid var(--gray-100);
}

/* Load-more link */
.resv-load-more{text-align:center;padding:8px 0 2px;}
.resv-load-more span{font-size:10.5px;color:var(--gray-400);font-weight:700;letter-spacing:0.07em;text-transform:uppercase;cursor:pointer;}
.resv-load-more span:hover{color:var(--blue);}

/* badge colours */
.badge-approved{background:#dcfce7;color:#15803d;}
.badge-rejected{background:#fee2e2;color:#dc2626;}
.badge-pending{background:#fef9c3;color:#854d0e;}
.badge-waiting{background:#fef9c3;color:#854d0e;border:1px solid #fde68a;}
.badge-scheduled{background:var(--blue-lt);color:var(--blue-dk);border:1px solid var(--blue-bd);}
.badge-sm{font-size:9.5px;padding:2px 8px;border-radius:12px;font-weight:700;display:inline-block;letter-spacing:0.03em;}
</style>

<!-- ── PAGE HEADER ── -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
  <div class="resv-page-header" style="margin-bottom:0;">
    <div class="resv-page-eyebrow">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Reservation Management
    </div>
    <div class="resv-page-title">System Controls</div>
  </div>
  <button class="resv-refresh-btn" onclick="location.reload()">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
    Refresh Data
  </button>
</div>

<!-- ── MAIN TABLE ── -->
<table class="resv-table">
  <thead>
    <tr>
      <!-- TH 1: PC Control -->
      <th>
        <div class="th-inner">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          CCS Laboratories
          <span class="th-badge"><?= $total_labs ?> LABS</span>
        </div>
      </th>
      <!-- TH 2: Pending Queue -->
      <th>
        <div class="th-inner">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Pending Queue
          <?php if (count($pending_reservations) > 0): ?>
          <span class="th-badge th-badge-yellow"><?= count($pending_reservations) ?> REQUEST<?= count($pending_reservations)>1?'S':'' ?></span>
          <?php else: ?>
          <span class="th-badge">0 REQUESTS</span>
          <?php endif; ?>
        </div>
      </th>
      <!-- TH 3: System Log -->
      <th>
        <div class="th-inner">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
          System Log
          <span class="th-badge th-badge-realtime">&#9679; REAL-TIME</span>
        </div>
      </th>
    </tr>
  </thead>
  <tbody>
    <tr>

      <!-- ════ TD 1: CCS Laboratories ════ -->
      <td>
        <div class="col-scroll">

          <!-- Lab cards -->
          <?php foreach ($all_pc_labs as $lab):
            $slots_per_lab = $lab_slots[$lab] ?? 50;
            $occupied  = $lab_activity[$lab] ?? 0;
            $available = max(0, $slots_per_lab - $occupied);
            $pct       = $slots_per_lab > 0 ? round(($occupied / $slots_per_lab) * 100) : 0;
            $numClass  = $pct >= 90 ? 'lab-slots-num-full' : ($pct >= 60 ? 'lab-slots-num-warn' : 'lab-slots-num-ok');
            $isActive  = $lab_statuses[$lab] ?? true;
            $accentCls = $isActive ? 'lab-card-accent-active' : 'lab-card-accent-inactive';
          ?>
          <div class="lab-card">
            <div class="lab-card-accent <?= $accentCls ?>"></div>
            <div class="lab-card-inner">
              <div class="lab-card-row1">
                <div>
                  <div class="lab-card-name"><?= htmlspecialchars($lab) ?></div>
                  <div class="lab-card-floor">5TH FLOOR &middot; CCS</div>
                </div>
                <form method="POST" style="margin:0;">
                  <input type="hidden" name="lab_name" value="<?= htmlspecialchars($lab) ?>"/>
                  <input type="hidden" name="new_status" value="<?= $isActive ? '0' : '1' ?>"/>
                  <?php if ($isActive): ?>
                  <button type="submit" name="toggle_lab_status" title="Click to deactivate"
                          onclick="return confirm('Deactivate <?= addslashes($lab) ?>?')"
                          style="width:26px;height:26px;border-radius:50%;background:#16a34a;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                          onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                  </button>
                  <?php else: ?>
                  <button type="submit" name="toggle_lab_status" title="Click to activate"
                          onclick="return confirm('Activate <?= addslashes($lab) ?>?')"
                          style="width:26px;height:26px;border-radius:50%;background:#fee2e2;border:2px solid #fca5a5;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                          onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="3.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  </button>
                  <?php endif; ?>
                </form>
              </div>
              <div class="lab-slots-row">
                <span class="lab-slots-text">Available Slots</span>
                <span class="lab-slots-num <?= $numClass ?>">
                  <?= $available ?>
                  <span style="font-size:9.5px;font-weight:500;color:#9ca3af;">OF</span>
                  <form method="POST" style="display:inline;margin:0;">
                    <input type="hidden" name="lab_name" value="<?= htmlspecialchars($lab) ?>"/>
                    <input type="hidden" name="update_lab_slots" value="1"/>
                    <input type="number" name="new_slots" value="<?= $slots_per_lab ?>"
                           min="1" max="500" class="lab-slots-editable"
                           title="Click to edit total slots"
                           onblur="if(this.value!=<?= $slots_per_lab ?>)this.closest('form').submit();"
                           onkeydown="if(event.key==='Enter'){this.closest('form').submit();event.preventDefault();}"/>
                  </form>
                </span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </td>

      <!-- ════ TD 2: Pending Queue ════ -->
      <td>
        <div class="col-scroll">
          <?php if (count($pending_reservations) > 0):
            foreach ($pending_reservations as $rv): ?>
          <div class="pq-card">
            <div class="pq-card-header">
              <div class="pq-avatar"><?= strtoupper(substr($rv['firstname'],0,1).substr($rv['lastname'],0,1)) ?></div>
              <div style="flex:1;min-width:0;">
                <div class="pq-name"><?= htmlspecialchars($rv['firstname'].' '.$rv['lastname']) ?></div>
                <div class="pq-idnum"><?= htmlspecialchars($rv['id_number']) ?></div>
              </div>
            </div>
            <div class="pq-details">
              <div class="pq-detail-row">
                <svg class="pq-detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/></svg>
                <span><?= htmlspecialchars($rv['laboratory']) ?></span>
                <?php if (!empty($rv['pc_number'])): ?>
                <span style="margin-left:4px;font-size:10.5px;color:#9ca3af;">&middot; PC-<?= htmlspecialchars($rv['pc_number']) ?></span>
                <?php endif; ?>
              </div>
              <div class="pq-detail-row">
                <svg class="pq-detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="pq-detail-val"><?= htmlspecialchars($rv['time_in'] ?? '—') ?></span>
                <span style="color:#9ca3af;font-size:10px;">&nbsp;&middot;&nbsp;</span>
                <svg class="pq-detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span class="pq-detail-val"><?= htmlspecialchars($rv['date'] ?? '—') ?></span>
              </div>
              <?php if (!empty($rv['purpose'])): ?>
              <div class="pq-detail-row">
                <svg class="pq-detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($rv['purpose']) ?></span>
              </div>
              <?php endif; ?>
            </div>
            <div class="pq-actions">
              <form method="POST">
                <input type="hidden" name="reservation_id" value="<?= $rv['id'] ?>"/>
                <button type="submit" name="approve_reservation" class="pq-btn-approve">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                  APPROVE
                </button>
              </form>
              <form method="POST">
                <input type="hidden" name="reservation_id" value="<?= $rv['id'] ?>"/>
                <button type="submit" name="reject_reservation" class="pq-btn-decline">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  DECLINE
                </button>
              </form>
            </div>
          </div>
          <?php endforeach;
          else: ?>
          <div class="pq-empty">
            <div style="font-size:32px;margin-bottom:10px;">📭</div>
            <div style="font-weight:700;color:#6b7280;font-size:13px;">No pending requests</div>
            <div style="font-size:11.5px;margin-top:5px;color:#9ca3af;">All reservations have been processed.</div>
          </div>
          <?php endif; ?>
        </div>
      </td>

      <!-- ════ TD 3: System Log ════ -->
      <td>
        <div class="col-scroll">
          <?php if ($system_log): foreach ($system_log as $rv):
            $isApproved = $rv['status'] === 'approved';
            $isPending  = $rv['status'] === 'pending';
            $isRejected = $rv['status'] === 'rejected';
            $hasArrived = !empty($rv['arrived']) && $rv['arrived'] == 1;
            $isAbsent   = !empty($rv['absent'])  && $rv['absent']  == 1;
            $initials   = strtoupper(substr($rv['firstname'],0,1).substr($rv['lastname'],0,1));
            $slBadgeCls = $isApproved ? 'badge-approved' : ($isRejected ? 'badge-rejected' : 'badge-waiting');
            $slBadgeTxt = $isApproved ? 'APPROVED' : ($isRejected ? 'REJECTED' : 'WAITING');
            $profilePic = $student_profiles[$rv['id_number']] ?? null;
            $dateStr    = $rv['date'] ?? '';
            $timeStr    = $rv['time_in'] ?? '';
          ?>
          <div class="syslog-card">
            <!-- ── Header strip: avatar · name/id/badge · scheduled time ── -->
            <div class="syslog-card-header">
              <div class="syslog-avatar">
                <?php if ($profilePic): ?>
                  <img src="<?= htmlspecialchars($profilePic) ?>" alt="<?= htmlspecialchars($initials) ?>"/>
                <?php else: ?>
                  <?= $initials ?>
                <?php endif; ?>
              </div>
              <div style="flex:1;min-width:0;">
                <div class="syslog-name"><?= htmlspecialchars($rv['firstname'].' '.$rv['lastname']) ?></div>
                <div class="syslog-idnum"><?= htmlspecialchars($rv['id_number']) ?></div>
                <div class="syslog-badge-row">
                  <span class="badge badge-sm <?= $slBadgeCls ?>"><?= $slBadgeTxt ?></span>
                </div>
              </div>
              <div class="syslog-time-area">
                <div class="syslog-date-label">SCHEDULED</div>
                <?php if ($dateStr): ?><div class="syslog-date-val"><?= htmlspecialchars(date('M j', strtotime($dateStr))) ?></div><?php endif; ?>
                <?php if ($timeStr): ?><div class="syslog-time-val"><?= htmlspecialchars($timeStr) ?></div><?php endif; ?>
                <?php if (!empty($rv['pc_number'])): ?><div class="syslog-pc-tag">&#128187; PC-<?= htmlspecialchars($rv['pc_number']) ?></div><?php endif; ?>
              </div>
            </div>
            <!-- ── Detail body ── -->
            <div class="syslog-body">
              Reservation for <strong><?= htmlspecialchars($rv['laboratory']) ?></strong>
              <?php if ($dateStr): ?>&nbsp;&middot;&nbsp;<strong><?= htmlspecialchars(date('M j', strtotime($dateStr))) ?></strong><?php endif; ?>
              <?php if (!empty($rv['purpose'])): ?>&nbsp;&middot;&nbsp;<?= htmlspecialchars($rv['purpose']) ?><?php endif; ?>
            </div>
            <!-- ── Action footer ── -->
            <?php if ($isApproved && !$hasArrived && !$isAbsent): ?>
            <div class="syslog-actions">
              <form method="POST">
                <input type="hidden" name="reservation_id" value="<?= $rv['id'] ?>"/>
                <button type="submit" name="mark_arrived" class="syslog-btn-start">
                  <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                  START SESSION
                </button>
              </form>
              <form method="POST">
                <input type="hidden" name="reservation_id" value="<?= $rv['id'] ?>"/>
                <button type="submit" name="mark_absent" class="syslog-btn-absent" onclick="return confirm('Mark student as absent?')">
                  <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  ABSENT
                </button>
              </form>
            </div>
            <?php elseif ($hasArrived): ?>
            <div class="syslog-status-done" style="color:#16a34a;background:#f0fdf4;">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
              Session Started
            </div>
            <?php elseif ($isAbsent): ?>
            <div class="syslog-status-done" style="color:#dc2626;background:#fff5f5;">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Marked Absent
            </div>
            <?php elseif ($isPending): ?>
            <div class="syslog-status-done" style="color:#854d0e;background:#fefce8;">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Awaiting Approval
            </div>
            <?php elseif ($isRejected): ?>
            <div class="syslog-status-done" style="color:#dc2626;background:#fff5f5;">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Reservation Rejected
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach;
          else: ?>
          <div style="text-align:center;padding:40px 16px;color:#9ca3af;font-size:13px;">No reservation logs yet.</div>
          <?php endif; ?>

          <?php if (count($reservations) > 20): ?>
          <div class="resv-load-more"><span>&#8212; Load More History &#8212;</span></div>
          <?php endif; ?>
        </div>
      </td>

    </tr>
  </tbody>
</table>
</div><!-- end reservation page -->


</div><!-- end page-body -->

<!-- ══════════════════════════════════════
     FLOATING AI CHAT WIDGET
══════════════════════════════════════ -->

<!-- Toggle Button -->
<button id="aiFab" onclick="aiToggle()" title="AI Assistant"
  style="position:fixed;bottom:24px;right:24px;z-index:9999;
         width:52px;height:52px;border-radius:50%;border:none;
         background:var(--blue-dk);color:#fff;font-size:22px;
         box-shadow:0 4px 18px rgba(0,58,107,0.35);
         cursor:pointer;display:flex;align-items:center;justify-content:center;
         transition:transform .2s,box-shadow .2s;">
  ✦
</button>

<!-- Unread dot -->
<span id="aiDot" style="display:none;position:fixed;bottom:68px;right:24px;z-index:10000;
  width:10px;height:10px;background:#e63946;border-radius:50%;
  border:2px solid #fff;"></span>

<!-- Chat Panel -->
<div id="aiPanel" style="display:none;position:fixed;bottom:88px;right:24px;z-index:9998;
  width:360px;max-width:calc(100vw - 32px);
  background:#fff;border-radius:14px;
  box-shadow:0 8px 40px rgba(0,58,107,0.18);
  flex-direction:column;overflow:hidden;
  animation:aiSlideUp .22s ease;">

  <style>
  @keyframes aiSlideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
  .ai-msg{display:flex;margin-bottom:2px;}
  .ai-msg-user{justify-content:flex-end;}
  .ai-msg-bot{justify-content:flex-start;}
  .ai-bubble{max-width:82%;padding:9px 13px;border-radius:14px;font-size:13px;line-height:1.55;white-space:pre-wrap;word-break:break-word;}
  .ai-bubble-bot{background:#f0f4f8;color:#1a2e45;border-bottom-left-radius:3px;}
  .ai-bubble-user{background:var(--blue-dk);color:#fff;border-bottom-right-radius:3px;}
  .ai-bubble-typing{background:#f0f4f8;color:#8aaac8;font-style:italic;font-size:12px;border-bottom-left-radius:3px;animation:aiPulse 1.2s ease-in-out infinite;}
  @keyframes aiPulse{0%,100%{opacity:1}50%{opacity:.35}}
  .ai-chip{background:#e8f4fb;color:#003A6B;border:1px solid #89CFF1;border-radius:20px;padding:4px 10px;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;font-family:inherit;white-space:nowrap;}
  .ai-chip:hover{background:#003A6B;color:#fff;}
  #aiInput2:focus{outline:none;border-color:var(--blue)!important;}
  </style>

  <!-- Header -->
  <div style="background:var(--blue-dk);padding:12px 16px;display:flex;align-items:center;gap:9px;flex-shrink:0;">
    <div style="width:30px;height:30px;background:rgba(137,207,241,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;">✦</div>
    <div style="flex:1;min-width:0;">
      <div style="color:#fff;font-weight:700;font-size:13px;">CCS Admin AI</div>
      <div style="color:rgba(255,255,255,0.55);font-size:10.5px;">Ask me anything</div>
    </div>
    <button onclick="aiClearChat()" title="Clear"
      style="background:rgba(255,255,255,0.1);border:none;color:rgba(255,255,255,0.7);font-size:10px;padding:3px 8px;border-radius:4px;cursor:pointer;font-family:inherit;">
      Clear
    </button>
    <button onclick="aiToggle()" title="Close"
      style="background:none;border:none;color:rgba(255,255,255,0.7);font-size:18px;line-height:1;cursor:pointer;padding:0 2px;margin-left:2px;">
      ×
    </button>
  </div>

  <!-- Messages -->
  <div id="aiMessages"
    style="height:320px;overflow-y:auto;padding:14px 14px 8px;display:flex;flex-direction:column;gap:8px;background:#fafcfe;flex-shrink:0;">
    <div class="ai-msg ai-msg-bot">
      <div class="ai-bubble ai-bubble-bot">
        👋 Hi! I'm your CCS Admin AI. Ask me anything — sit-in stats, announcements, student info, or anything else!
      </div>
    </div>
  </div>

  <!-- Quick chips -->
  <div id="aiChips" style="padding:8px 12px;display:flex;gap:6px;flex-wrap:wrap;border-top:1px solid #eef2f7;background:#fff;">
    <button class="ai-chip" onclick="aiSend('How many students are currently sitting in?')">Sit-ins now?</button>
    <button class="ai-chip" onclick="aiSend('Draft a lab silence announcement.')">Draft announcement</button>
    <button class="ai-chip" onclick="aiSend('What are the most common sit-in purposes?')">Top purposes</button>
  </div>

  <!-- Input bar -->
  <div style="padding:10px 12px;border-top:1px solid #eef2f7;background:#fff;display:flex;gap:7px;align-items:flex-end;">
    <textarea id="aiInput2" rows="1" placeholder="Type a message… (Enter to send)"
      style="flex:1;resize:none;border:1px solid #cddaec;border-radius:8px;padding:8px 11px;
             font-family:inherit;font-size:13px;line-height:1.45;max-height:100px;overflow-y:auto;
             background:#fafcfe;transition:border .15s;"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();aiSend();}"
      oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px';"></textarea>
    <button onclick="aiSend()" id="aiSendBtn"
      style="background:var(--blue-dk);color:#fff;border:none;border-radius:8px;
             padding:8px 13px;font-size:18px;line-height:1;cursor:pointer;flex-shrink:0;
             transition:background .15s;"
      onmouseover="this.style.background='#1B5886'" onmouseout="this.style.background='var(--blue-dk)'">
      ➤
    </button>
  </div>

</div>

<!-- ══════════════════════════════════════
     MODALS
══════════════════════════════════════ -->

<!-- SEARCH MODAL -->
<div class="modal-overlay" id="searchModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-head">
      <h3>Search Student</h3>
      <button class="modal-close" onclick="closeModal('searchModal')">×</button>
    </div>
    <div class="modal-body">
      <input type="text" class="search-input" id="globalSearch" placeholder="Search..." style="width:100%;font-size:14px;padding:9px 12px;"
             oninput="globalSearchFn(this.value)"/>
      <div id="searchResults" style="margin-top:14px;max-height:260px;overflow-y:auto;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-blue" onclick="runGlobalSearch()">Search</button>
    </div>
  </div>
</div>

<!-- SITIN SEARCH MODAL — search student then open sit-in form -->
<div class="modal-overlay" id="sitinSearchModal">
  <div class="modal" style="max-width:420px;">
    <div class="modal-head">
      <h3>Search Student</h3>
      <button class="modal-close" onclick="closeModal('sitinSearchModal')">×</button>
    </div>
    <div class="modal-body">
      <input type="text" id="sitinSearchInput" class="search-input"
             placeholder="Search by ID or name..."
             oninput="sitinSearchFn(this.value)"
             style="width:100%;padding:9px 12px;font-size:14px;border:1.5px solid var(--gray-200);border-radius:var(--radius);outline:none;font-family:inherit;"
             onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--gray-200)'"/>
      <div id="sitinSearchResults" style="margin-top:12px;max-height:280px;overflow-y:auto;"></div>
    </div>
  </div>
</div>

<!-- SIT-IN FORM MODAL — admin fills in manually, auto-fills if student is registered -->
<div class="modal-overlay" id="sitinModal">
  <div class="modal" style="max-width:480px;">
    <div class="modal-head" style="border-bottom:1px solid #e8f0f7;">
      <h3 style="font-size:16px;font-weight:700;color:#1a2e45;">Sit In Form</h3>
      <button class="modal-close" onclick="closeModal('sitinModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body" style="padding:20px 24px;">
        <input type="hidden" name="student_id" id="sitin_student_id" value="0"/>
        <input type="hidden" name="current_page" id="sitin_current_page" value="home"/>

        <table style="width:100%;border-collapse:separate;border-spacing:0 10px;">
          <tr>
            <td style="width:42%;font-size:13px;color:#3d607f;font-weight:600;padding-right:14px;white-space:nowrap;">ID Number:</td>
            <td>
              <div style="display:flex;gap:6px;">
                <input type="text" name="id_number" id="sitin_id_number"
                       placeholder="Enter student ID"
                       style="flex:1;padding:8px 11px;border:1px solid #cddaec;border-radius:6px;font-size:13px;font-family:inherit;color:#1a2e45;outline:none;"
                       onfocus="this.style.borderColor='#1B5886'" onblur="this.style.borderColor='#cddaec'"/>
                <button type="button" onclick="lookupStudent()"
                        style="padding:8px 12px;background:#1B5886;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                  Look up
                </button>
              </div>
              <div id="sitin_lookup_msg" style="font-size:11.5px;margin-top:4px;display:none;"></div>
            </td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#3d607f;font-weight:600;padding-right:14px;">Student Name:</td>
            <td><input type="text" name="student_name" id="sitin_name"
                       placeholder="Enter full name"
                       style="width:100%;padding:8px 11px;border:1px solid #cddaec;border-radius:6px;font-size:13px;font-family:inherit;color:#1a2e45;outline:none;"
                       onfocus="this.style.borderColor='#1B5886'" onblur="this.style.borderColor='#cddaec'"/></td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#3d607f;font-weight:600;padding-right:14px;">Purpose:</td>
            <td>
              <select name="purpose" id="sitin_purpose" required
                      style="width:100%;padding:8px 11px;border:1px solid #cddaec;border-radius:6px;font-size:13px;font-family:inherit;color:#1a2e45;outline:none;"
                      onfocus="this.style.borderColor='#1B5886'" onblur="this.style.borderColor='#cddaec'">
                <option value="">— Select Purpose —</option>
                <?php foreach(['C Programming','Java Programming','Python Programming','C# Programming','PHP Programming','Database (MySQL)','Web Development','Data Structures','Computer Networks','Operating Systems','System Administration','Thesis / Capstone','Research','Online Class','Others'] as $p): ?>
                <option value="<?=$p?>"><?=$p?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#3d607f;font-weight:600;padding-right:14px;">Lab:</td>
            <td>
              <select name="lab" id="sitin_lab" required
                      style="width:100%;padding:8px 11px;border:1px solid #cddaec;border-radius:6px;font-size:13px;font-family:inherit;color:#1a2e45;outline:none;"
                      onfocus="this.style.borderColor='#1B5886'" onblur="this.style.borderColor='#cddaec'">
                <option value="">— Select Lab —</option>
                <?php foreach(['Lab 517','Lab 524','Lab 526','Lab 528','Lab 530','Lab 542','Lab 544'] as $l): ?>
                <option value="<?=$l?>"><?=$l?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#3d607f;font-weight:600;padding-right:14px;">Remaining Session:</td>
            <td><input type="text" id="sitin_session" readonly placeholder="Auto-filled for registered students"
                       style="width:100%;padding:8px 11px;border:1px solid #cddaec;border-radius:6px;font-size:13px;background:#f9fafb;font-family:inherit;color:#1a2e45;"/></td>
          </tr>
        </table>
      </div>
      <div class="modal-footer" style="justify-content:flex-end;gap:8px;">
        <button type="button"
                style="padding:8px 20px;border-radius:6px;border:none;background:#6b7280;color:#fff;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;"
                onclick="closeSitinModal()">Close</button>
        <button type="submit" name="do_sitin"
                style="padding:8px 20px;border-radius:6px;border:none;background:#1B5886;color:#fff;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;">Sit In</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD STUDENT MODAL -->
<div class="modal-overlay" id="addStudentModal">
  <div class="modal" style="max-width:520px;">
    <div class="modal-head">
      <h3>Add Student</h3>
      <button class="modal-close" onclick="closeModal('addStudentModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <div class="field-row">
          <div class="field"><label>ID Number *</label><input type="text" name="id_number" required/></div>
          <div class="field"><label>Email *</label><input type="email" name="email" required/></div>
        </div>
        <div class="field-row">
          <div class="field"><label>Last Name *</label><input type="text" name="lastname" required/></div>
          <div class="field"><label>First Name *</label><input type="text" name="firstname" required/></div>
        </div>
        <div class="field"><label>Middle Name</label><input type="text" name="middlename"/></div>
        <div class="field-row">
          <div class="field"><label>Course *</label>
            <select name="course" required>
              <option value="">Select</option>
              <option>BSIT</option><option>BSCS</option><option>BSDA</option><option>ACT</option>
            </select>
          </div>
          <div class="field"><label>Year Level *</label>
            <select name="year_level" required>
              <option value="1">1st Year</option><option value="2">2nd Year</option>
              <option value="3">3rd Year</option><option value="4">4th Year</option>
            </select>
          </div>
        </div>
        <div class="field"><label>Password (default: Password123)</label><input type="text" name="password" placeholder="Password123"/></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background:var(--gray-200);color:var(--gray-800);" onclick="closeModal('addStudentModal')">Cancel</button>
        <button type="submit" name="add_student" class="btn btn-blue">Add Student</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT STUDENT MODAL -->
<div class="modal-overlay" id="editStudentModal">
  <div class="modal" style="max-width:520px;">
    <div class="modal-head">
      <h3>Edit Student</h3>
      <button class="modal-close" onclick="closeModal('editStudentModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="student_id" id="edit_student_id"/>
        <div class="field-row">
          <div class="field"><label>Last Name *</label><input type="text" name="lastname" id="edit_ln" required/></div>
          <div class="field"><label>First Name *</label><input type="text" name="firstname" id="edit_fn" required/></div>
        </div>
        <div class="field"><label>Middle Name</label><input type="text" name="middlename" id="edit_mn"/></div>
        <div class="field"><label>Email *</label><input type="email" name="email" id="edit_email" required/></div>
        <div class="field-row">
          <div class="field"><label>Course *</label>
            <select name="course" id="edit_course" required>
              <option>BSIT</option><option>BSCS</option><option>BSDA</option><option>ACT</option>
            </select>
          </div>
          <div class="field"><label>Year Level *</label>
            <select name="year_level" id="edit_year" required>
              <option value="1">1st Year</option><option value="2">2nd Year</option>
              <option value="3">3rd Year</option><option value="4">4th Year</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label>Remaining Sessions *</label>
          <input type="number" name="session" id="edit_session" min="0" max="30" required
                 style="width:100%;padding:9px 11px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;"
                 onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--gray-200)'"/>
          <small style="font-size:11.5px;color:var(--gray-400);margin-top:4px;display:block;">Enter a value between 0 and 30</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background:var(--gray-200);color:var(--gray-800);" onclick="closeModal('editStudentModal')">Cancel</button>
        <button type="submit" name="edit_student" class="btn btn-blue">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT SESSION MODAL -->
<div class="modal-overlay" id="editSessionModal">
  <div class="modal" style="max-width:380px;">
    <div class="modal-head">
      <h3>Edit Session</h3>
      <button class="modal-close" onclick="closeModal('editSessionModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="student_id" id="esess_student_id"/>

        <div style="text-align:center;margin-bottom:18px;">
          <div style="font-size:13px;color:var(--gray-400);margin-bottom:4px;">Student</div>
          <div style="font-size:15px;font-weight:700;color:var(--blue-dk);" id="esess_name"></div>
        </div>

        <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:16px;">
          <button type="button" onclick="adjustSession(-1)"
                  style="width:36px;height:36px;border-radius:50%;border:2px solid var(--gray-200);background:#fff;font-size:20px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--red);">−</button>
          <input type="number" name="session" id="esess_value" min="0" max="30"
                 style="width:80px;text-align:center;padding:10px;border:2px solid var(--blue-bd);border-radius:var(--radius);font-size:22px;font-weight:800;color:var(--blue-dk);font-family:inherit;outline:none;"/>
          <button type="button" onclick="adjustSession(1)"
                  style="width:36px;height:36px;border-radius:50%;border:2px solid var(--gray-200);background:#fff;font-size:20px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#16a34a;">+</button>
        </div>

        <div style="text-align:center;font-size:12px;color:var(--gray-400);margin-bottom:6px;">Value must be between <strong>0</strong> and <strong>30</strong></div>

        <!-- Quick preset buttons -->
        <div style="display:flex;justify-content:center;gap:8px;margin-top:10px;flex-wrap:wrap;">
          <?php foreach([0, 5, 10, 15, 20, 25, 30] as $preset): ?>
          <button type="button" onclick="setSession(<?= $preset ?>)"
                  style="padding:4px 12px;border-radius:20px;border:1.5px solid var(--gray-200);background:#fff;font-size:12px;font-weight:600;cursor:pointer;color:var(--gray-600);transition:all .15s;"
                  onmouseover="this.style.borderColor='var(--blue)';this.style.color='var(--blue)'"
                  onmouseout="this.style.borderColor='var(--gray-200)';this.style.color='var(--gray-600)'"><?= $preset ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background:var(--gray-200);color:var(--gray-800);" onclick="closeModal('editSessionModal')">Cancel</button>
        <button type="submit" name="edit_session_only" class="btn btn-blue">Save Session</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT SIT-IN RECORD MODAL -->
<div class="modal-overlay" id="editSitinModal">
  <div class="modal" style="max-width:500px;">
    <div class="modal-head">
      <h3>Edit Sit-in Record</h3>
      <button class="modal-close" onclick="closeModal('editSitinModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="sitin_record_id" id="esitin_id"/>
        <div class="field-row">
          <div class="field">
            <label>Purpose *</label>
            <select name="edit_sit_purpose" id="esitin_purpose" required>
              <option value="">— Select Purpose —</option>
              <?php foreach(['C Programming','Java Programming','Python Programming','C# Programming','PHP Programming','Database (MySQL)','Web Development','Data Structures','Computer Networks','Operating Systems','System Administration','Thesis / Capstone','Research','Online Class','Others'] as $p): ?>
              <option value="<?=$p?>"><?=$p?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Laboratory *</label>
            <select name="edit_laboratory" id="esitin_lab" required>
              <option value="">— Select Lab —</option>
              <?php foreach(['Lab 517','Lab 524','Lab 526','Lab 528','Lab 530','Lab 542','Lab 544'] as $l): ?>
              <option value="<?=$l?>"><?=$l?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label>Date *</label>
          <input type="date" name="edit_date" id="esitin_date" required/>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Login Time</label>
            <input type="time" name="edit_login_time" id="esitin_login"/>
          </div>
          <div class="field">
            <label>Logout Time <small style="color:var(--gray-400);font-weight:400;">(leave blank = Ongoing)</small></label>
            <input type="time" name="edit_logout_time" id="esitin_logout"/>
          </div>
        </div>
        <p style="font-size:12px;color:var(--gray-400);margin-top:4px;">
          💡 Leave <strong>Logout Time</strong> blank to keep status as <em>Ongoing</em>. Fill it in to mark as <em>Completed</em>.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background:var(--gray-200);color:var(--gray-800);" onclick="closeModal('editSitinModal')">Cancel</button>
        <button type="submit" name="edit_sitin_record" class="btn btn-blue">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Edit Sit-in Record modal ──
function openEditSitin(id, purpose, lab, loginTime, logoutTime, date) {
  document.getElementById('esitin_id').value = id;
  document.getElementById('esitin_purpose').value = purpose;
  document.getElementById('esitin_lab').value = lab;
  document.getElementById('esitin_date').value = date ? date.substring(0, 10) : '';

  // Extract time part from datetime string (format: "2026-04-11 08:00:00")
  function extractTime(dt) {
    if (!dt || dt === '0000-00-00 00:00:00') return '';
    var parts = dt.split(' ');
    return parts[1] ? parts[1].substring(0, 5) : '';
  }
  document.getElementById('esitin_login').value  = extractTime(loginTime);
  document.getElementById('esitin_logout').value = extractTime(logoutTime);
  openModal('editSitinModal');
}

function openEditSession(id, name, session){
  document.getElementById('esess_student_id').value = id;
  document.getElementById('esess_name').textContent  = name;
  document.getElementById('esess_value').value       = session;
  openModal('editSessionModal');
}
function adjustSession(delta){
  const input = document.getElementById('esess_value');
  const val   = Math.min(30, Math.max(0, (parseInt(input.value) || 0) + delta));
  input.value = val;
}
function setSession(val){
  document.getElementById('esess_value').value = val;
}

// ── Modal helpers ──
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(o=>{
  o.addEventListener('click',e=>{ if(e.target===o) o.classList.remove('open'); });
});

// ── Edit student prefill ──
function openEditStudent(id,idnum,fn,mn,ln,course,year,email,session){
  document.getElementById('edit_student_id').value = id;
  document.getElementById('edit_fn').value = fn;
  document.getElementById('edit_mn').value = mn;
  document.getElementById('edit_ln').value = ln;
  document.getElementById('edit_email').value = email;
  document.getElementById('edit_course').value = course;
  document.getElementById('edit_year').value = year;
  document.getElementById('edit_session').value = session;
  openModal('editStudentModal');
}

// ── Open Sit-in modal (from search results) ──
function openSitinFor(id, idnum, name, session, purpose, lab){
  document.getElementById('sitin_student_id').value = id;
  document.getElementById('sitin_id_number').value  = idnum;
  document.getElementById('sitin_name').value        = name;
  document.getElementById('sitin_session').value     = session;
  document.getElementById('sitin_purpose').value     = purpose || '';
  document.getElementById('sitin_lab').value         = lab || '';
  const msg = document.getElementById('sitin_lookup_msg');
  msg.style.display = 'block';
  msg.style.color   = '#16a34a';
  msg.textContent   = '✅ Registered student found — session will be deducted on Sit In.';
  closeModal('searchModal');
  closeModal('sitinSearchModal');
  openModal('sitinModal');
}

// ── Clear and open blank Sit In Form ──
function openBlankSitin(){
  // Track which page we're on so we can return after submit
  const currentPage = new URLSearchParams(window.location.search).get('page') || 'home';
  document.getElementById('sitin_current_page').value = currentPage;

  document.getElementById('sitin_student_id').value = '0';
  document.getElementById('sitin_id_number').value  = '';
  document.getElementById('sitin_name').value        = '';
  document.getElementById('sitin_session').value     = '';
  document.getElementById('sitin_purpose').value     = '';
  document.getElementById('sitin_lab').value         = '';
  const msg = document.getElementById('sitin_lookup_msg');
  msg.style.display = 'none';
  msg.textContent   = '';
  openModal('sitinModal');
}

// ── Close sit-in modal and clear fields ──
function closeSitinModal(){
  closeModal('sitinModal');
  document.getElementById('sitin_student_id').value = '0';
  document.getElementById('sitin_id_number').value  = '';
  document.getElementById('sitin_name').value        = '';
  document.getElementById('sitin_session').value     = '';
  document.getElementById('sitin_purpose').value     = '';
  document.getElementById('sitin_lab').value         = '';
  document.getElementById('sitin_lookup_msg').style.display = 'none';
}

// ── Look up student by ID number typed in the form ──
function lookupStudent(){
  const idnum = document.getElementById('sitin_id_number').value.trim();
  const msg   = document.getElementById('sitin_lookup_msg');
  if (!idnum){ msg.style.display='block'; msg.style.color='#dc2626'; msg.textContent='Please enter an ID number first.'; return; }

  const found = allStudents.find(s => s.id_number === idnum);
  msg.style.display = 'block';

  if (found) {
    document.getElementById('sitin_student_id').value = found.id;
    document.getElementById('sitin_name').value        = found.name;
    document.getElementById('sitin_session').value     = found.session;
    if (found.sit_purpose) document.getElementById('sitin_purpose').value = found.sit_purpose;
    if (found.laboratory)  document.getElementById('sitin_lab').value     = found.laboratory;
    msg.style.color   = '#16a34a';
    msg.textContent   = '✅ Registered student found — session will be deducted on Sit In.';
  } else {
    document.getElementById('sitin_student_id').value = '0';
    document.getElementById('sitin_name').value        = '';
    document.getElementById('sitin_session').value     = '';
    msg.style.color   = '#ea580c';
    msg.textContent   = '⚠️ No registered account found. Fill in name manually — walk-in will be recorded.';
  }
}

// ── Sit-in page search ──
function sitinSearchFn(q){
  const box = document.getElementById('sitinSearchResults');
  if (!q.trim()){ box.innerHTML=''; return; }
  const res = allStudents.filter(s =>
    s.id_number.toLowerCase().includes(q.toLowerCase()) ||
    s.name.toLowerCase().includes(q.toLowerCase())
  );
  if (!res.length){ box.innerHTML='<p style="color:#aaa;font-size:13px;padding:8px 0;">No students found.</p>'; return; }
  box.innerHTML = res.map(s=>`
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eef0f4;">
      <div>
        <div style="font-size:13.5px;font-weight:700;color:#1a2e45;">${s.name}</div>
        <div style="font-size:12px;color:#8aaac8;margin-top:2px;">${s.id_number} &bull; ${s.course} &bull; Year ${s.year} &bull; <strong style="color:#1B5886;">${s.session} sessions</strong></div>
      </div>
      <button class="btn btn-blue btn-sm" onclick="openSitinFor(${s.id},'${s.id_number}','${s.name.replace(/'/g,"\\'")}',${s.session},'${(s.sit_purpose||'').replace(/'/g,"\\'")}','${(s.laboratory||'').replace(/'/g,"\\'")}')">Sit In</button>
    </div>
  `).join('');
}

// ── Global search (searches student table in memory) ──
const allStudents = <?php echo json_encode(array_map(fn($s)=>[
  'id'         => $s['id'],
  'id_number'  => $s['id_number'],
  'name'       => $s['firstname'].' '.$s['middlename'].' '.$s['lastname'],
  'course'     => $s['course'],
  'year'       => $s['year_level'],
  'session'    => $s['session'],
  'sit_purpose'=> $s['sit_purpose'] ?? '',
  'laboratory' => $s['laboratory'] ?? '',
], $students)); ?>;

function globalSearchFn(q){
  const box = document.getElementById('searchResults');
  if (!q.trim()){ box.innerHTML=''; return; }
  const res = allStudents.filter(s =>
    s.id_number.toLowerCase().includes(q.toLowerCase()) ||
    s.name.toLowerCase().includes(q.toLowerCase())
  );
  if (!res.length){ box.innerHTML='<p style="color:#aaa;font-size:13px;">No results.</p>'; return; }
  box.innerHTML = res.map(s=>`
    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid #eee;">
      <div>
        <div style="font-size:13px;font-weight:600;">${s.name}</div>
        <div style="font-size:12px;color:#888;">${s.id_number} &bull; ${s.course} ${s.year}yr &bull; ${s.session} sessions</div>
      </div>
      <button class="btn btn-blue btn-sm" onclick="openSitinFor(${s.id},'${s.id_number}','${s.name}',${s.session},'${(s.sit_purpose||'').replace(/'/g,"\\'")}','${(s.laboratory||'').replace(/'/g,"\\'")}')">Sit In</button>
    </div>
  `).join('');
}
function runGlobalSearch(){ globalSearchFn(document.getElementById('globalSearch').value); }

// ── Records table pagination + search ──
let recordsCurrentPage = 1;
let recordsSearchQuery = '';

function getRecordsRows(){
  return Array.from(document.querySelectorAll('#recordsBody tr'));
}
function getFilteredRecordsRows(){
  const q = recordsSearchQuery.toLowerCase();
  return getRecordsRows().filter(r => !q || r.textContent.toLowerCase().includes(q));
}
function renderRecords(){
  const perPage  = parseInt(document.getElementById('recordsEntries').value) || 10;
  const filtered = getFilteredRecordsRows();
  const total    = filtered.length;
  const pages    = Math.max(1, Math.ceil(total / perPage));
  recordsCurrentPage = Math.min(recordsCurrentPage, pages);
  const start = (recordsCurrentPage - 1) * perPage;
  const end   = start + perPage;

  // Show/hide rows
  getRecordsRows().forEach(r => r.style.display = 'none');
  filtered.forEach((r, i) => { r.style.display = (i >= start && i < end) ? '' : 'none'; });

  // Info text
  const showing = Math.min(end, total);
  document.getElementById('recordsInfo').textContent =
    total === 0 ? 'Showing 0 entries' :
    `Showing ${start + 1} to ${showing} of ${total} entr${total === 1 ? 'y' : 'ies'}`;

  // Page buttons
  const btns = document.getElementById('recordsPageBtns');
  btns.innerHTML = '';
  for (let i = 1; i <= pages; i++){
    const b = document.createElement('button');
    b.className = 'page-btn' + (i === recordsCurrentPage ? ' active' : '');
    b.textContent = i;
    b.onclick = () => { recordsCurrentPage = i; renderRecords(); };
    btns.appendChild(b);
  }
}
function goRecordsPage(dir){
  const perPage = parseInt(document.getElementById('recordsEntries').value) || 10;
  const pages   = Math.max(1, Math.ceil(getFilteredRecordsRows().length / perPage));
  if (dir === 'first') recordsCurrentPage = 1;
  else if (dir === 'prev')  recordsCurrentPage = Math.max(1, recordsCurrentPage - 1);
  else if (dir === 'next')  recordsCurrentPage = Math.min(pages, recordsCurrentPage + 1);
  else if (dir === 'last')  recordsCurrentPage = pages;
  renderRecords();
}
function filterRecords(q){
  recordsSearchQuery = q;
  recordsCurrentPage = 1;
  renderRecords();
}
function paginateRecords(){ recordsCurrentPage = 1; renderRecords(); }

// Init on load
window.addEventListener('load', function() {
  renderRecords();

  // Auto-open search modal if redirected from another page via Search nav link
  const params = new URLSearchParams(window.location.search);
  if (params.get('open') === 'search') {
    openModal('searchModal');
    // Clean the URL so refresh doesn't re-open it
    const cleanUrl = window.location.pathname + '?page=' + (params.get('page') || 'home');
    history.replaceState(null, '', cleanUrl);
  }
});

// ── Table filter ──
function filterTable(tableId, q){
  const rows = document.querySelectorAll('#'+tableId+' tbody tr');
  rows.forEach(r=>{
    r.style.display = r.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
  });
}

// ── Entries per page (basic) ──
function setEntries(n){ /* could implement pagination here */ }

// ── Pie Chart ──
const purposeLabels = <?php echo json_encode(array_column($purpose_rows,'sit_purpose') ?: ['No Data']); ?>;
const purposeCounts = <?php echo json_encode(array_column($purpose_rows,'cnt') ?: [1]); ?>;
const colors = ['#1B5886','#e63946','#f4a261','#2a9d8f','#e9c46a','#264653'];

function buildChart(canvasId){
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'pie',
    data: { 
      labels: purposeLabels.map((l,i) => l + ' (' + purposeCounts[i] + ')'),
      datasets:[{ data: purposeCounts, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }]
    },
    options: { 
      plugins:{ 
        legend:{ 
          position:'top', 
          labels:{ font:{ size:11, family:"'Plus Jakarta Sans', sans-serif" }, padding:10, boxWidth:12 }
        },
        tooltip:{
          callbacks:{
            label: function(c){ return ' ' + c.label.split(' (')[0] + ': ' + c.raw; }
          }
        }
      }, 
      responsive:true 
    }
  });
}
buildChart('purposeChart');
buildChart('reportsChart');
buildChart('analyticsPieChart');
(function(){
  var ctx = document.getElementById('analyticsBarChart');
  if (!ctx) return;
  var dailyLabels = <?php echo json_encode(array_map(fn($d) => date('M d', strtotime($d['date'])), $daily_stats) ?: []); ?>;
  var dailyCounts = <?php echo json_encode(array_column($daily_stats, 'cnt') ?: []); ?>;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: dailyLabels,
      datasets: [{ label: 'Sit-ins', data: dailyCounts, backgroundColor: '#1B5886', borderRadius: 5 }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      },
      responsive: true
    }
  });
})();

// ── AI Floating Widget ────────────────────────────────────────
var aiHistory = [];
var aiOpen = false;

<?php
$ai_purposes = implode(', ', array_map(fn($r) => $r['sit_purpose'].' ('.$r['cnt'].')', $purpose_rows));
?>
var aiSystemPrompt = "You are CCS Admin AI, a helpful assistant for the College of Computer Studies (CCS) sit-in lab management system.\n\n" +
  "Live system data right now:\n" +
  "- Students Registered: <?= $total_students ?>\n" +
  "- Currently Sitting In: <?= $currently_sitin ?>\n" +
  "- Total Sit-in Records: <?= $total_sitin ?>\n" +
  "- Top sit-in purposes: <?= addslashes($ai_purposes) ?>\n\n" +
  "You can answer ANY question the admin asks — not just about the system. Be helpful, concise, and friendly. " +
  "For system-related questions, use the live data above. For general questions, answer them fully. " +
  "Format responses in plain readable text.";

function aiToggle() {
  aiOpen = !aiOpen;
  var panel = document.getElementById('aiPanel');
  var fab   = document.getElementById('aiFab');
  var dot   = document.getElementById('aiDot');
  panel.style.display = aiOpen ? 'flex' : 'none';
  fab.style.transform  = aiOpen ? 'rotate(20deg) scale(1.08)' : '';
  dot.style.display    = 'none';
  if (aiOpen) {
    panel.style.flexDirection = 'column';
    setTimeout(function(){ document.getElementById('aiInput2').focus(); }, 120);
  }
}

async function aiSend(prefill) {
  var input = document.getElementById('aiInput2');
  var msg   = (prefill !== undefined ? prefill : input.value).trim();
  if (!msg) return;

  document.getElementById('aiChips').style.display = 'none';
  input.value = '';
  input.style.height = 'auto';

  aiAppendMsg(msg, 'user');
  aiHistory.push({ role: 'user', content: msg });

  var typingId = aiAppendTyping();
  document.getElementById('aiSendBtn').disabled = true;

  try {
    var res = await fetch('ai_proxy.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        system: aiSystemPrompt,
        messages: aiHistory
      })
    });
    var data  = await res.json();
    var reply = (data && data.content && data.content[0] && data.content[0].text)
                ? data.content[0].text
                : '⚠️ No response. Please try again.';
    aiRemoveTyping(typingId);
    aiAppendMsg(reply, 'bot');
    aiHistory.push({ role: 'assistant', content: reply });

    // Show unread dot if panel is closed
    if (!aiOpen) document.getElementById('aiDot').style.display = 'block';

  } catch(e) {
    aiRemoveTyping(typingId);
    aiAppendMsg('⚠️ Could not reach AI. Check your connection and try again.', 'bot');
  }

  document.getElementById('aiSendBtn').disabled = false;
  if (aiOpen) document.getElementById('aiInput2').focus();
}

function aiAppendMsg(text, role) {
  var wrap   = document.getElementById('aiMessages');
  var row    = document.createElement('div');
  row.className = 'ai-msg ai-msg-' + (role === 'user' ? 'user' : 'bot');
  var bubble = document.createElement('div');
  bubble.className = 'ai-bubble ai-bubble-' + (role === 'user' ? 'user' : 'bot');
  bubble.textContent = text;
  row.appendChild(bubble);
  wrap.appendChild(row);
  wrap.scrollTop = wrap.scrollHeight;
}

function aiAppendTyping() {
  var wrap = document.getElementById('aiMessages');
  var id   = 'ait-' + Date.now();
  var row  = document.createElement('div');
  row.className = 'ai-msg ai-msg-bot';
  row.id = id;
  var bubble = document.createElement('div');
  bubble.className = 'ai-bubble ai-bubble-typing';
  bubble.textContent = 'Thinking…';
  row.appendChild(bubble);
  wrap.appendChild(row);
  wrap.scrollTop = wrap.scrollHeight;
  return id;
}

function aiRemoveTyping(id) {
  var el = document.getElementById(id);
  if (el) el.remove();
}

function aiClearChat() {
  aiHistory = [];
  document.getElementById('aiMessages').innerHTML =
    '<div class="ai-msg ai-msg-bot"><div class="ai-bubble ai-bubble-bot">' +
    '👋 Chat cleared! Ask me anything.' +
    '</div></div>';
  document.getElementById('aiChips').style.display = 'flex';
}

// FAB hover effect
document.getElementById('aiFab').addEventListener('mouseenter', function(){
  if (!aiOpen) this.style.transform = 'scale(1.12)';
});
document.getElementById('aiFab').addEventListener('mouseleave', function(){
  if (!aiOpen) this.style.transform = '';
});
</script>
</body>
</html>