<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

// One-time schema fix: allow NULL student_id for walk-in students
try { $pdo->exec("ALTER TABLE sit_in_history MODIFY student_id INT NULL DEFAULT NULL"); } catch (Exception $e) {}


// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Post announcement
    if (isset($_POST['add_announcement'])) {
        $content = trim($_POST['content'] ?? '');
        $pdo->prepare("INSERT INTO announcements (admin_name, content) VALUES (?, ?)")
            ->execute([$_SESSION['admin_username'], $content ?: null]);
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

    // Sit-in — works for BOTH registered students AND walk-in (no account)
    if (isset($_POST['do_sitin'])) {
        $id_num  = trim($_POST['id_number'] ?? '');
        $name    = trim($_POST['student_name'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $lab     = trim($_POST['lab'] ?? '');

        if (!$id_num || !$name || !$purpose || !$lab) {
            header('Location: admin_dashboard.php?page=students&msg=sitin_err'); exit;
        }

        try {
            // Try to find a registered student by ID number
            $stu = $pdo->prepare("SELECT * FROM students WHERE id_number = ? LIMIT 1");
            $stu->execute([$id_num]);
            $found = $stu->fetch();

            if ($found) {
                // Registered student — deduct session and log sit-in
                $pdo->prepare("UPDATE students SET session = session - 1 WHERE id = ? AND session > 0")
                    ->execute([$found['id']]);
                $pdo->prepare("INSERT INTO sit_in_history (student_id, id_number, fullname, sit_purpose, laboratory, login_time, date) VALUES (?,?,?,?,?,NOW(),CURDATE())")
                    ->execute([$found['id'], $id_num, $name, $purpose, $lab]);
            } else {
                // Walk-in — alter table to allow NULL if needed, then insert
                try {
                    $pdo->exec("ALTER TABLE sit_in_history MODIFY student_id INT NULL");
                } catch (Exception $e) { /* already nullable */ }
                $pdo->prepare("INSERT INTO sit_in_history (student_id, id_number, fullname, sit_purpose, laboratory, login_time, date) VALUES (NULL,?,?,?,?,NOW(),CURDATE())")
                    ->execute([$id_num, $name, $purpose, $lab]);
            }
        } catch (PDOException $e) {
            // Log error and still redirect
            error_log('Sit-in error: ' . $e->getMessage());
        }

        header('Location: admin_dashboard.php?page=students&msg=sittin'); exit;
    }

    // Logout a sit-in record
    if (isset($_POST['logout_sitin'])) {
        $pdo->prepare("UPDATE sit_in_history SET logout_time = NOW() WHERE id = ? AND logout_time IS NULL")
            ->execute([(int)$_POST['sitin_id']]);
        header('Location: admin_dashboard.php?page=sitin&msg=logout'); exit;
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
];
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
  --blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;
  --gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;
  --gray-400:#8aaac8;--gray-600:#3d607f;--gray-800:#1a2e45;
  --white:#fff;--radius:6px;
  --shadow:0 1px 3px rgba(0,58,107,0.09);--shadow-md:0 4px 16px rgba(0,58,107,0.12);
  --red:#dc2626;--green:#16a34a;--green-lt:#f0fdf4;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);font-size:14px;}

/* ── NAV ── */
nav{background:var(--blue-dk);height:56px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200;}
.nav-brand{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;}
.nav-links{display:flex;align-items:center;gap:2px;flex-wrap:wrap;}
.nav-links a{font-size:12.5px;font-weight:500;color:rgba(255,255,255,0.8);text-decoration:none;padding:5px 10px;border-radius:4px;white-space:nowrap;transition:all .15s;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.12);}
.nav-links a.active{color:#89CFF1;font-weight:600;}
.btn-logout-nav{background:#e8b800;color:#1a1a00 !important;font-weight:700 !important;border-radius:4px;padding:5px 14px !important;margin-left:4px;}
.btn-logout-nav:hover{background:#ffd000 !important;}

/* ── FLASH ── */
.flash{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);padding:9px 16px;border-radius:var(--radius);font-size:13px;margin-bottom:18px;font-weight:500;}

/* ── PAGE BODY ── */
.page-body{max-width:1280px;margin:0 auto;padding:22px 20px 60px;}
.page-section{display:none;}
.page-section.active{display:block;}
.page-title{font-size:20px;font-weight:700;color:var(--blue-dk);margin-bottom:20px;text-align:center;}

/* ── HOME: two-column grid ── */
.home-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}

/* ── CARD ── */
.card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px;}
.card-head{background:var(--blue);padding:10px 16px;display:flex;align-items:center;justify-content:space-between;}
.card-head h2{color:#fff;font-size:13px;font-weight:600;}
.card-body{padding:16px;}

/* ── STATS ── */
.stat-row{display:flex;flex-direction:column;gap:0;padding:0;}
.stat-item{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--gray-100);}
.stat-item:last-child{border-bottom:none;}
.stat-icon-box{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon-box svg{width:18px;height:18px;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.stat-icon-blue{background:#e8f4fb;border:1px solid #89CFF1;}
.stat-icon-blue svg{stroke:#1B5886;}
.stat-icon-green{background:#f0fdf4;border:1px solid #86efac;}
.stat-icon-green svg{stroke:#16a34a;}
.stat-icon-orange{background:#fff7ed;border:1px solid #fed7aa;}
.stat-icon-orange svg{stroke:#ea580c;}
.stat-text{flex:1;}
.stat-label{font-size:12px;color:var(--gray-400);font-weight:500;}
.stat-value{font-size:22px;font-weight:800;color:var(--blue-dk);line-height:1.1;letter-spacing:-0.02em;}

/* ── PIE CHART ── */
.chart-wrap{padding:12px 16px 16px;display:flex;justify-content:center;}
.chart-wrap canvas{max-width:280px;}

/* ── ANNOUNCE FORM ── */
.ann-form{padding:14px 16px;}
.ann-form textarea{width:100%;padding:9px 11px;border:1px solid var(--gray-200);border-radius:var(--radius);font-family:'Inter',sans-serif;font-size:13px;resize:vertical;min-height:80px;outline:none;transition:border-color .15s;}
.ann-form textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.10);}
.btn-submit{padding:7px 20px;border:none;border-radius:var(--radius);background:#16a34a;color:#fff;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;margin-top:8px;transition:background .15s;}
.btn-submit:hover{background:#15803d;}

/* ── ANN LIST ── */
.ann-posted-title{font-size:16px;font-weight:700;padding:12px 16px 4px;}
.ann-item{padding:10px 16px;border-top:1px solid var(--gray-100);}
.ann-meta{font-size:12.5px;font-weight:600;color:var(--blue-dk);margin-bottom:4px;}
.ann-content{font-size:13px;color:var(--gray-600);}
.ann-del{float:right;}

/* ── TABLE ── */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--gray-50);color:var(--gray-600);font-size:11.5px;font-weight:700;padding:9px 12px;text-align:left;border-bottom:2px solid var(--gray-200);white-space:nowrap;letter-spacing:0.03em;}
tbody tr{border-bottom:1px solid var(--gray-100);transition:background .1s;}
tbody tr:hover{background:var(--gray-50);}
tbody td{padding:9px 12px;font-size:13px;color:var(--gray-600);}
.no-data{text-align:center;padding:28px;color:var(--gray-400);font-size:13px;font-style:italic;}

/* Sortable header arrow */
thead th.sortable{cursor:pointer;user-select:none;}
thead th.sortable::after{content:' ⇅';font-size:10px;opacity:0.5;}

/* ── TOOLBAR ── */
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.toolbar-right{margin-left:auto;display:flex;align-items:center;gap:8px;}
.entries-select{padding:5px 8px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:13px;font-family:'Inter',sans-serif;}
.search-input{padding:6px 11px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:13px;font-family:'Inter',sans-serif;width:180px;outline:none;}
.search-input:focus{border-color:var(--blue);}

/* ── BUTTONS ── */
.btn{padding:7px 16px;border:none;border-radius:var(--radius);font-size:13px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn-blue{background:#1976d2;color:#fff;}
.btn-blue:hover{background:#1558a0;}
.btn-red{background:var(--red);color:#fff;}
.btn-red:hover{background:#b91c1c;}
.btn-green{background:#16a34a;color:#fff;}
.btn-green:hover{background:#15803d;}
.btn-sm{padding:4px 11px;font-size:12px;}

/* ── MODAL ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:500;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:var(--white);border-radius:8px;box-shadow:var(--shadow-md);width:100%;max-width:480px;padding:0;overflow:hidden;}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--gray-200);}
.modal-head h3{font-size:15px;font-weight:700;color:var(--gray-800);}
.modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);line-height:1;padding:0 4px;}
.modal-close:hover{color:var(--gray-800);}
.modal-body{padding:20px;}
.modal-footer{padding:12px 20px;border-top:1px solid var(--gray-100);display:flex;justify-content:flex-end;gap:8px;}

/* ── FORM FIELDS ── */
.field{margin-bottom:14px;}
.field label{display:block;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:5px;}
.field input,.field select{width:100%;padding:9px 11px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:13px;font-family:'Inter',sans-serif;color:var(--gray-800);outline:none;transition:border-color .15s;}
.field input:focus,.field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.10);}
.field input[readonly]{background:var(--gray-50);color:var(--gray-400);}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

/* ── STATUS BADGE ── */
.badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11.5px;font-weight:600;}
.badge-pending{background:#fef9c3;color:#854d0e;}
.badge-approved{background:#dcfce7;color:#15803d;}
.badge-rejected{background:#fee2e2;color:var(--red);}

/* ── PAGINATION ── */
.page-btn{
  width:30px;height:30px;border-radius:6px;
  border:1.5px solid var(--gray-200);background:var(--white);
  font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--gray-600);
  cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
  transition:all .15s;flex-shrink:0;
}
.page-btn:hover{border-color:var(--blue);color:var(--blue);}
.page-btn.active{background:var(--blue);border-color:var(--blue);color:#fff;font-weight:700;}

@media(max-width:900px){.home-grid{grid-template-columns:1fr;}}
@media(max-width:640px){nav{padding:0 12px;}.nav-brand{font-size:13px;}.nav-links a{padding:4px 7px;font-size:11.5px;}}
</style>
</head>
<body>

<!-- ══════════════ NAV ══════════════ -->
<nav>
  <div class="nav-brand">College of Computer Studies Admin</div>
  <div class="nav-links">
    <a href="?page=home"        class="<?= $page==='home'        ?'active':'' ?>">Home</a>
    <a href="#"                 onclick="openModal('searchModal');return false;">Search</a>
    <a href="?page=students"    class="<?= $page==='students'    ?'active':'' ?>">Students</a>
    <a href="#" class="<?= $page==='sitin' ? 'active' : '' ?>" onclick="openBlankSitin(); return false;">Sit-in</a>
    <a href="?page=records"     class="<?= $page==='records'     ?'active':'' ?>">View Sit-in Records</a>
    <a href="?page=reports"     class="<?= $page==='reports'     ?'active':'' ?>">Sit-in Reports</a>
    <a href="?page=feedback"    class="<?= $page==='feedback'    ?'active':'' ?>">Feedback Reports</a>
    <a href="?page=reservation" class="<?= $page==='reservation' ?'active':'' ?>">Reservation</a>
    <a href="admin_logout.php"  class="btn-logout-nav">Log out</a>
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
            <button type="submit" name="add_announcement" class="btn-submit">Submit</button>
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
            <tr>
              <td><?= htmlspecialchars($p['sit_purpose']) ?></td>
              <td><?= $p['cnt'] ?></td>
            </tr>
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

<!-- ════════════ FEEDBACK ════════════ -->
<div id="page-feedback" class="page-section <?= $page==='feedback'?'active':'' ?>">
  <div class="page-title">Feedback Reports</div>
  <div class="card">
    <div class="card-body" style="color:var(--gray-400);font-size:13px;text-align:center;padding:40px;">No feedback data available yet.</div>
  </div>
</div>

<!-- ════════════ RESERVATION ════════════ -->
<div id="page-reservation" class="page-section <?= $page==='reservation'?'active':'' ?>">
  <div class="page-title">Reservations</div>
  <div class="toolbar">
    <div class="toolbar-right">
      <span style="font-size:13px;color:var(--gray-600);">Search:</span>
      <input type="text" class="search-input" oninput="filterTable('reservTable',this.value)" placeholder=""/>
    </div>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table id="reservTable">
        <thead>
          <tr>
            <th>ID</th><th>Student</th><th>ID Number</th><th>Purpose</th><th>Lab</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($reservations): foreach ($reservations as $rv): ?>
          <tr>
            <td><?= $rv['id'] ?></td>
            <td><?= htmlspecialchars($rv['firstname'].' '.$rv['lastname']) ?></td>
            <td><?= htmlspecialchars($rv['id_number']) ?></td>
            <td><?= htmlspecialchars($rv['purpose']) ?></td>
            <td><?= htmlspecialchars($rv['laboratory']) ?></td>
            <td><?= htmlspecialchars($rv['date'] ?? '—') ?></td>
            <td><?= htmlspecialchars($rv['time_in'] ?? '—') ?></td>
            <td><span class="badge badge-<?= $rv['status'] ?>"><?= ucfirst($rv['status']) ?></span></td>
            <td style="display:flex;gap:5px;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="reservation_id" value="<?= $rv['id'] ?>"/>
                <button type="submit" name="approve_reservation" class="btn btn-green btn-sm">Approve</button>
              </form>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="reservation_id" value="<?= $rv['id'] ?>"/>
                <button type="submit" name="reject_reservation" class="btn btn-red btn-sm">Reject</button>
              </form>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="9" class="no-data">No reservations yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</div><!-- end page-body -->

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
            <td><input type="text" name="purpose" id="sitin_purpose"
                       placeholder="e.g. C Programming" required
                       style="width:100%;padding:8px 11px;border:1px solid #cddaec;border-radius:6px;font-size:13px;font-family:inherit;color:#1a2e45;outline:none;"
                       onfocus="this.style.borderColor='#1B5886'" onblur="this.style.borderColor='#cddaec'"/></td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#3d607f;font-weight:600;padding-right:14px;">Lab:</td>
            <td><input type="text" name="lab" id="sitin_lab"
                       placeholder="e.g. 524" required
                       style="width:100%;padding:8px 11px;border:1px solid #cddaec;border-radius:6px;font-size:13px;font-family:inherit;color:#1a2e45;outline:none;"
                       onfocus="this.style.borderColor='#1B5886'" onblur="this.style.borderColor='#cddaec'"/></td>
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

<script>
// ── Edit Session modal ──
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
function openSitinFor(id, idnum, name, session){
  document.getElementById('sitin_student_id').value = id;
  document.getElementById('sitin_id_number').value  = idnum;
  document.getElementById('sitin_name').value        = name;
  document.getElementById('sitin_session').value     = session;
  document.getElementById('sitin_purpose').value     = '';
  document.getElementById('sitin_lab').value         = '';
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
      <button class="btn btn-blue btn-sm" onclick="openSitinFor(${s.id},'${s.id_number}','${s.name.replace(/'/g,"\\'")}',${s.session})">Sit In</button>
    </div>
  `).join('');
}

// ── Global search (searches student table in memory) ──
const allStudents = <?php echo json_encode(array_map(fn($s)=>[
  'id'       => $s['id'],
  'id_number'=> $s['id_number'],
  'name'     => $s['firstname'].' '.$s['middlename'].' '.$s['lastname'],
  'course'   => $s['course'],
  'year'     => $s['year_level'],
  'session'  => $s['session'],
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
      <button class="btn btn-blue btn-sm" onclick="openSitinFor(${s.id},'${s.id_number}','${s.name}',${s.session})">Sit In</button>
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
window.addEventListener('load', renderRecords);

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
</script>
</body>
</html>