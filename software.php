<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}
require_once 'db.php';
$CURRENT_PAGE = 'software';

$student_id = (int)$_SESSION['student_id'];

// ── Ensure software table exists ─────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lab_software (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        lab_name    VARCHAR(100) NOT NULL,
        software    VARCHAR(255) NOT NULL,
        category    VARCHAR(100) DEFAULT 'General',
        is_available TINYINT(1) DEFAULT 1,
        added_by    VARCHAR(100) DEFAULT 'Admin',
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// ── Handle mark-all-read ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notif_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0")->execute([$student_id]);
    header('Location: software.php'); exit;
}

// ── Fetch software grouped by lab ────────────────────────────
$software_rows = $pdo->query("SELECT * FROM lab_software WHERE is_available = 1 ORDER BY lab_name, category, software")->fetchAll();
$labs = [];
foreach ($software_rows as $row) {
    $labs[$row['lab_name']][$row['category']][] = $row['software'];
}

// ── Set current page for active tab highlight ───────────────
$CURRENT_PAGE = 'software';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Software Availability</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --blue:#1B5886;--blue-dk:#003A6B;--blue-lt:#e8f4fb;--blue-bd:#89CFF1;
  --gray-50:#f4f8fc;--gray-100:#e8f0f7;--gray-200:#cddaec;--gray-400:#8aaac8;
  --gray-600:#3d607f;--gray-800:#1a2e45;--white:#fff;
  --radius:8px;--radius-lg:12px;
  --shadow:0 1px 4px rgba(0,58,107,0.08);--shadow-md:0 4px 20px rgba(0,58,107,0.12);
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;}

.page-body{max-width:1100px;margin:0 auto;padding:32px 20px 70px;}
.page-title{font-size:22px;font-weight:800;color:var(--blue-dk);text-align:center;margin-bottom:6px;}
.page-sub{font-size:13px;color:var(--gray-400);text-align:center;margin-bottom:28px;}

.search-bar{display:flex;align-items:center;gap:10px;background:var(--white);border:1.5px solid var(--gray-200);border-radius:var(--radius);padding:9px 14px;margin-bottom:24px;box-shadow:var(--shadow);}
.search-bar svg{width:17px;height:17px;stroke:var(--gray-400);fill:none;stroke-width:2;flex-shrink:0;}
.search-bar input{border:none;outline:none;font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;width:100%;color:var(--gray-800);}
.search-bar input::placeholder{color:var(--gray-400);}

.lab-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px;}
.lab-tab{padding:6px 16px;border-radius:20px;border:1.5px solid var(--gray-200);background:var(--white);font-size:12.5px;font-weight:600;color:var(--gray-600);cursor:pointer;transition:all .15s;}
.lab-tab:hover,.lab-tab.active{background:var(--blue-dk);color:#fff;border-color:var(--blue-dk);}

.labs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;}
.lab-card{background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-200);box-shadow:var(--shadow);overflow:hidden;}
.lab-card-head{background:linear-gradient(135deg,var(--blue-dk),var(--blue));padding:14px 18px;display:flex;align-items:center;gap:10px;}
.lab-card-head svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.lab-card-head h3{color:#fff;font-size:14px;font-weight:800;}
.lab-card-body{padding:14px;}
.category-label{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--blue);margin:12px 0 6px;padding-bottom:4px;border-bottom:1px solid var(--gray-100);}
.category-label:first-child{margin-top:0;}
.software-list{display:flex;flex-wrap:wrap;gap:6px;}
.software-pill{background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:4px 10px;font-size:12px;font-weight:600;color:var(--gray-600);display:flex;align-items:center;gap:5px;}
.software-pill svg{width:11px;height:11px;stroke:#16a34a;fill:none;stroke-width:2.5;}

.empty-state{text-align:center;padding:60px 20px;color:var(--gray-400);}
.empty-state svg{width:48px;height:48px;stroke:var(--gray-200);fill:none;stroke-width:1.5;margin-bottom:12px;}
.empty-state p{font-size:14px;}

@media(max-width:550px){
  .lab-tabs{gap:6px;}
  .lab-tab{padding:4px 12px;font-size:11px;}
  .labs-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- INCLUDE SHARED NAVIGATION + NOTIFICATIONS + DARK MODE -->
<?php require_once 'notif_partial.php'; ?>

<div class="page-body">
  <div class="page-title">Software Availability</div>
  <div class="page-sub">Browse available software across all computer labs</div>

  <div class="search-bar">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" id="softwareSearch" placeholder="Search software or lab..." oninput="filterSoftware(this.value)"/>
  </div>

  <div class="lab-tabs" id="labTabs">
    <div class="lab-tab active" onclick="filterLab('all',this)">All Labs</div>
    <?php foreach (array_keys($labs) as $labName): ?>
    <div class="lab-tab" onclick="filterLab(<?= json_encode($labName) ?>,this)"><?= htmlspecialchars($labName) ?></div>
    <?php endforeach; ?>
  </div>

  <?php if ($labs): ?>
  <div class="labs-grid" id="labsGrid">
    <?php foreach ($labs as $labName => $categories): ?>
    <div class="lab-card" data-lab="<?= htmlspecialchars($labName) ?>">
      <div class="lab-card-head">
        <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        <h3><?= htmlspecialchars($labName) ?></h3>
      </div>
      <div class="lab-card-body">
        <?php foreach ($categories as $cat => $softwares): ?>
        <div class="category-label"><?= htmlspecialchars($cat) ?></div>
        <div class="software-list">
          <?php foreach ($softwares as $sw): ?>
          <span class="software-pill">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <?= htmlspecialchars($sw) ?>
          </span>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
    <p>No software information available yet.<br>The admin will update this soon.</p>
  </div>
  <?php endif; ?>
</div>

<script>
function filterLab(lab, el) {
  document.querySelectorAll('.lab-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  document.querySelectorAll('.lab-card').forEach(c => {
    c.style.display = (lab === 'all' || c.dataset.lab === lab) ? '' : 'none';
  });
}
function filterSoftware(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.lab-card').forEach(card => {
    const text = card.textContent.toLowerCase();
    card.style.display = text.includes(q) ? '' : 'none';
  });
  if (q) {
    document.querySelectorAll('.lab-tab').forEach(t => t.classList.remove('active'));
    if(document.querySelector('.lab-tab')) document.querySelector('.lab-tab').classList.add('active');
  }
}
</script>
</body>
</html>