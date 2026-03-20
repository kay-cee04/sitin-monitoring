<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header('Location: login.php'); exit; }
require_once 'db.php';
$history = $pdo->prepare("SELECT * FROM sit_in_history WHERE student_id = ? ORDER BY created_at DESC");
$history->execute([$_SESSION['student_id']]);
$rows = $history->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | History</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;--gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-400:#8aaac8;--gray-600:#3d607f;--gray-800:#1a2e45;--white:#fff;--radius:8px;--radius-lg:12px;--shadow:0 1px 3px rgba(0,58,107,0.08);--shadow-md:0 4px 20px rgba(0,58,107,0.11);}
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
.page-body{max-width:1100px;margin:0 auto;padding:32px 20px 60px;}
.page-title{font-size:22px;font-weight:800;color:var(--blue-dk);margin-bottom:24px;text-align:center;letter-spacing:-0.02em;}
.toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;}
.entries-wrap{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-600);}
.entries-wrap select{padding:6px 10px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;outline:none;}
.entries-wrap select:focus{border-color:var(--blue);}
.search-wrap{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-600);}
.search-wrap input{padding:7px 12px;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;outline:none;width:200px;}
.search-wrap input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(27,88,134,0.08);}
.card{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow);overflow:hidden;}
table{width:100%;border-collapse:collapse;}
thead tr{background:var(--blue);}
thead th{color:#fff;font-size:11.5px;font-weight:700;padding:11px 14px;text-align:left;white-space:nowrap;letter-spacing:0.03em;text-transform:uppercase;}
tbody tr{border-bottom:1px solid var(--gray-100);transition:background .12s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:var(--gray-50);}
tbody td{padding:11px 14px;font-size:13px;color:var(--gray-600);}
.no-data{text-align:center;padding:36px;color:var(--gray-400);font-size:13px;font-style:italic;}
.table-footer{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid var(--gray-100);font-size:12.5px;color:var(--gray-400);flex-wrap:wrap;gap:8px;}
.pagination{display:flex;align-items:center;gap:4px;}
.page-btn{width:30px;height:30px;border-radius:6px;border:1.5px solid var(--gray-200);background:var(--white);font-size:12px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--gray-600);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;text-decoration:none;}
.page-btn:hover{border-color:var(--blue);color:var(--blue);}
.page-btn.active{background:var(--blue);border-color:var(--blue);color:#fff;font-weight:700;}
@media(max-width:700px){table{font-size:12px;}thead th,tbody td{padding:9px 10px;}nav{padding:0 16px;}}
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
    <a href="history.php" class="active">History</a>
    <a href="reservation.php">Reservation</a>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>
<div class="page-body">
  <div class="page-title">History Information</div>
  <div class="toolbar">
    <div class="entries-wrap">
      <select id="entriesSelect"><option>10</option><option>25</option><option>50</option><option>100</option></select>
      entries per page
    </div>
    <div class="search-wrap">Search: <input type="text" id="searchInput" placeholder="Search..." oninput="filterTable(this.value)"/></div>
  </div>
  <div class="card">
    <table id="historyTable">
      <thead>
        <tr>
          <th>ID Number</th><th>Name</th><th>Sit Purpose</th><th>Laboratory</th><th>Login</th><th>Logout</th><th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
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
        <tr><td colspan="7" class="no-data">No sit-in history found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <div class="table-footer">
      <span>Showing <?= count($rows) ?> entr<?= count($rows)===1?'y':'ies' ?></span>
      <div class="pagination">
        <a href="#" class="page-btn">«</a><a href="#" class="page-btn">‹</a>
        <a href="#" class="page-btn active">1</a>
        <a href="#" class="page-btn">›</a><a href="#" class="page-btn">»</a>
      </div>
    </div>
  </div>
</div>
<script>
function filterTable(q){
  document.querySelectorAll('#historyTable tbody tr').forEach(r=>{
    r.style.display=r.textContent.toLowerCase().includes(q.toLowerCase())?'':'none';
  });
}
</script>
</body>
</html>