<?php
session_start();
require_once 'db.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

// Auto-create feedback table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        sitin_id        INT         NOT NULL,
        student_id      INT         NOT NULL,
        admin_feedback  TEXT        NOT NULL,
        admin_name      VARCHAR(100) DEFAULT 'CCS Admin',
        created_at      TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sitin_id) REFERENCES sit_in_history(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )");
} catch (Exception $e) { /* table already exists */ }

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Logout a sit-in record
    if (isset($_POST['logout_sitin'])) {
        $sitin_id = (int)$_POST['sitin_id'];
        $logout_time = $_POST['logout_time'] ?? null;
        
        if ($logout_time) {
            $sitin = $pdo->prepare("SELECT * FROM sit_in_history WHERE id = ? LIMIT 1");
            $sitin->execute([$sitin_id]);
            $record = $sitin->fetch();
            
            if ($record) {
                // Combine date with logout time
                $logout_datetime = $record['date'] . ' ' . $logout_time;
                $pdo->prepare("UPDATE sit_in_history SET logout_time = ? WHERE id = ?")
                    ->execute([$logout_datetime, $sitin_id]);
                
                // Send notification to student
                $notif = '📤 Your sit-in session has been logged out by admin at ' . $logout_time;
                $pdo->prepare("INSERT INTO notifications (student_id, message) VALUES (?, ?)")
                    ->execute([$record['student_id'] ?? 0, $notif]);
            }
        }
        header('Location: admin_sitin_history.php?msg=logout'); exit;
    }
    
    // Add feedback
    if (isset($_POST['add_feedback'])) {
        $sitin_id = (int)$_POST['sitin_id'];
        $feedback = trim($_POST['feedback'] ?? '');
        $admin_name = $_SESSION['admin_username'] ?? 'CCS Admin';
        
        if ($sitin_id && $feedback) {
            $sitin = $pdo->prepare("SELECT student_id FROM sit_in_history WHERE id = ? LIMIT 1");
            $sitin->execute([$sitin_id]);
            $record = $sitin->fetch();
            
            if ($record) {
                $pdo->prepare("INSERT INTO feedback (sitin_id, student_id, admin_feedback, admin_name) VALUES (?, ?, ?, ?)")
                    ->execute([$sitin_id, $record['student_id'], $feedback, $admin_name]);
                
                // Send notification to student
                $notif = '💬 You received feedback from admin: ' . mb_substr($feedback, 0, 50) . (mb_strlen($feedback) > 50 ? '…' : '');
                $pdo->prepare("INSERT INTO notifications (student_id, message) VALUES (?, ?)")
                    ->execute([$record['student_id'], $notif]);
            }
        }
        header('Location: admin_sitin_history.php?msg=feedback_added'); exit;
    }
    
    // Delete sit-in record
    if (isset($_POST['delete_sitin'])) {
        $pdo->prepare("DELETE FROM sit_in_history WHERE id = ?")
            ->execute([(int)$_POST['sitin_id']]);
        header('Location: admin_sitin_history.php?msg=deleted'); exit;
    }
}

// ── Fetch data ───────────────────────────────────────────────
$page = $_GET['page'] ?? 'history';
$filter_status = $_GET['status'] ?? 'all';

// Build query based on filter
$query = "SELECT s.*, 
                 st.firstname, st.lastname, st.middlename, st.course, st.year_level, st.session
          FROM sit_in_history s
          LEFT JOIN students st ON s.student_id = st.id";

if ($filter_status === 'active') {
    $query .= " WHERE s.logout_time IS NULL";
} elseif ($filter_status === 'completed') {
    $query .= " WHERE s.logout_time IS NOT NULL";
}

$query .= " ORDER BY s.created_at DESC";

$all_sitin = $pdo->query($query)->fetchAll();

// Count statistics
$total_sitin = count($all_sitin);
$active_sitin = (int)$pdo->query("SELECT COUNT(*) FROM sit_in_history WHERE logout_time IS NULL")->fetchColumn();
$completed_sitin = (int)$pdo->query("SELECT COUNT(*) FROM sit_in_history WHERE logout_time IS NOT NULL")->fetchColumn();

$flash_map = [
    'logout'         => '✅ Student logged out successfully.',
    'feedback_added' => '✅ Feedback sent to student.',
    'deleted'        => '✅ Record deleted.',
];
$flash_msg = $flash_map[$_GET['msg'] ?? ''] ?? '';

// Fetch all students for the search functionality
$students = $pdo->query("SELECT id, id_number, firstname, middlename, lastname, course, year_level, session FROM students ORDER BY firstname ASC")->fetchAll(PDO::FETCH_ASSOC);
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
  --gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;
  --gray-400:#8aaac8;--gray-600:#3d607f;--gray-800:#1a2e45;
  --white:#fff;--radius:6px;
  --shadow:0 1px 3px rgba(0,58,107,0.09);--shadow-md:0 4px 16px rgba(0,58,107,0.12);
  --red:#dc2626;--green:#16a34a;--green-lt:#f0fdf4;--yellow:#ea580c;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);font-size:14px;}

