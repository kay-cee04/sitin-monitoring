<?php
session_start();

// ── Student logout — record sit-in logout time ────────────────────────────
if (isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true && isset($_SESSION['student_id'])) {
    require_once 'db.php';
    $student_id = (int)$_SESSION['student_id'];
    $pdo->prepare(
        "UPDATE sit_in_history 
         SET logout_time = NOW() 
         WHERE student_id = ? AND date = CURDATE() AND logout_time IS NULL LIMIT 1"
    )->execute([$student_id]);
}

session_unset();
session_destroy();
header('Location: index.php');
exit;