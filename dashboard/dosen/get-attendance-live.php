<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is dosen
if (!isLoggedIn() || getCurrentUser()['role'] !== 'dosen') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    echo json_encode(['success' => false, 'error' => 'Session ID required']);
    exit;
}

try {
    // Verify that this QR session belongs to the current dosen
    $verify_query = "SELECT qs.id 
                     FROM qr_sessions qs
                     JOIN kelas k ON qs.kelas_id = k.id
                     WHERE qs.id = ? AND k.dosen_id = ?";
    
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute([$session_id, $current_user['id']]);
    
    if ($verify_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'QR session not found']);
        exit;
    }
    
    // Get total students in the class
    $total_query = "SELECT COUNT(DISTINCT e.mahasiswa_id) as total_students
                    FROM qr_sessions qs
                    JOIN kelas k ON qs.kelas_id = k.id
                    JOIN enrollments e ON k.id = e.kelas_id
                    WHERE qs.id = ?";
    
    $total_stmt = $db->prepare($total_query);
    $total_stmt->execute([$session_id]);
    $total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);
    $total_students = $total_result['total_students'];
    
    // Get attendance count
    $count_query = "SELECT COUNT(DISTINCT a.mahasiswa_id) as attendance_count
                    FROM attendance a
                    WHERE a.qr_session_id = ?";
    
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute([$session_id]);
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $attendance_count = $count_result['attendance_count'];
    
    // Get attendance list
    $attendance_query = "SELECT 
                          a.*,
                          u.full_name,
                          u.username as nim
                        FROM attendance a
                        JOIN users u ON a.mahasiswa_id = u.id
                        WHERE a.qr_session_id = ?
                        ORDER BY a.tanggal_hadir DESC";
    
    $attendance_stmt = $db->prepare($attendance_query);
    $attendance_stmt->execute([$session_id]);
    $attendance_list = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the attendance list for JSON response
    $formatted_attendance = [];
    foreach ($attendance_list as $attendance) {
        $formatted_attendance[] = [
            'full_name' => $attendance['full_name'],
            'nim' => $attendance['nim'],
            'tanggal_hadir' => $attendance['tanggal_hadir']
        ];
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'total_students' => (int)$total_students,
        'attendance_count' => (int)$attendance_count,
        'attendance_list' => $formatted_attendance,
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
