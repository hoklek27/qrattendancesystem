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
    echo json_encode(['success' => false, 'error' => 'Kelas ID required']);
    exit;
}

try {
    // Verify that this class belongs to the current dosen
    $verify_query = "SELECT id FROM kelas WHERE id = ? AND dosen_id = ?";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute([$kelas_id, $current_user['id']]);
    
    if ($verify_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit;
    }
    
    // Get sessions for the class
    $query = "SELECT 
                id,
                tanggal,
                jam_mulai,
                jam_selesai,
                DATE_FORMAT(tanggal, '%d/%m/%Y') as formatted_date
              FROM qr_sessions 
              WHERE kelas_id = ? 
              ORDER BY tanggal DESC, jam_mulai DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$kelas_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
