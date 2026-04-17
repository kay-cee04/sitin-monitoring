<?php
session_start();
require_once 'db.php';

// Check if student is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
  header('Location: login.php');
  exit;
}

$student_id = $_SESSION['student_id'];

// Mark notifications as read if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notif_read'])) {
  try {
    $sql = "UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id]);
  } catch (Exception $e) {
    // Error marking as read
  }
}

// Mark individual notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_single_read'])) {
  try {
    $notif_id = (int)$_POST['notif_id'];
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND student_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$notif_id, $student_id]);
  } catch (Exception $e) {
    // Error marking as read
  }
}

// Delete notification if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notif'])) {
  try {
    $notif_id = (int)$_POST['notif_id'];
    $sql = "DELETE FROM notifications WHERE id = ? AND student_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$notif_id, $student_id]);
  } catch (Exception $e) {
    // Error deleting
  }
}

// Fetch all notifications for this student
try {
  $sql = "SELECT id, message, is_read, created_at FROM notifications 
          WHERE student_id = ? 
          ORDER BY created_at DESC 
          LIMIT 100";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$student_id]);
  $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $notifications = [];
}

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notif) {
  if ($notif['is_read'] == 0) {
    $unread_count++;
  }
}

// Strip leading emoji from message
function stripLeadingEmoji($text) {
  return preg_replace('/^[\p{Emoji}]+ */u', '', $text);
}

// Format time difference
function getTimeAgo($timestamp) {
  $now = time();
  $diff = $now - $timestamp;

  if ($diff < 60) {
    return 'just now';
  } elseif ($diff < 3600) {
    $mins = floor($diff / 60);
    return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
  } elseif ($diff < 86400) {
    $hours = floor($diff / 3600);
    return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
  } elseif ($diff < 604800) {
    $days = floor($diff / 86400);
    return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
  } else {
    return date('M d, Y', $timestamp);
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Notifications - CCS Sit-in Monitoring</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root {
  --blue: #1B5886;
  --blue-dk: #003A6B;
  --blue-lt: #e8f4fb;
  --blue-bd: #89CFF1;
  --gray-50: #f4f8fc;
  --gray-100: #e8f0f7;
  --gray-200: #cddaec;
  --gray-400: #8aaac8;
  --gray-600: #3d607f;
  --gray-800: #1a2e45;
  --white: #fff;
  --radius: 8px;
  --radius-lg: 12px;
  --shadow: 0 1px 3px rgba(0, 58, 107, 0.08);
  --shadow-md: 0 4px 20px rgba(0, 58, 107, 0.11);
  --green: #16a34a;
  --red: #dc2626;
  --orange: #ea580c;
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--gray-50);
  color: var(--gray-800);
  min-height: 100vh;
  font-size: 14px;
}

nav {
  background: var(--blue-dk);
  height: 58px;
  padding: 0 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
}

.nav-brand {
  font-size: 15px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.02em;
}

.nav-links {
  display: flex;
  align-items: center;
  gap: 2px;
}

.nav-links a {
  font-size: 13px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.75);
  text-decoration: none;
  padding: 6px 11px;
  border-radius: 6px;
  transition: all 0.15s;
  white-space: nowrap;
}

.nav-links a:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.1);
}

.nav-links a.active {
  color: #89CFF1;
  font-weight: 600;
}

.btn-logout {
  background: #e53e3e !important;
  color: #fff !important;
  font-weight: 700 !important;
  border-radius: 6px;
  padding: 6px 16px !important;
  margin-left: 6px;
}

.btn-logout:hover {
  background: #c53030 !important;
}

.page-body {
  max-width: 900px;
  margin: 0 auto;
  padding: 30px 20px 50px;
}

.page-header {
  margin-bottom: 24px;
}

.page-title {
  font-size: 24px;
  font-weight: 800;
  color: var(--blue-dk);
  margin-bottom: 8px;
}

.page-sub {
  font-size: 13px;
  color: var(--gray-600);
}

.toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  justify-content: space-between;
  align-items: center;
}

.toolbar-left {
  display: flex;
  gap: 12px;
}

.btn {
  background: var(--blue);
  color: #fff;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn:hover {
  background: var(--blue-dk);
}

.btn-secondary {
  background: var(--gray-200);
  color: var(--gray-800);
}

