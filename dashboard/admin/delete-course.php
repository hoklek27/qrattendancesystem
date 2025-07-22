<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$course_id = $_GET['id'] ?? 0;

if (!$course_id) {
    header('Location: courses.php?error=' . urlencode('ID mata kuliah tidak valid'));
    exit();
}

// Get course data first
$query = "SELECT mk.*, u.full_name as dosen_name 
          FROM mata_kuliah mk 
          LEFT JOIN users u ON mk.dosen_id = u.id 
          WHERE mk.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$course_id]);

if ($stmt->rowCount() === 0) {
    header('Location: courses.php?error=' . urlencode('Mata kuliah tidak ditemukan'));
    exit();
}

$course = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if course has related data
$has_related_data = false;
$related_info = [];

// Check kelas
$query = "SELECT COUNT(*) as count FROM kelas WHERE mata_kuliah_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$course_id]);
$kelas_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($kelas_count > 0) {
    $has_related_data = true;
    $related_info[] = "$kelas_count kelas";
}

// Check QR sessions through kelas
$query = "SELECT COUNT(*) as count FROM qr_sessions qs 
          JOIN kelas k ON qs.kelas_id = k.id 
          WHERE k.mata_kuliah_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$course_id]);
$session_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($session_count > 0) {
    $has_related_data = true;
    $related_info[] = "$session_count sesi QR";
}

// Check attendance records through QR sessions and kelas
$query = "SELECT COUNT(*) as count FROM attendance a 
          JOIN qr_sessions qs ON a.qr_session_id = qs.id 
          JOIN kelas k ON qs.kelas_id = k.id 
          WHERE k.mata_kuliah_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$course_id]);
$attendance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($attendance_count > 0) {
    $has_related_data = true;
    $related_info[] = "$attendance_count record kehadiran";
}

// Handle confirmation
if (isset($_POST['confirm_delete'])) {
    try {
        $db->beginTransaction();
        
        // Delete attendance records first (through QR sessions and kelas)
        $query = "DELETE a FROM attendance a 
                  JOIN qr_sessions qs ON a.qr_session_id = qs.id 
                  JOIN kelas k ON qs.kelas_id = k.id 
                  WHERE k.mata_kuliah_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$course_id]);
        
        // Delete QR sessions (through kelas)
        $query = "DELETE qs FROM qr_sessions qs 
                  JOIN kelas k ON qs.kelas_id = k.id 
                  WHERE k.mata_kuliah_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$course_id]);
        
        // Delete enrollments (through kelas)
        $query = "DELETE e FROM enrollments e 
                  JOIN kelas k ON e.kelas_id = k.id 
                  WHERE k.mata_kuliah_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$course_id]);
        
        // Delete kelas
        $query = "DELETE FROM kelas WHERE mata_kuliah_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$course_id]);
        
        // Delete the mata kuliah
        $query = "DELETE FROM mata_kuliah WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$course_id]);
        
        $db->commit();
        
        header('Location: courses.php?success=' . urlencode('Mata kuliah berhasil dihapus'));
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Mata Kuliah - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Hapus Mata Kuliah</h1>
                    <div class="user-info">
                        <a href="courses.php" style="color: #2d5a27; text-decoration: none; margin-right: 15px;">← Kembali</a>
                        <span><?php echo $current_user['full_name']; ?></span>
                        <a href="../../logout.php" class="logout-btn">Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="container">
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Course Info -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Mata Kuliah yang akan Dihapus</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <strong>ID:</strong><br>
                                <?php echo $course['id']; ?>
                            </div>
                            <div>
                                <strong>Kode MK:</strong><br>
                                <?php echo $course['kode_mk']; ?>
                            </div>
                            <div>
                                <strong>Nama Mata Kuliah:</strong><br>
                                <?php echo $course['nama_mk']; ?>
                            </div>
                            <div>
                                <strong>SKS:</strong><br>
                                <?php echo $course['sks']; ?>
                            </div>
                            <div>
                                <strong>Semester:</strong><br>
                                <?php echo $course['semester']; ?>
                            </div>
                            <div>
                                <strong>Dosen Pengampu:</strong><br>
                                <?php echo $course['dosen_name'] ?? '-'; ?>
                            </div>
                            <div>
                                <strong>Dibuat:</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($course['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Warning Card -->
                <div class="card" style="margin-top: 20px; border-left: 4px solid #dc3545;">
                    <div class="card-header" style="background: #f8d7da; color: #721c24;">
                        <h3 class="card-title">⚠️ Peringatan Penghapusan</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($has_related_data): ?>
                            <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                <strong>🔗 Mata kuliah ini memiliki data terkait:</strong>
                                <ul style="margin-top: 10px; margin-bottom: 0;">
                                    <?php foreach ($related_info as $info): ?>
                                        <li><?php echo $info; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <p><strong>Dampak penghapusan:</strong></p>
                            <div style="background: #f8d7da; padding: 15px; border-radius: 8px;">
                                <strong>Semua data berikut akan dihapus secara permanen:</strong>
                                <ul style="margin-top: 10px; margin-bottom: 0;">
                                    <li>Semua kelas dari mata kuliah ini</li>
                                    <li>Pendaftaran mahasiswa di semua kelas</li>
                                    <li>Semua sesi QR Code yang pernah dibuat</li>
                                    <li>Semua record kehadiran mahasiswa</li>
                                    <li>Data mata kuliah itu sendiri</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <p>Mata kuliah ini tidak memiliki data terkait. Penghapusan dapat dilakukan dengan aman.</p>
                        <?php endif; ?>
                        
                        <div style="background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin-top: 20px;">
                            <strong>📝 Catatan:</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                <li>Penghapusan mata kuliah bersifat permanen dan tidak dapat dibatalkan</li>
                                <li>Pastikan Anda telah membackup data jika diperlukan</li>
                                <li>Ini akan menghapus SEMUA kelas yang menggunakan mata kuliah ini</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Confirmation Form -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Konfirmasi Penghapusan</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="confirm_delete" value="1" required style="margin-right: 10px;">
                                    Saya memahami bahwa tindakan ini akan menghapus mata kuliah beserta semua kelas dan data terkait dan tidak dapat dibatalkan
                                </label>
                            </div>
                            
                            <div style="margin-top: 30px; display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-secondary" style="background: #dc3545; border-color: #dc3545;" onclick="return confirm('Apakah Anda benar-benar yakin ingin menghapus mata kuliah ini beserta semua data terkait?')">
                                    🗑️ Hapus Mata Kuliah
                                </button>
                                <a href="courses.php" class="btn btn-primary">
                                    ← Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
