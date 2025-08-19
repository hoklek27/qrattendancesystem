<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('mahasiswa');

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$qr_code = $input['qr_code'] ?? '';
$status = $input['status'] ?? 'hadir';
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;

// Validate status
$valid_statuses = ['hadir', 'sakit', 'izin'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
    exit;
}

try {
    // Parse QR code (could be JSON or simple string)
    $qr_data = json_decode($qr_code, true);
    if ($qr_data && isset($qr_data['session_id'])) {
        $session_id = $qr_data['session_id'];
    } else {
        // Try to find session by QR code string
        $query = "SELECT id FROM qr_sessions WHERE qr_code = ? AND status = 'active' AND expires_at > NOW()";
        $stmt = $db->prepare($query);
        $stmt->execute([$qr_code]);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'QR Code tidak valid atau sudah expired']);
            exit;
        }
        
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        $session_id = $session['id'];
    }
    
    // Verify QR session exists and is active
    $query = "SELECT qs.*, k.nama_kelas, mk.nama_mk 
              FROM qr_sessions qs
              JOIN kelas k ON qs.kelas_id = k.id
              JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
              WHERE qs.id = ? AND qs.status = 'active' AND qs.expires_at > NOW()";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'QR Code tidak valid atau sudah expired']);
        exit;
    }
    
    $qr_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if student is enrolled in this class
    $query = "SELECT id FROM enrollments WHERE mahasiswa_id = ? AND kelas_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id'], $qr_session['kelas_id']]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak terdaftar di kelas ini']);
        exit;
    }
    
    // Check if already submitted attendance for this session
    $query = "SELECT id, status FROM attendance WHERE qr_session_id = ? AND mahasiswa_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id, $current_user['id']]);
    
    if ($stmt->rowCount() > 0) {
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => false, 
            'message' => 'Anda sudah melakukan absensi untuk sesi ini dengan status: ' . ucfirst($existing['status'])
        ]);
        exit;
    }
    
    // Insert attendance record
    $query = "INSERT INTO attendance (qr_session_id, mahasiswa_id, status, scan_time, latitude, longitude) 
              VALUES (?, ?, ?, NOW(), ?, ?)";
    
    $stmt = $db->prepare($query);
    $success = $stmt->execute([$session_id, $current_user['id'], $status, $latitude, $longitude]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Absensi berhasil dicatat dengan status: ' . ucfirst($status),
            'data' => [
                'mata_kuliah' => $qr_session['nama_mk'],
                'kelas' => $qr_session['nama_kelas'],
                'tanggal' => date('d/m/Y'),
                'waktu' => date('H:i:s'),
                'status' => ucfirst($status)
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data absensi']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
