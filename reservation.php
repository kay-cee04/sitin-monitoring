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

// ── Set current page for active tab highlight ───────────────
$CURRENT_PAGE = 'reservation';
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
  --blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;
  --gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-400:#8aaac8;
  --gray-600:#3d607f;--gray-800:#1a2e45;--white:#fff;
  --radius:8px;--radius-lg:12px;
  --shadow:0 1px 4px rgba(0,58,107,0.08);--shadow-md:0 4px 20px rgba(0,58,107,0.12);
  --red:#dc2626;--green:#16a34a;--green-lt:#f0fdf4;--yellow:#d97706;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;}

.page-body{max-width:900px;margin:0 auto;padding:32px 20px 70px;}
.page-title{font-size:22px;font-weight:800;color:var(--blue-dk);text-align:center;margin-bottom:6px;}
.page-sub{font-size:13px;color:var(--gray-400);text-align:center;margin-bottom:28px;}

.alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--radius);font-size:13.5px;font-weight:600;margin-bottom:20px;}
.alert-success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:var(--red);}
.alert-warning{background:#fffbeb;border:1px solid #fed7aa;color:var(--yellow);}

.card{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow-md);overflow:hidden;margin-bottom:24px;}
.card-head{background:var(--blue);padding:13px 18px;display:flex;align-items:center;gap:8px;}
.card-head h2{color:#fff;font-size:13px;font-weight:700;}
.card-head svg{width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.card-body{padding:22px 24px;}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.field{margin-bottom:0;}
.field label{display:block;font-size:11px;font-weight:800;color:var(--gray-600);margin-bottom:5px;text-transform:uppercase;letter-spacing:0.04em;}
.field input,.field select{width:100%;padding:9px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--gray-800);background:var(--white);outline:none;transition:border-color .15s;}
.field input:focus,.field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.1);}
.field.full{grid-column:1/-1;}
.btn-submit{margin-top:18px;padding:11px 30px;border:none;border-radius:var(--radius);background:var(--blue-dk);color:#fff;font-size:14px;font-weight:800;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s;}
.btn-submit:hover{background:#002255;}
.btn-submit:disabled{background:var(--gray-400);cursor:not-allowed;}

table{width:100%;border-collapse:collapse;}
thead tr{background:var(--blue);}
thead th{color:#fff;font-size:11.5px;font-weight:700;padding:11px 14px;text-align:left;white-space:nowrap;letter-spacing:0.04em;text-transform:uppercase;}
tbody tr{border-bottom:1px solid var(--gray-100);transition:background .12s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:var(--gray-50);}
tbody td{padding:11px 14px;font-size:13px;color:var(--gray-600);}
.no-data{text-align:center;padding:40px;color:var(--gray-400);font-style:italic;}

.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;}
.badge-pending{background:#fef9c3;color:#854d0e;}
.badge-approved{background:#dcfce7;color:#15803d;}
.badge-rejected{background:#fee2e2;color:var(--red);}

.disabled-notice{background:#fff7ed;border:1px solid #fed7aa;border-radius:var(--radius);padding:16px;display:flex;align-items:center;gap:12px;margin-bottom:20px;}
.disabled-notice svg{width:22px;height:22px;stroke:#d97706;fill:none;stroke-width:2;flex-shrink:0;}
.disabled-notice p{font-size:13.5px;color:#92400e;font-weight:600;}

@media(max-width:640px){.form-grid{grid-template-columns:1fr;}.field.full{grid-column:1;}nav{padding:0 16px;}}
</style>
</head>
<body>

<!-- INCLUDE SHARED NAVIGATION + NOTIFICATIONS + DARK MODE -->
<?php require_once 'notif_partial.php'; ?>

<div class="page-body">
  <div class="page-title">Lab Reservation</div>
  <div class="page-sub">Reserve a laboratory slot in advance</div>

  <?php if ($success): ?>
  <div class="alert alert-success">
    <svg viewBox="0 0 24 24" style="width:17px;height:17px;stroke:#16a34a;fill:none;stroke-width:2;flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-error">
    <svg viewBox="0 0 24 24" style="width:17px;height:17px;stroke:#dc2626;fill:none;stroke-width:2;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <?php if (!$reservations_open): ?>
  <div class="disabled-notice">
    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>Reservations are currently <strong>closed</strong> by the administrator. Please check back later.</p>
  </div>
  <?php endif; ?>

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

  <div class="card">
    <div class="card-head">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <h2>My Reservations</h2>
    </div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Purpose</th><th>Lab</th><th>Date</th><th>Time</th><th>Status</th><th>Submitted</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($my_reservations): $i=0; foreach ($my_reservations as $r): $i++; ?>
          <tr>
            <td><?= $i ?></td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td><?= htmlspecialchars($r['laboratory']) ?></td>
            <td><?= date('M d, Y', strtotime($r['date'])) ?></td>
            <td><?= date('h:i A', strtotime($r['time_in'])) ?></td>
            <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
            <td><?= date('M d, Y g:i A', strtotime($r['created_at'])) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="7" class="no-data">No reservations yet. Make one above!</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>