/* ── NAV ── */
nav{background:var(--blue-dk);height:56px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200;}
.nav-brand{font-size:14px;font-weight:800;color:#fff;white-space:nowrap;letter-spacing:-0.01em;}
.nav-links{display:flex;align-items:center;gap:1px;flex-wrap:wrap;}
.nav-links a{font-size:12.5px;font-weight:500;color:rgba(255,255,255,0.8);text-decoration:none;padding:6px 12px;border-radius:4px;white-space:nowrap;transition:all .15s;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.12);}
.nav-links a.active{color:#89CFF1;font-weight:600;}
.btn-logout-nav{background:#e8b800;color:#1a1a00 !important;font-weight:700 !important;border-radius:4px;padding:6px 16px !important;margin-left:8px;}
.btn-logout-nav:hover{background:#ffd000 !important;}

/* ── FLASH ── */
.flash{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);padding:9px 16px;border-radius:var(--radius);font-size:13px;margin-bottom:18px;font-weight:500;}

/* ── PAGE BODY ── */
.page-body{max-width:1400px;margin:0 auto;padding:22px 20px 60px;}
.page-title{font-size:20px;font-weight:700;color:var(--blue-dk);margin-bottom:20px;text-align:center;}

/* ── CARD ── */
.card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px;}
.card-head{background:var(--blue);padding:10px 16px;display:flex;align-items:center;justify-content:space-between;}
.card-head h2{color:#fff;font-size:13px;font-weight:600;}
.card-body{padding:16px;}

/* ── STATS ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:20px;}
.stat-card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);}
.stat-label{font-size:12px;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;}
.stat-value{font-size:28px;font-weight:800;color:var(--blue-dk);}
.stat-badge{font-size:11px;font-weight:600;padding:3px 8px;border-radius:20px;display:inline-block;margin-top:8px;}
.stat-badge-active{background:#dcfce7;color:#15803d;}
.stat-badge-completed{background:#fef3c7;color:#b45309;}

/* ── TOOLBAR ── */
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.toolbar-right{margin-left:auto;display:flex;align-items:center;gap:8px;}
.filter-btn{padding:6px 14px;border:1.5px solid var(--gray-200);background:var(--white);border-radius:var(--radius);font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;color:var(--gray-600);}
.filter-btn.active{background:var(--blue);border-color:var(--blue);color:#fff;}
.filter-btn:hover{border-color:var(--blue);color:var(--blue);}
.search-input{padding:6px 11px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:13px;font-family:inherit;width:200px;outline:none;}
.search-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.10);}

/* ── TABLE ── */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--gray-50);color:var(--gray-600);font-size:11.5px;font-weight:700;padding:9px 12px;text-align:left;border-bottom:2px solid var(--gray-200);white-space:nowrap;letter-spacing:0.03em;}
tbody tr{border-bottom:1px solid var(--gray-100);transition:background .1s;}
tbody tr:hover{background:var(--gray-50);}
tbody td{padding:9px 12px;font-size:13px;color:var(--gray-600);}
.no-data{text-align:center;padding:28px;color:var(--gray-400);font-size:13px;font-style:italic;}

