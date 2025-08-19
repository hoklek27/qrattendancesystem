<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('dosen');

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$kelas_id = $_GET['kelas_id'] ?? '';

if (empty($kelas_id)) {
    echo json_encode(['success' => false, 'message' => 'Kelas ID tidak valid']);
    exit;
}

try {
    // Verify that this class belongs to the current dosen
    $verify_query = "SELECT id FROM kelas WHERE id = ? AND dosen_id = ?";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute([$kelas_id, $current_user['id']]);
    
    if ($verify_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }
    
    // Get sessions for this class
    $query = "SELECT qs.*, DATE_FORMAT(qs.tanggal, '%d/%m/%Y') as formatted_date
              FROM qr_sessions qs 
              WHERE qs.kelas_id = ? 
              ORDER BY qs.tanggal DESC, qs.jam_mulai DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$kelas_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