.btn-secondary:hover {
  background: var(--gray-100);
}

.notif-list {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.notif-item {
  background: var(--white);
  border: 1px solid var(--gray-200);
  border-radius: 8px;
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  transition: all 0.2s;
  cursor: pointer;
}

.notif-item:hover {
  box-shadow: var(--shadow-md);
  border-color: var(--blue-bd);
}

.notif-item.unread {
  background: var(--blue-lt);
  border-color: var(--blue-bd);
}

.notif-content {
  flex: 1;
}

.notif-msg {
  font-size: 14px;
  font-weight: 500;
  color: var(--gray-800);
  line-height: 1.5;
  margin-bottom: 6px;
}

.notif-time {
  font-size: 12px;
  color: var(--gray-400);
}

.notif-unread-dot {
  width: 8px;
  height: 8px;
  background: var(--blue);
  border-radius: 50%;
  margin-top: 4px;
  flex-shrink: 0;
}

.notif-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.notif-btn-small {
  background: transparent;
  border: 1px solid var(--gray-200);
  color: var(--gray-600);
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.2s;
}

.notif-btn-small:hover {
  background: var(--gray-100);
  border-color: var(--gray-400);
  color: var(--gray-800);
}

.notif-btn-delete:hover {
  background: #fee2e2;
  border-color: var(--red);
  color: var(--red);
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.empty-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--gray-800);
  margin-bottom: 8px;
}

.empty-desc {
  font-size: 13px;
  color: var(--gray-600);
}

@media (max-width: 600px) {
  .toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .toolbar-left {
    width: 100%;
  }

  .notif-item {
    flex-direction: column;
  }

  .notif-actions {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>
</head>
<body>

<nav>
  <div class="nav-brand">Dashboard</div>
  <div class="nav-links">
    <a href="Homepage.php">Home</a>
    <a href="profile.php">Edit Profile</a>
    <a href="history.php">History</a>
    <a href="feedback.php">Feedback</a>
    <a href="notifications.php" class="active">Notifications</a>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="page-body">
  <div class="page-header">
    <div class="page-title">🔔 Notifications</div>
    <div class="page-sub">You have <?= $unread_count ?> unread notification<?= $unread_count !== 1 ? 's' : '' ?></div>
  </div>

  <?php if (!empty($notifications)): ?>
    <div class="toolbar">
      <div class="toolbar-left">
        <?php if ($unread_count > 0): ?>
          <form method="POST" action="notifications.php" style="display: inline;">
            <input type="hidden" name="mark_notif_read" value="1"/>
            <button type="submit" class="btn btn-secondary">Mark all as read</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="notif-list">
      <?php foreach ($notifications as $notif):
        $ts = (int)strtotime($notif['created_at']);
        $msg = stripLeadingEmoji($notif['message']);
        $time_ago = getTimeAgo($ts);
      ?>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
          <a href="notification_handler.php?id=<?= (int)$notif['id'] ?>" class="notif-item <?= $notif['is_read'] == 0 ? 'unread' : 'read' ?>" style="text-decoration: none; color: inherit; flex: 1;">
            <div class="notif-content">
              <div class="notif-msg"><?= htmlspecialchars($msg) ?></div>
              <div class="notif-time"><?= $time_ago ?></div>
            </div>
            <?php if ($notif['is_read'] == 0): ?>
              <div class="notif-unread-dot"></div>
            <?php endif; ?>
          </a>
          <div style="display: flex; gap: 8px; flex-shrink: 0;" onclick="event.stopPropagation();">
            <?php if ($notif['is_read'] == 0): ?>
              <form method="POST" action="notifications.php" style="display: inline;">
                <input type="hidden" name="mark_single_read" value="1"/>
                <input type="hidden" name="notif_id" value="<?= (int)$notif['id'] ?>"/>
                <button type="submit" class="notif-btn-small">Mark read</button>
              </form>
            <?php endif; ?>
            <form method="POST" action="notifications.php" style="display: inline;">
              <input type="hidden" name="delete_notif" value="1"/>
              <input type="hidden" name="notif_id" value="<?= (int)$notif['id'] ?>"/>
              <button type="submit" class="notif-btn-small notif-btn-delete">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <div class="empty-title">No notifications yet</div>
      <div class="empty-desc">You'll see your notifications here when they arrive</div>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