/* ── BUTTONS ── */
.btn{padding:6px 14px;border:none;border-radius:var(--radius);font-size:12px;font-weight:600;font-family:inherit;cursor:pointer;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn-blue{background:var(--blue);color:#fff;}
.btn-blue:hover{background:#1558a0;}
.btn-red{background:var(--red);color:#fff;}
.btn-red:hover{background:#b91c1c;}
.btn-green{background:var(--green);color:#fff;}
.btn-green:hover{background:#15803d;}
.btn-sm{padding:4px 10px;font-size:12px;}

/* ── BADGES ── */
.badge{display:inline-block;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;}
.badge-active{background:#dcfce7;color:#15803d;}
.badge-completed{background:#fef3c7;color:#b45309;}

/* ── MODAL ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:500;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:var(--white);border-radius:8px;box-shadow:var(--shadow-md);width:100%;max-width:520px;padding:0;overflow:hidden;}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--gray-200);}
.modal-head h3{font-size:15px;font-weight:700;color:var(--gray-800);}
.modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);line-height:1;padding:0 4px;}
.modal-close:hover{color:var(--gray-800);}
.modal-body{padding:20px;}
.modal-footer{padding:12px 20px;border-top:1px solid var(--gray-100);display:flex;justify-content:flex-end;gap:8px;}

/* ── FORM ── */
.field{margin-bottom:14px;}
.field label{display:block;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:5px;}
.field input,.field textarea{width:100%;padding:9px 11px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:13px;font-family:inherit;color:var(--gray-800);outline:none;transition:border-color .15s;}
.field input:focus,.field textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.10);}
.field textarea{resize:vertical;min-height:100px;}

/* ── STUDENT INFO BOX ── */
.student-info{background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--radius);padding:12px;margin-bottom:14px;font-size:13px;}
.info-row{display:flex;justify-content:space-between;gap:12px;padding:4px 0;}
.info-label{font-weight:600;color:var(--gray-600);min-width:120px;}
.info-value{color:var(--gray-800);font-weight:500;}

@media(max-width:768px){
  .stats-grid{grid-template-columns:1fr 1fr;}
  .toolbar{flex-direction:column;align-items:flex-start;}
  .toolbar-right{width:100%;justify-content:space-between;}
  .search-input{width:100%;}
}
</style>
</head>
<body>

<!-- ══════════════ NAV ══════════════ -->
<nav>
  <div class="nav-brand">College of Computer Studies Admin</div>
  <div class="nav-links">
    <a href="admin_dashboard.php?page=home">Home</a>
    <a href="admin_dashboard.php?page=home" onclick="openModal('searchModal');return false;">Search</a>
    <a href="admin_dashboard.php?page=students">Students</a>
    <a href="admin_dashboard.php?page=home" onclick="openBlankSitin(); return false;">Sit-in</a>
    <a href="admin_sitin_history.php" class="active">View Sit-in History</a>
    <a href="admin_dashboard.php?page=reports">Sit-in Reports</a>
    <a href="admin_dashboard.php?page=feedback">Feedback Reports</a>
    <a href="admin_dashboard.php?page=reservation">Reservation</a>
    <a href="admin_logout.php" class="btn-logout-nav">Log out</a>
  </div>
</nav>

<!-- ══════════════ BODY ══════════════ -->
<div class="page-body">

<div style="margin-bottom: 28px; border-bottom: 2px solid var(--gray-200); padding-bottom: 16px; text-align: center;">
  <h1 style="font-size: 26px; font-weight: 800; color: var(--blue-dk); margin: 0;">View Sit-In History</h1>
  <p style="font-size: 13px; color: var(--gray-600); margin-top: 4px;">Manage and review all student sit-in sessions</p>
</div>

<?php if ($flash_msg): ?>
  <div class="flash"><?= $flash_msg ?></div>
<?php endif; ?>

<!-- ════════════ STATISTICS ════════════ -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Total Sit-in Records</div>
    <div class="stat-value"><?= $total_sitin ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Active Sessions</div>
    <div class="stat-value" style="color:#15803d;"><?= $active_sitin ?></div>
    <span class="stat-badge stat-badge-active">In Progress</span>
  </div>
  <div class="stat-card">
    <div class="stat-label">Completed Sessions</div>
    <div class="stat-value" style="color:#b45309;"><?= $completed_sitin ?></div>
    <span class="stat-badge stat-badge-completed">Finished</span>
  </div>
</div>

<!-- ════════════ FILTERS & SEARCH ════════════ -->
<div class="toolbar">
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <button class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>" 
            onclick="window.location.href='?page=history&status=all'">
      All Records (<?= $total_sitin ?>)
    </button>
    <button class="filter-btn <?= $filter_status === 'active' ? 'active' : '' ?>" 
            onclick="window.location.href='?page=history&status=active'">
      Active (<?= $active_sitin ?>)
    </button>
    <button class="filter-btn <?= $filter_status === 'completed' ? 'active' : '' ?>" 
            onclick="window.location.href='?page=history&status=completed'">
      Completed (<?= $completed_sitin ?>)
    </button>
  </div>
  <div class="toolbar-right">
    <input type="text" class="search-input" id="searchInput" placeholder="Search ID, name, purpose..." 
           oninput="filterTable(this.value)"/>
  </div>
</div>

<!-- ════════════ HISTORY TABLE ════════════ -->
<div class="card">
  <div class="table-wrap">
    <table id="historyTable">
      <thead>
        <tr>
          <th>ID Number</th>
          <th>Student Name</th>
          <th>Purpose</th>
          <th>Lab</th>
          <th>Login Time</th>
          <th>Logout Time</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($all_sitin): foreach ($all_sitin as $sit): 
          $is_active = empty($sit['logout_time']);
          $full_name = $sit['firstname'] . ' ' . $sit['middlename'] . ' ' . $sit['lastname'];
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($sit['id_number']) ?></strong></td>
          <td><?= htmlspecialchars($full_name) ?></td>
          <td><?= htmlspecialchars($sit['sit_purpose']) ?></td>
          <td><?= htmlspecialchars($sit['laboratory']) ?></td>
          <td><?= $sit['login_time'] ? date('M d, Y h:i A', strtotime($sit['login_time'])) : '—' ?></td>
          <td><?= $sit['logout_time'] ? date('M d, Y h:i A', strtotime($sit['logout_time'])) : '—' ?></td>
          <td>
            <?php if ($is_active): ?>
              <span class="badge badge-active">Active</span>
            <?php else: ?>
              <span class="badge badge-completed">Completed</span>
            <?php endif; ?>
          </td>
          <td style="display:flex;gap:5px;flex-wrap:wrap;">
            <?php if ($is_active): ?>
              <button class="btn btn-blue btn-sm" onclick="openLogoutModal(<?= $sit['id'] ?>)">Logout</button>
            <?php endif; ?>
            <button class="btn btn-sm" style="background:#7c3aed;color:#fff;" onclick="openFeedbackModal(<?= $sit['id'] ?>, '<?= addslashes($full_name) ?>')">Feedback</button>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="sitin_id" value="<?= $sit['id'] ?>"/>
              <button type="submit" name="delete_sitin" class="btn btn-red btn-sm" onclick="return confirm('Delete this record?')">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" class="no-data">No sit-in records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div><!-- end page-body -->

<!-- ══════════════════════════════════════
     MODALS
══════════════════════════════════════ -->

<!-- LOGOUT MODAL -->
<div class="modal-overlay" id="logoutModal">
  <div class="modal">
    <div class="modal-head">
      <h3>Log Out Student</h3>
      <button class="modal-close" onclick="closeModal('logoutModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="sitin_id" id="logout_sitin_id"/>
        <div class="field">
          <label>Logout Time *</label>
          <input type="time" name="logout_time" id="logout_time" required/>
        </div>
        <p style="font-size:12px;color:var(--gray-400);margin-top:8px;">
          The logout time will be recorded with today's date.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background:var(--gray-200);color:var(--gray-800);" onclick="closeModal('logoutModal')">Cancel</button>
        <button type="submit" name="logout_sitin" class="btn btn-green">Log Out</button>
      </div>
    </form>
  </div>
</div>

<!-- FEEDBACK MODAL -->
<div class="modal-overlay" id="feedbackModal">
  <div class="modal">
    <div class="modal-head">
      <h3>Send Feedback</h3>
      <button class="modal-close" onclick="closeModal('feedbackModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="sitin_id" id="feedback_sitin_id"/>
        
        <div class="student-info">
          <div class="info-row">
            <span class="info-label">Student:</span>
            <span class="info-value" id="feedback_student_name">—</span>
          </div>
        </div>

        <div class="field">
          <label>Feedback *</label>
          <textarea name="feedback" id="feedback_text" placeholder="Enter your feedback for the student..." required></textarea>
          <small style="font-size:11px;color:var(--gray-400);margin-top:4px;display:block;">
            This feedback will be sent to the student and visible in their feedback history.
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background:var(--gray-200);color:var(--gray-800);" onclick="closeModal('feedbackModal')">Cancel</button>
        <button type="submit" name="add_feedback" class="btn btn-blue">Send Feedback</button>
      </div>
    </form>
  </div>
</div>

<!-- ════════════════════════════════════════
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
        <input type="hidden" name="current_page" id="sitin_current_page" value="history"/>

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

<script>
// ── All students data for search ──
const allStudents = <?php echo json_encode(array_map(fn($s)=>[
  'id'       => $s['id'],
  'id_number'=> $s['id_number'],
  'name'     => $s['firstname'].' '.$s['middlename'].' '.$s['lastname'],
  'course'   => $s['course'],
  'year'     => $s['year_level'],
  'session'  => $s['session'],
], $students)); ?>;

// ── Modal functions ──
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('open');
  });
});

// ── Logout modal ──
function openLogoutModal(sitinId) {
  document.getElementById('logout_sitin_id').value = sitinId;
  // Set default time to current time
  const now = new Date();
  const hours = String(now.getHours()).padStart(2, '0');
  const minutes = String(now.getMinutes()).padStart(2, '0');
  document.getElementById('logout_time').value = `${hours}:${minutes}`;
  openModal('logoutModal');
}

// ── Feedback modal ──
function openFeedbackModal(sitinId, studentName) {
  document.getElementById('feedback_sitin_id').value = sitinId;
  document.getElementById('feedback_student_name').textContent = studentName;
  document.getElementById('feedback_text').value = '';
  openModal('feedbackModal');
}

// ── Search/filter ──
function filterTable(query) {
  const table = document.getElementById('historyTable');
  const rows = table.querySelectorAll('tbody tr');
  const q = query.toLowerCase();
  
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(q) ? '' : 'none';
  });
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
  document.getElementById('sitin_current_page').value = 'history';

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
</script>
</body>
</html>