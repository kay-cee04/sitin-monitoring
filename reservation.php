<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header('Location: login.php'); exit; }
require_once 'db.php';
$msg = ''; $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve'])) {
    $purpose = trim($_POST['purpose'] ?? '');
    $lab     = trim($_POST['lab'] ?? '');
    $time_in = trim($_POST['time_in'] ?? '');
    $date    = trim($_POST['date'] ?? '');
    if ($purpose && $lab && $date) {
        $pdo->prepare("INSERT INTO reservations (student_id, id_number, purpose, laboratory, time_in, date, status) VALUES (?,?,?,?,?,?,'pending')")
            ->execute([$_SESSION['student_id'], $_SESSION['id_number'], $purpose, $lab, $time_in ?: null, $date]);
        $msg = 'Reservation submitted successfully! Pending admin approval.';
        $msg_type = 'success';
    } else { $msg = 'Please fill in all required fields.'; $msg_type = 'error'; }
}
$my_reservations = $pdo->prepare("SELECT * FROM reservations WHERE student_id = ? ORDER BY created_at DESC LIMIT 10");
$my_reservations->execute([$_SESSION['student_id']]);
$reservations = $my_reservations->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Reservation</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;--gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-400:#8aaac8;--gray-600:#3d607f;--gray-800:#1a2e45;--white:#fff;--radius:8px;--radius-lg:12px;--shadow:0 1px 3px rgba(0,58,107,0.08);--shadow-md:0 4px 20px rgba(0,58,107,0.11);--red:#dc2626;--red-lt:#fef2f2;--green:#16a34a;--green-lt:#f0fdf4;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;}
nav{background:var(--blue-dk);height:58px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,0.15);}
.nav-brand{font-size:15px;font-weight:800;color:#fff;letter-spacing:-0.02em;}
.nav-links{display:flex;align-items:center;gap:2px;}
.nav-links a{font-size:13px;font-weight:500;color:rgba(255,255,255,0.75);text-decoration:none;padding:6px 11px;border-radius:6px;transition:all .15s;white-space:nowrap;}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,0.1);}
.nav-links a.active{color:#89CFF1;font-weight:600;}
.nav-dropdown{position:relative;}
.nav-dropdown>a{display:flex;align-items:center;gap:4px;}
.nav-dropdown>a .chevron{font-size:10px;color:rgba(255,255,255,0.4);transition:transform .2s;}
.nav-dropdown:hover>a .chevron{transform:rotate(180deg);}
.dropdown-menu{display:none;position:absolute;top:calc(100%+8px);left:0;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow-md);min-width:180px;z-index:200;overflow:hidden;}
.nav-dropdown:hover .dropdown-menu{display:block;}
.dropdown-menu a{display:block;padding:9px 16px;font-size:13px;color:var(--gray-600) !important;background:transparent !important;border-radius:0 !important;}
.dropdown-menu a:hover{background:var(--gray-50) !important;color:var(--blue) !important;}
.btn-logout{background:#e53e3e !important;color:#fff !important;font-weight:700 !important;border-radius:6px;padding:6px 16px !important;margin-left:6px;}
.btn-logout:hover{background:#c53030 !important;}
.page-body{max-width:860px;margin:0 auto;padding:32px 20px 60px;}
.page-title{font-size:22px;font-weight:800;color:var(--blue-dk);margin-bottom:24px;text-align:center;letter-spacing:-0.02em;}
.alert{padding:11px 16px;border-radius:var(--radius);font-size:13px;margin-bottom:20px;font-weight:600;}
.alert-success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
.alert-error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
.card{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow);overflow:hidden;margin-bottom:24px;}
.card-head{background:var(--blue);padding:12px 20px;}
.card-head h2{color:#fff;font-size:13.5px;font-weight:700;}
.card-body{padding:24px 24px 28px;}
.section-divider{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--blue);padding-bottom:8px;border-bottom:1px solid var(--gray-100);margin-bottom:14px;margin-top:22px;}
.section-divider:first-of-type{margin-top:0;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:11.5px;font-weight:700;color:var(--gray-600);margin-bottom:5px;text-transform:uppercase;letter-spacing:0.03em;}
.field input,.field select{width:100%;padding:10px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--gray-800);background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;}
.field input:focus,.field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.1);}
.field input[readonly]{background:var(--gray-50);color:var(--gray-400);cursor:not-allowed;}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.session-badge{display:inline-flex;align-items:center;gap:7px;background:var(--blue-lt);border:1px solid var(--blue-bd);color:var(--blue);border-radius:6px;padding:8px 14px;font-size:13px;font-weight:700;margin-top:4px;}
.btn-reserve{padding:11px 28px;border:none;border-radius:var(--radius);background:var(--blue-dk);color:#fff;font-size:14px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s;margin-top:8px;}
.btn-reserve:hover{background:#002255;}
/* table */
table{width:100%;border-collapse:collapse;}
thead tr{background:var(--blue);}
thead th{color:#fff;font-size:11px;font-weight:700;padding:10px 14px;text-align:left;letter-spacing:0.04em;text-transform:uppercase;}
tbody tr{border-bottom:1px solid var(--gray-100);transition:background .12s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:var(--gray-50);}
tbody td{padding:10px 14px;font-size:13px;color:var(--gray-600);}
.badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11.5px;font-weight:700;}
.badge-pending{background:#fef9c3;color:#854d0e;}
.badge-approved{background:#dcfce7;color:#15803d;}
.badge-rejected{background:#fee2e2;color:var(--red);}
.no-data{text-align:center;padding:28px;color:var(--gray-400);font-size:13px;font-style:italic;}
@media(max-width:600px){.field-row{grid-template-columns:1fr;}nav{padding:0 16px;}.card-body{padding:18px;}}
</style>
</head>
<body>
<nav>
  <div class="nav-brand">Dashboard</div>
  <div class="nav-links">
    <div class="nav-dropdown">
      <a href="#">Notification <span class="chevron">▾</span></a>
      <div class="dropdown-menu"><a href="#">All Notifications</a><a href="#">Unread</a><a href="#">Mark all as read</a></div>
    </div>
    <a href="Homepage.php">Home</a>
    <a href="profile.php">Edit Profile</a>
    <a href="history.php">History</a>
    <a href="reservation.php" class="active">Reservation</a>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>
<div class="page-body">
  <div class="page-title">Reservation</div>
  <?php if ($msg): ?><div class="alert alert-<?= $msg_type ?>"> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="card">
    <div class="card-head"><h2>Lab Reservation Form</h2></div>
    <div class="card-body">
      <form method="POST">
        <div class="section-divider">Student Details</div>
        <div class="field-row">
          <div class="field"><label>ID Number</label><input type="text" value="<?= htmlspecialchars($_SESSION['id_number']??'') ?>" readonly/></div>
          <div class="field"><label>Student Name</label><input type="text" value="<?= htmlspecialchars($_SESSION['fullname']??'') ?>" readonly/></div>
        </div>
        <div class="section-divider">Reservation Details</div>
        <div class="field-row">
          <div class="field"><label>Purpose *</label><input type="text" name="purpose" placeholder="e.g. C Programming" required/></div>
          <div class="field"><label>Laboratory *</label><input type="text" name="lab" placeholder="e.g. 524" required/></div>
        </div>
        <div class="section-divider">Schedule</div>
        <div class="field-row">
          <div class="field"><label>Time In</label><input type="time" name="time_in"/></div>
          <div class="field"><label>Date *</label><input type="date" name="date" required/></div>
        </div>
        <div class="section-divider">Session</div>
        <div class="field">
          <label>Remaining Sessions</label>
          <div class="session-badge">🖥️ <?= htmlspecialchars($_SESSION['session']??'0') ?> sessions remaining</div>
        </div>
        <button type="submit" name="reserve" class="btn-reserve">Submit Reservation</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>My Reservations</h2></div>
    <table>
      <thead><tr><th>Purpose</th><th>Lab</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
      <tbody>
        <?php if ($reservations): foreach ($reservations as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['purpose']) ?></td>
          <td><?= htmlspecialchars($r['laboratory']) ?></td>
          <td><?= htmlspecialchars($r['date']??'—') ?></td>
          <td><?= htmlspecialchars($r['time_in']??'—') ?></td>
          <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="no-data">No reservations yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>