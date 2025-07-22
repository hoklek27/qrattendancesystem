<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$class_id = $_GET['id'] ?? 0;

if (!$class_id) {
    header('Location: courses.php?error=' . urlencode('ID kelas tidak valid'));
    exit();
}

// Get class data first
$query = "SELECT k.*, mk.nama_mk, mk.kode_mk, u.full_name as dosen_name 
          FROM kelas k 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          LEFT JOIN users u ON k.dosen_id = u.id 
          WHERE k.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$class_id]);

if ($stmt->rowCount() === 0) {
    header('Location: courses.php?error=' . urlencode('Kelas tidak ditemukan'));
    exit();
}

$class = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if class has related data
$has_related_data = false;
$related_info = [];

// Check enrollments
$query = "SELECT COUNT(*) as count FROM enrollments WHERE kelas_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$class_id]);
$enrollment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($enrollment_count > 0) {
    $has_related_data = true;
    $related_info[] = "$enrollment_count mahasiswa terdaftar";
}

// Check QR sessions
$query = "SELECT COUNT(*) as count FROM qr_sessions WHERE kelas_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$class_id]);
$session_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($session_count > 0) {
    $has_related_data = true;
    $related_info[] = "$session_count sesi QR";
}

// Check attendance records through QR sessions
$query = "SELECT COUNT(*) as count FROM attendance a 
          JOIN qr_sessions qs ON a.qr_session_id = qs.id 
          WHERE qs.kelas_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$class_id]);
$attendance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($attendance_count > 0) {
    $has_related_data = true;
    $related_info[] = "$attendance_count record kehadiran";
}

// Handle confirmation
if (isset($_POST['confirm_delete'])) {
    try {
        $db->beginTransaction();
        
        // Delete attendance records first (through QR sessions)
        $query = "DELETE a FROM attendance a 
                  JOIN qr_sessions qs ON a.qr_session_id = qs.id 
                  WHERE qs.kelas_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$class_id]);
        
        // Delete QR sessions
        $query = "DELETE FROM qr_sessions WHERE kelas_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$class_id]);
        
        // Delete enrollments
        $query = "DELETE FROM enrollments WHERE kelas_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$class_id]);
        
        // Delete the class
        $query = "DELETE FROM kelas WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$class_id]);
        
        $db->commit();
        
        header('Location: courses.php?success=' . urlencode('Kelas berhasil dihapus'));
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
    <title>Hapus Kelas - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Hapus Kelas</h1>
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
                
                <!-- Class Info -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Kelas yang akan Dihapus</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <strong>ID:</strong><br>
                                <?php echo $class['id']; ?>
                            </div>
                            <div>
                                <strong>Nama Kelas:</strong><br>
                                <?php echo $class['nama_kelas']; ?>
                            </div>
                            <div>
                                <strong>Mata Kuliah:</strong><br>
                                <?php echo $class['kode_mk'] . ' - ' . $class['nama_mk']; ?>
                            </div>
                            <div>
                                <strong>Dosen:</strong><br>
                                <?php echo $class['dosen_name'] ?? '-'; ?>
                            </div>
                            <div>
                                <strong>Ruangan:</strong><br>
                                <?php echo $class['ruangan']; ?>
                            </div>
                            <div>
                                <strong>Jadwal:</strong><br>
                                <?php echo $class['hari'] . ', ' . date('H:i', strtotime($class['jam_mulai'])) . ' - ' . date('H:i', strtotime($class['jam_selesai'])); ?>
                            </div>
                            <div>
                                <strong>Dibuat:</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($class['created_at'])); ?>
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
                                <strong>🔗 Kelas ini memiliki data terkait:</strong>
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
                                    <li>Pendaftaran mahasiswa di kelas ini</li>
                                    <li>Semua sesi QR Code yang pernah dibuat</li>
                                    <li>Semua record kehadiran mahasiswa</li>
                                    <li>Data kelas itu sendiri</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <p>Kelas ini tidak memiliki data terkait. Penghapusan dapat dilakukan dengan aman.</p>
                        <?php endif; ?>
                        
                        <div style="background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin-top: 20px;">
                            <strong>📝 Catatan:</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                <li>Penghapusan kelas bersifat permanen dan tidak dapat dibatalkan</li>
                                <li>Pastikan Anda telah membackup data jika diperlukan</li>
                                <li>Mata kuliah tidak akan terhapus, hanya kelas ini saja</li>
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
                                    Saya memahami bahwa tindakan ini akan menghapus kelas beserta semua data terkait dan tidak dapat dibatalkan
                                </label>
                            </div>
                            
                            <div style="margin-top: 30px; display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-secondary" style="background: #dc3545; border-color: #dc3545;" onclick="return confirm('Apakah Anda benar-benar yakin ingin menghapus kelas ini beserta semua data terkait?')">
                                    🗑️ Hapus Kelas
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
