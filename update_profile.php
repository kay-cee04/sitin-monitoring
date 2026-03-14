<?php
// ============================================================
//  update_profile.php
//  Handles ALL profile save actions (info + photo upload)
//  Called via POST from profile.php
// ============================================================

session_start();

// Must be logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

$action = $_POST['action'] ?? '';

// ============================================================
//  ACTION 1 — Save personal info
// ============================================================
if ($action === 'save_info') {

    $lastname   = trim($_POST['lastname']   ?? '');
    $firstname  = trim($_POST['firstname']  ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $email      = trim($_POST['email']      ?? '');
    $address    = trim($_POST['address']    ?? '');
    $course     = trim($_POST['course']     ?? '');
    $year_level = (int)($_POST['year_level'] ?? 1);
    $new_pw     = $_POST['new_password']     ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    // ── Validation ──────────────────────────────────────────
    if (empty($lastname) || empty($firstname) || empty($email) || empty($course)) {
        $_SESSION['profile_error'] = 'First name, last name, email, and course are required.';
        header('Location: profile.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['profile_error'] = 'Please enter a valid email address.';
        header('Location: profile.php');
        exit;
    }

    if (!empty($new_pw) && strlen($new_pw) < 6) {
        $_SESSION['profile_error'] = 'New password must be at least 6 characters long.';
        header('Location: profile.php');
        exit;
    }

    if (!empty($new_pw) && $new_pw !== $confirm_pw) {
        $_SESSION['profile_error'] = 'Passwords do not match. Please try again.';
        header('Location: profile.php');
        exit;
    }

    // ── Check email uniqueness ───────────────────────────────
    $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id != ? LIMIT 1");
    $stmt->execute([$email, $_SESSION['student_id']]);
    if ($stmt->fetch()) {
        $_SESSION['profile_error'] = 'That email address is already registered to another account.';
        header('Location: profile.php');
        exit;
    }

    // ── Run UPDATE ───────────────────────────────────────────
    try {
        if (!empty($new_pw)) {
            $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
            $pdo->prepare("
                UPDATE students
                SET lastname=?, firstname=?, middlename=?, email=?,
                    address=?, course=?, year_level=?, password=?
                WHERE id=?
            ")->execute([
                $lastname, $firstname, $middlename, $email,
                $address, $course, $year_level, $hashed,
                $_SESSION['student_id']
            ]);
        } else {
            $pdo->prepare("
                UPDATE students
                SET lastname=?, firstname=?, middlename=?, email=?,
                    address=?, course=?, year_level=?
                WHERE id=?
            ")->execute([
                $lastname, $firstname, $middlename, $email,
                $address, $course, $year_level,
                $_SESSION['student_id']
            ]);
        }

        // ── Refresh session ──────────────────────────────────
        $_SESSION['lastname']   = $lastname;
        $_SESSION['firstname']  = $firstname;
        $_SESSION['middlename'] = $middlename;
        $_SESSION['fullname']   = trim($firstname . ' ' . $middlename . ' ' . $lastname);
        $_SESSION['email']      = $email;
        $_SESSION['address']    = $address;
        $_SESSION['course']     = $course;
        $_SESSION['year_level'] = $year_level;

        $_SESSION['profile_success'] = 'Profile information updated successfully!';

    } catch (PDOException $e) {
        $_SESSION['profile_error'] = 'Database error: ' . htmlspecialchars($e->getMessage());
    }

    header('Location: profile.php');
    exit;
}

// ============================================================
//  ACTION 2 — Upload profile photo
// ============================================================
if ($action === 'upload_photo') {

    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['profile_error'] = 'No file received or an upload error occurred.';
        header('Location: profile.php');
        exit;
    }

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // Detect real MIME type (not just extension)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES['profile_photo']['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed)) {
        $_SESSION['profile_error'] = 'Invalid file type. Only JPG, PNG, GIF, or WEBP images are allowed.';
        header('Location: profile.php');
        exit;
    }

    if ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
        $_SESSION['profile_error'] = 'File is too large. Maximum size is 2 MB.';
        header('Location: profile.php');
        exit;
    }

    // Build safe filename
    $ext      = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
    $filename = 'profile_' . (int)$_SESSION['student_id'] . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/uploads/profiles/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $dest = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dest)) {
        $_SESSION['profile_error'] = 'Could not save the file. Check folder permissions on uploads/profiles/.';
        header('Location: profile.php');
        exit;
    }

    // Delete the previous photo file if it exists
    if (!empty($_SESSION['profile_photo'])) {
        $oldFile = $uploadDir . $_SESSION['profile_photo'];
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }

    // Save filename to DB (create column if it doesn't exist yet)
    try {
        $pdo->prepare("UPDATE students SET profile_photo = ? WHERE id = ?")
            ->execute([$filename, $_SESSION['student_id']]);
    } catch (PDOException $e) {
        // Column probably missing — add it then retry
        try {
            $pdo->exec("ALTER TABLE students ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL");
            $pdo->prepare("UPDATE students SET profile_photo = ? WHERE id = ?")
                ->execute([$filename, $_SESSION['student_id']]);
        } catch (PDOException $e2) {
            $_SESSION['profile_error'] = 'Could not save photo to database: ' . htmlspecialchars($e2->getMessage());
            header('Location: profile.php');
            exit;
        }
    }

    $_SESSION['profile_photo']   = $filename;
    $_SESSION['profile_success'] = 'Profile photo updated successfully!';

    header('Location: profile.php');
    exit;
}

// ============================================================
//  Fallback — unknown action
// ============================================================
header('Location: profile.php');
exit;