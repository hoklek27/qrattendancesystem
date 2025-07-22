<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('dosen');

header('Content-Type: application/json');

$current_user = getCurrentUser();
$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    echo json_encode(['success' => false, 'message' => 'Session ID required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get QR session data
    $query = "SELECT qs.*, k.nama_kelas, mk.nama_mk, mk.kode_mk
              FROM qr_sessions qs
              JOIN kelas k ON qs.kelas_id = k.id
              JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
              WHERE qs.id = ? AND k.dosen_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id, $current_user['id']]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'QR Session not found']);
        exit;
    }
    
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'session' => $session
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
