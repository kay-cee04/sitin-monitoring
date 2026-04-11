<?php
session_start();

// ── Record logout time before destroying session ──
if (isset($_SESSION['student_id']) && isset($_SESSION['logged_in'])) {
    require_once 'db.php';
    
    $student_id = (int)$_SESSION['student_id'];
    
    // Update the active session's logout_time
    $pdo->prepare(
        "UPDATE sit_in_history 
         SET logout_time = NOW() 
         WHERE student_id = ? AND date = CURDATE() AND logout_time IS NULL LIMIT 1"
    )->execute([$student_id]);
}

// ── Destroy session and redirect ──
session_unset();
session_destroy();
header('Location: login.php');
exit;