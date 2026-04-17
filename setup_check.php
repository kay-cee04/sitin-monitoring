<?php
session_start();
require_once 'db.php';

// Check if user is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

$tables_created = [];
$tables_failed = [];

// List of required tables and their creation SQL
$required_tables = [
    'students' => "CREATE TABLE IF NOT EXISTS students (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        id_number       VARCHAR(20)  NOT NULL UNIQUE,
        lastname        VARCHAR(100) NOT NULL,
        firstname       VARCHAR(100) NOT NULL,
        middlename      VARCHAR(100) DEFAULT '',
        course          VARCHAR(20)  NOT NULL,
        year_level      TINYINT      NOT NULL DEFAULT 1,
        email           VARCHAR(150) NOT NULL UNIQUE,
        password        VARCHAR(255) NOT NULL,
        address         VARCHAR(255) DEFAULT '',
        session         INT          NOT NULL DEFAULT 30,
        created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    )",
    'admins' => "CREATE TABLE IF NOT EXISTS admins (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        username    VARCHAR(100) NOT NULL UNIQUE,
        password    VARCHAR(255) NOT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'sit_in_history' => "CREATE TABLE IF NOT EXISTS sit_in_history (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        student_id      INT         NULL DEFAULT NULL,
        id_number       VARCHAR(20) NOT NULL,
        fullname        VARCHAR(255) NOT NULL,
        sit_purpose     VARCHAR(255) NOT NULL,
        laboratory      VARCHAR(50) NOT NULL,
        login_time      DATETIME    DEFAULT NULL,
        logout_time     DATETIME    DEFAULT NULL,
        date            DATE        NOT NULL,
        created_at      TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )",
    'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        student_id  INT  NOT NULL,
        message     TEXT NOT NULL,
        is_read     TINYINT(1) DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )",
    'announcements' => "CREATE TABLE IF NOT EXISTS announcements (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        admin_name  VARCHAR(100) NOT NULL DEFAULT 'CCS Admin',
        content     TEXT,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'reservations' => "CREATE TABLE IF NOT EXISTS reservations (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        student_id      INT          NOT NULL,
        id_number       VARCHAR(20)  NOT NULL,
        purpose         VARCHAR(255) NOT NULL,
        laboratory      VARCHAR(50)  NOT NULL,
        time_in         TIME         DEFAULT NULL,
        date            DATE         DEFAULT NULL,
        status          ENUM('pending','approved','rejected') DEFAULT 'pending',
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )",
    'feedback' => "CREATE TABLE IF NOT EXISTS feedback (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        sitin_id        INT         NOT NULL,
        student_id      INT         NOT NULL,
        admin_feedback  TEXT        NOT NULL,
        admin_name      VARCHAR(100) DEFAULT 'CCS Admin',
        created_at      TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sitin_id) REFERENCES sit_in_history(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )"
];

// Try to create each table
foreach ($required_tables as $table_name => $sql) {
    try {
        $pdo->exec($sql);
        $tables_created[] = $table_name;
    } catch (Exception $e) {
        $tables_failed[] = [
            'table' => $table_name,
            'error' => $e->getMessage()
        ];
    }
}

// Check if all tables exist
$all_tables_exist = empty($tables_failed);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CCS | Database Setup Check</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --blue:#1B5886;--blue-dk:#003A6B;--green:#16a34a;--red:#dc2626;
  --gray-50:#f4f8fc;--gray-200:#cddaec;--gray-600:#3d607f;--gray-800:#1a2e45;
  --white:#fff;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gray-50);color:var(--gray-800);min-height:100vh;font-size:14px;}
.container{max-width:800px;margin:0 auto;padding:40px 20px;}
.header{text-align:center;margin-bottom:40px;}
.header h1{font-size:28px;font-weight:800;color:var(--blue-dk);margin-bottom:8px;}
.header p{color:var(--gray-600);}

.status-card{background:var(--white);border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,58,107,0.09);}
.status-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
.status-title{font-size:16px;font-weight:700;color:var(--blue-dk);}
.status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;}
.status-badge.success{background:#dcfce7;color:var(--green);}
.status-badge.failed{background:#fee2e2;color:var(--red);}

.table-list{display:grid;gap:10px;}
.table-item{display:flex;align-items:center;gap:10px;padding:10px;background:var(--gray-50);border-radius:6px;border:1px solid var(--gray-200);}
.table-item.success{border-color:#86efac;background:#f0fdf4;}
.table-item.failed{border-color:#fca5a5;background:#fef2f2;}
.table-icon{font-size:18px;}
.table-name{flex:1;font-weight:600;color:var(--gray-800);}
.table-status{font-size:12px;font-weight:600;}

.action-buttons{display:flex;gap:10px;justify-content:center;margin-top:30px;}
.btn{padding:10px 20px;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none;display:inline-block;}
.btn-primary{background:var(--blue);color:#fff;}
.btn-primary:hover{background:var(--blue-dk);}
.error-box{background:#fef2f2;border:1px solid var(--red);border-radius:6px;padding:15px;margin-bottom:20px;color:var(--red);}
.error-box h3{margin-bottom:10px;}
.error-box p{font-size:13px;margin-bottom:5px;}

@media(max-width:600px){
  .container{padding:20px;}.header h1{font-size:24px;}
}
</style>
</head>
<body>

<div class="container">
  <div class="header">
    <h1>🔧 Database Setup Check</h1>
    <p>Verifying all required tables are created</p>
  </div>

  <?php if ($all_tables_exist): ?>
  <div class="status-card">
    <div class="status-header">
      <span class="status-title">✅ All Systems Ready</span>
      <span class="status-badge success">SUCCESS</span>
    </div>
    <p style="color:var(--green);font-weight:600;">All required database tables have been created successfully!</p>
  </div>
  <?php else: ?>
  <div class="error-box">
    <h3>⚠️ Issues Detected</h3>
    <p><?= count($tables_failed) ?> table(s) failed to create. This may affect functionality.</p>
  </div>
  <?php endif; ?>

  <div class="status-card">
    <div class="status-header">
      <span class="status-title">Database Tables Status</span>
      <span class="status-badge <?= $all_tables_exist ? 'success' : 'failed' ?>">
        <?= count($tables_created) ?>/<?= count($required_tables) ?> Created
      </span>
    </div>
    
    <div class="table-list">
      <?php foreach ($tables_created as $table): ?>
      <div class="table-item success">
        <span class="table-icon">✓</span>
        <span class="table-name"><?= htmlspecialchars($table) ?></span>
        <span class="table-status" style="color:var(--green);">Created</span>
      </div>
      <?php endforeach; ?>

      <?php foreach ($tables_failed as $failed): ?>
      <div class="table-item failed">
        <span class="table-icon">✗</span>
        <span class="table-name"><?= htmlspecialchars($failed['table']) ?></span>
        <span class="table-status" style="color:var(--red);font-size:11px;"><?= htmlspecialchars($failed['error']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="action-buttons">
    <a href="admin_dashboard.php" class="btn btn-primary">Back to Dashboard</a>
  </div>
</div>

</body>
</html>