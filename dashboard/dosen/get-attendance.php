<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('dosen');

header('Content-Type: application/json');

$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    echo json_encode([]);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Verify session belongs to current dosen
$query = "SELECT id FROM qr_sessions WHERE id = ? AND dosen_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$session_id, $current_user['id']]);

if ($stmt->rowCount() === 0) {
    echo json_encode([]);
    exit();
}

// Get attendance records
$query = "SELECT a.*, u.full_name, u.nim_nip 
          FROM attendance a 
          JOIN users u ON a.mahasiswa_id = u.id 
          WHERE a.qr_session_id = ? 
          ORDER BY a.scan_time DESC";
$stmt = $db->prepare($query);
$stmt->execute([$session_id]);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($attendance_records);
?>
