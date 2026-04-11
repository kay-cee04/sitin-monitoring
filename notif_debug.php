<?php
session_start();

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;'>";
echo "=== SESSION CHECK ===\n";
echo "logged_in   : " . var_export($_SESSION['logged_in'] ?? 'NOT SET', true) . "\n";
echo "student_id  : " . var_export($_SESSION['student_id'] ?? 'NOT SET', true) . "\n\n";

echo "=== DB CONNECTION ===\n";
$host = 'localhost'; $dbname = 'ccs_sitin'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Connected OK to '$dbname'\n\n";
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit;
}

echo "=== NOTIFICATIONS TABLE ===\n";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    echo "Total rows in notifications: $count\n";
    $rows = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "  id={$r['id']} student_id={$r['student_id']} is_read={$r['is_read']} msg=" . mb_substr($r['message'],0,60) . "\n";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "  → The notifications table might not exist. Run ccs_sitin.sql first.\n";
}

echo "\n=== STUDENTS TABLE ===\n";
try {
    $students = $pdo->query("SELECT id, id_number, firstname, lastname FROM students LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($students as $s) {
        echo "  id={$s['id']} id_number={$s['id_number']} name={$s['firstname']} {$s['lastname']}\n";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== TEST: Insert a notification for student_id=" . ((int)($_SESSION['student_id'] ?? 0)) . " ===\n";
$sid = (int)($_SESSION['student_id'] ?? 0);
if ($sid > 0) {
    try {
        $pdo->prepare("INSERT INTO notifications (student_id, message) VALUES (?, ?)")
            ->execute([$sid, '🧪 Test notification from notif_debug.php at ' . date('H:i:s')]);
        echo "INSERT OK — refresh Homepage.php and check bell!\n";
    } catch (PDOException $e) {
        echo "INSERT FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo "Skipped (not logged in as student)\n";
}

echo "\n=== Done. DELETE notif_debug.php when finished! ===\n";
echo "</pre>";