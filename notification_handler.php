<?php
session_start();
require_once 'db.php';

// Check if student is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
  header('Location: login.php');
  exit;
}

$student_id = $_SESSION['student_id'];
$notif_id = (int)($_GET['id'] ?? 0);

if (!$notif_id) {
  header('Location: Homepage.php');
  exit;
}

// Mark notification as read
try {
  $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND student_id = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$notif_id, $student_id]);
} catch (Exception $e) {
  // Continue anyway
}

// Fetch notification to determine type
try {
  $sql = "SELECT message FROM notifications WHERE id = ? AND student_id = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$notif_id, $student_id]);
  $notif = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$notif) {
    header('Location: Homepage.php');
    exit;
  }
  
  $message = $notif['message'];
  
  // Determine where to redirect based on notification type
  if (strpos($message, '📤') === 0 || strpos($message, 'logged out') !== false) {
    // Logout notification - go to history
    header('Location: history.php');
  } elseif (strpos($message, '💬') === 0 || strpos($message, 'feedback') !== false) {
    // Feedback notification - go to feedback page
    header('Location: feedback.php');
  } else {
    // Default to home
    header('Location: Homepage.php');
  }
  exit;
} catch (Exception $e) {
  header('Location: Homepage.php');
  exit;
}
?>
