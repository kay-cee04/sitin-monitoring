<?php
session_start();
require_once 'db.php';

// Check if the student is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$action = $_GET['action'] ?? '';

if ($action === 'fetch') {
    try {
        // Fetch the 30 most recent notifications
        $stmt = $pdo->prepare("SELECT id, message, is_read, created_at 
                               FROM notifications 
                               WHERE student_id = ? 
                               ORDER BY created_at DESC 
                               LIMIT 30");
        $stmt->execute([$student_id]);
        $notifications = $stmt->fetchAll();

        header('Content-Type: application/json');
        echo json_encode(['notifications' => $notifications]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['notifications' => [], 'error' => 'Database error']);
    }
    exit;
}
?>