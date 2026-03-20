<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

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

    // Sit-in (admin logs a student in)
    if (isset($_POST['do_sitin'])) {
        $sid     = (int)$_POST['student_id'];
        $id_num  = trim($_POST['id_number'] ?? '');
        $name    = trim($_POST['student_name'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $lab     = trim($_POST['lab'] ?? '');
        // Deduct session
        $pdo->prepare("UPDATE students SET session = session - 1 WHERE id = ? AND session > 0")->execute([$sid]);
        $pdo->prepare("INSERT INTO sit_in_history (student_id,id_number,fullname,sit_purpose,laboratory,login_time,date)
                       VALUES (?,?,?,?,?,NOW(),CURDATE())")
            ->execute([$sid,$id_num,$name,$purpose,$lab]);
        header('Location: admin_dashboard.php?page=sitin&msg=sittin'); exit;
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
$current_sitin = $pdo->query("SELECT s.*, st.id as student_db_id FROM sit_in_history s JOIN students st ON s.student_id = st.id WHERE s.logout_time IS NULL AND s.date = CURDATE() ORDER BY s.login_time DESC")->fetchAll();
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
    'sittin'     => '✅ Student logged in.',
    'logout'     => '✅ Student logged out.',
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
  height:56px;
  padding:0 20px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  position:sticky;
  top:0;
  z-index:200;
}

.nav-brand{
  font-size:15px;
  font-weight:700;
  color:#fff;
  white-space:nowrap;
}

.nav-links{
  display:flex;
  align-items:center;
  gap:2px;
  flex-wrap:wrap;
}

.nav-links a{
  font-size:12.5px;
  font-weight:500;
  color:rgba(255,255,255,0.8);
  text-decoration:none;
  padding:5px 10px;
  border-radius:4px;
  white-space:nowrap;
  transition:all .15s;
}

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
.stat-row{display:flex;flex-direction:column;gap:6px;padding:14px 16px;}
.stat-line{font-size:13.5px;color:var(--gray-800);}
.stat-line strong{font-weight:700;}

/* ── PIE CHART ── */
.chart-wrap{padding:12px 16px 16px;display:flex;justify-content:center;}
.chart-wrap canvas{max-width:260px;}

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
    <a href="?page=sitin"       class="<?= $page==='sitin'       ?'active':'' ?>">Sit-in</a>
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
        <div class="card-head"><h2>📊 Statistics</h2></div>
        <div class="stat-row">
          <div class="stat-line">Students Registered: <strong><?= $total_students ?></strong></div>
          <div class="stat-line">Currently Sit-in: <strong><?= $currently_sitin ?></strong></div>
          <div class="stat-line">Total Sit-in: <strong><?= $total_sitin ?></strong></div>
        </div>
        <div class="chart-wrap">
          <canvas id="purposeChart"></canvas>
        </div>
      </div>
    </div>

    <!-- RIGHT: Announcement -->
    <div>
      <div class="card">
        <div class="card-head"><h2>📢 Announcement</h2></div>
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
        <tbody>
          <?php if ($current_sitin): foreach ($current_sitin as $si): ?>
          <tr>
            <td><?= htmlspecialchars($si['id']) ?></td>
            <td><?= htmlspecialchars($si['id_number']) ?></td>
            <td><?= htmlspecialchars($si['fullname']) ?></td>
            <td><?= htmlspecialchars($si['sit_purpose']) ?></td>
            <td><?= htmlspecialchars($si['laboratory']) ?></td>
            <td><?= htmlspecialchars($si['login_time'] ?? '—') ?></td>
            <td><span class="badge badge-approved">Active</span></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="sitin_id" value="<?= $si['id'] ?>"/>
                <button type="submit" name="logout_sitin" class="btn btn-red btn-sm" onclick="return confirm('Log out this student?')">Log Out</button>
              </form>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="no-data">No data available</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:10px 14px;font-size:12.5px;color:var(--gray-400);">
      Showing 1 to <?= count($current_sitin) ?> of <?= count($current_sitin) ?> entr<?= count($current_sitin)===1?'y':'ies' ?>
    </div>
  </div>
</div>

<!-- ════════════ RECORDS ════════════ -->
<div id="page-records" class="page-section <?= $page==='records'?'active':'' ?>">
  <div class="page-title">Sit-in Records</div>
  <div class="toolbar">
    <div class="toolbar-right">
      <select class="entries-select"><option>10</option><option>25</option><option>50</option><option>100</option></select>
      <span style="font-size:13px;color:var(--gray-600);">entries per page</span>
      <span style="font-size:13px;color:var(--gray-600);margin-left:12px;">Search:</span>
      <input type="text" class="search-input" oninput="filterTable('recordsTable',this.value)" placeholder=""/>
    </div>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table id="recordsTable">
        <thead>
          <tr>
            <th class="sortable">ID Number</th>
            <th class="sortable">Name</th>
            <th class="sortable">Purpose</th>
            <th class="sortable">Lab</th>
            <th class="sortable">Login</th>
            <th class="sortable">Logout</th>
            <th class="sortable">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($all_sitin): foreach ($all_sitin as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['id_number']) ?></td>
            <td><?= htmlspecialchars($r['fullname']) ?></td>
            <td><?= htmlspecialchars($r['sit_purpose']) ?></td>
            <td><?= htmlspecialchars($r['laboratory']) ?></td>
            <td><?= htmlspecialchars($r['login_time'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['logout_time'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['date']) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="7" class="no-data">No records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
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

<!-- SIT-IN FORM MODAL -->
<div class="modal-overlay" id="sitinModal">
  <div class="modal">
    <div class="modal-head">
      <h3>Sit In Form</h3>
      <button class="modal-close" onclick="closeModal('sitinModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="student_id" id="sitin_student_id"/>
        <div class="field"><label>ID Number:</label><input type="text" name="id_number" id="sitin_id_number" readonly/></div>
        <div class="field"><label>Student Name:</label><input type="text" name="student_name" id="sitin_name" readonly/></div>
        <div class="field"><label>Purpose:</label><input type="text" name="purpose" placeholder="e.g. C Programming" required/></div>
        <div class="field"><label>Lab:</label><input type="text" name="lab" placeholder="e.g. 524" required/></div>
        <div class="field"><label>Remaining Session:</label><input type="text" id="sitin_session" readonly/></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background:var(--gray-200);color:var(--gray-800);" onclick="closeModal('sitinModal')">Close</button>
        <button type="submit" name="do_sitin" class="btn btn-blue">Sit In</button>
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

<script>
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

// ── Open Sit-in modal from search results ──
function openSitinFor(id, idnum, name, session){
  document.getElementById('sitin_student_id').value = id;
  document.getElementById('sitin_id_number').value = idnum;
  document.getElementById('sitin_name').value = name;
  document.getElementById('sitin_session').value = session;
  closeModal('searchModal');
  openModal('sitinModal');
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
    data: { labels: purposeLabels, datasets:[{ data: purposeCounts, backgroundColor: colors }] },
    options: { plugins:{ legend:{ position:'top', labels:{ font:{ size:11 } } } }, responsive:true }
  });
}
buildChart('purposeChart');
buildChart('reportsChart');
</script>
</body>
</html>