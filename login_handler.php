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

    header('Location: Homepage.php');
    exit;
} else {
    header('Location: login.php?error=Invalid+ID+number+or+password');
    exit;
}