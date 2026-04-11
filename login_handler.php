<?php
// ============================================================
//  login_handler.php — Processes login form submission
//  This is called by login.php via form POST
// ============================================================

session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$id_number = trim($_POST['id_number'] ?? '');
$password  = trim($_POST['password']  ?? '');

if (empty($id_number) || empty($password)) {
    header('Location: login.php?error=Please+fill+in+all+fields');
    exit;
}

// Fetch student by ID number
$stmt = $pdo->prepare("SELECT * FROM students WHERE id_number = ? LIMIT 1");
$stmt->execute([$id_number]);
$student = $stmt->fetch();

if ($student && password_verify($password, $student['password'])) {
    // ── Set session variables ──
    $_SESSION['logged_in']  = true;
    $_SESSION['student_id'] = $student['id'];
    $_SESSION['id_number']  = $student['id_number'];
    $_SESSION['lastname']   = $student['lastname'];
    $_SESSION['firstname']  = $student['firstname'];
    $_SESSION['middlename'] = $student['middlename'];
    $_SESSION['fullname']   = $student['firstname'] . ' ' . $student['middlename'] . ' ' . $student['lastname'];
    $_SESSION['course']     = $student['course'];
    $_SESSION['year_level'] = $student['year_level'];
    $_SESSION['email']      = $student['email'];
    $_SESSION['address']    = $student['address'];
    $_SESSION['session']    = $student['session'];

    // ── Create sit-in record on login ──
    // Check if there's an active session (logout_time IS NULL) for today
    $active_session = $pdo->prepare(
        "SELECT id FROM sit_in_history 
         WHERE student_id = ? AND date = CURDATE() AND logout_time IS NULL LIMIT 1"
    );
    $active_session->execute([$student['id']]);
    $has_active = $active_session->fetch();
    
    // Only create new record if no active session exists
    if (!$has_active) {
        $pdo->prepare(
            "INSERT INTO sit_in_history 
             (student_id, id_number, fullname, sit_purpose, laboratory, login_time, date) 
             VALUES (?, ?, ?, ?, ?, NOW(), CURDATE())"
        )->execute([
            $student['id'],
            $student['id_number'],
            $_SESSION['fullname'],
            'Self-Service Login',  // Default purpose - user can update later
            '000'  // Default lab - user can specify when logging in
        ]);
    }

    header('Location: Homepage.php');
    exit;
} else {
    header('Location: login.php?error=Invalid+ID+number+or+password');
    exit;
}