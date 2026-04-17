<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = (int)$_SESSION['student_id'];
$firstname  = $_SESSION['firstname'];
$lastname   = $_SESSION['lastname'];
$course     = $_SESSION['course'];
$year_level = $_SESSION['year_level'];

$msg      = '';
$msg_type = '';

// Handle sit-in log submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sitin'])) {
    $lab  = trim($_POST['lab_room'] ?? '');
    $purp = trim($_POST['purpose'] ?? '');
    if ($lab && $purp) {
        $pdo->prepare("INSERT INTO sit_in_history (student_id, id_number, fullname, sit_purpose, laboratory, login_time, date) VALUES (?, ?, ?, ?, ?, NOW(), CURDATE())")
            ->execute([$student_id, $_SESSION['id_number'], $_SESSION['fullname'], $purp, $lab]);
        $msg      = "Sit-in logged successfully!";
        $msg_type = "success";
    } else {
        $msg      = "Please select a lab room and enter a purpose.";
        $msg_type = "error";
    }
}

// Fetch recent sit-in logs from the correct table
$logs = $pdo->prepare("SELECT * FROM sit_in_history WHERE student_id = ? ORDER BY login_time DESC LIMIT 10");
$logs->execute([$student_id]);
$logs = $logs->fetchAll();

// Suffix helper
function ordSuffix($n) {
    if ($n == 1) return '1st';
    if ($n == 2) return '2nd';
    if ($n == 3) return '3rd';
    return $n . 'th';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Poppins', sans-serif;
            background: #e8eaf0;
            min-height: 100vh;
        }

        /* NAVBAR */
        nav {
            background: #1e3a7b;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 99;
        }
        nav .brand { color: #fff; font-size: 0.83rem; font-weight: 500; }
        nav .nav-right { display: flex; align-items: center; gap: 10px; }
        nav .nav-right .user-name {
            color: rgba(255,255,255,0.9);
            font-size: 0.8rem;
        }
        nav .nav-right a.btn-logout {
            background: #fff;
            color: #1e3a7b;
            font-weight: 600;
            font-size: 0.78rem;
            padding: 5px 14px;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.18s;
        }
        nav .nav-right a.btn-logout:hover { background: #fdecea; color: #c62828; }

        /* MAIN LAYOUT */
        .container {
            max-width: 860px;
            margin: 30px auto;
            padding: 0 20px 50px;
        }

        /* WELCOME BANNER */
        .welcome-banner {
            background: #1e3a7b;
            color: #fff;
            border-radius: 10px;
            padding: 22px 28px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .welcome-banner h2 {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .welcome-banner p {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.75);
        }
        .welcome-banner .id-badge {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            padding: 10px 18px;
            text-align: center;
        }
        .welcome-banner .id-badge .label {
            font-size: 0.68rem;
            color: rgba(255,255,255,0.65);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .welcome-banner .id-badge .value {
            font-size: 1rem;
            font-weight: 700;
            margin-top: 2px;
        }

        /* CARDS */
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 14px rgba(0,0,0,0.08);
            padding: 24px 28px;
            margin-bottom: 22px;
        }
        .card h3 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e3a7b;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e8eaf0;
        }

        /* FORM */
        .form-row { display: flex; gap: 14px; }
        .form-row .field { flex: 1; }
        .field { margin-bottom: 14px; }
        .field label {
            display: block;
            font-size: 0.75rem;
            color: #1565c0;
            margin-bottom: 4px;
            font-weight: 500;
        }
        .field input,
        .field select,
        .field textarea {
            width: 100%;
            padding: 9px 11px;
            border: 1px solid #c8cdd6;
            border-radius: 5px;
            font-size: 0.86rem;
            font-family: 'Poppins', sans-serif;
            color: #222;
            background: #fff;
            transition: border-color 0.2s;
        }
        .field input:focus,
        .field select:focus,
        .field textarea:focus { outline: none; border-color: #1e3a7b; }

        .btn-log {
            padding: 9px 28px;
            background: #1976d2;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.88rem;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-log:hover { background: #1558a0; }

        /* ALERTS */
        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 0.83rem;
            margin-bottom: 16px;
        }
        .alert.success { background: #e8f5e9; color: #2e7d32; }
        .alert.error   { background: #fdecea; color: #c62828; }

        /* TABLE */
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.83rem;
        }
        thead tr { background: #f0f4fc; }
        th {
            text-align: left;
            padding: 10px 12px;
            color: #1e3a7b;
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        td { padding: 10px 12px; border-bottom: 1px solid #eef0f4; color: #444; }
        tbody tr:hover { background: #f9fafc; }
        tbody tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.74rem;
            font-weight: 600;
        }
        .badge.active { background: #e8f5e9; color: #2e7d32; }
        .badge.done   { background: #f0f4f8; color: #888; }

        .no-records {
            text-align: center;
            color: #aaa;
            padding: 30px 0;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<nav>
    <div class="brand">College of Computer Studies Sit-in Monitoring System</div>
    <div class="nav-right">
        <span class="user-name">👤 <?= htmlspecialchars($firstname . ' ' . $lastname) ?></span>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="container">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div>
            <h2>Welcome, <?= htmlspecialchars($firstname . ' ' . $lastname) ?>!</h2>
            <p><?= htmlspecialchars($course) ?> &bull; <?= ordSuffix($year_level) ?> Year</p>
        </div>
        <div class="id-badge">
            <div class="label">Student ID</div>
            <div class="value"><?= htmlspecialchars($student_id) ?></div>
        </div>
    </div>

    <!-- Log Sit-In Form -->
    <div class="card">
        <h3>📋 Log a Sit-In Session</h3>

        <?php if ($msg): ?>
            <div class="alert <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-row">
                <div class="field">
                    <label>Lab Room</label>
                    <select name="lab_room" required>
                        <option value="">-- Select Room --</option>
                        <option value="Lab 101">Lab 101</option>
                        <option value="Lab 102">Lab 102</option>
                        <option value="Lab 103">Lab 103</option>
                        <option value="Lab 104">Lab 104</option>
                        <option value="Lab 201">Lab 201</option>
                        <option value="Lab 202">Lab 202</option>
                    </select>
                </div>
                <div class="field">
                    <label>Purpose</label>
                    <input type="text" name="purpose" placeholder="e.g. Thesis, Project, Practice" required>
                </div>
            </div>
            <button type="submit" name="sitin" class="btn-log">Log Sit-In</button>
        </form>
    </div>

    <!-- Sit-In History -->
    <div class="card">
        <h3>📅 Sit-In History</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Lab Room</th>
                        <th>Purpose</th>
                        <th>Date &amp; Time In</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $count = 0;
                    foreach ($logs as $log):
                        $count++;
                    ?>
                    <tr>
                        <td><?= $count ?></td>
                        <td><?= htmlspecialchars($log['laboratory'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($log['sit_purpose'] ?? 'N/A') ?></td>
                        <td><?= date('M d, Y — h:i A', strtotime($log['login_time'])) ?></td>
                        <td><span class="badge<?= (!empty($log['logout_time'])) ? ' approved' : ' pending' ?>"><?= (!empty($log['logout_time'])) ? 'Logged Out' : 'Active' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($count === 0): ?>
                    <tr>
                        <td colspan="5" class="no-records">No sit-in records yet. Log your first session above!</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>