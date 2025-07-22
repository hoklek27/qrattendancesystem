<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$user_id = $_GET['id'] ?? 0;

if (!$user_id) {
    header('Location: users.php?error=' . urlencode('ID user tidak valid'));
    exit();
}

// Prevent deleting self
if ($user_id == $current_user['id']) {
    header('Location: users.php?error=' . urlencode('Anda tidak dapat menghapus akun Anda sendiri'));
    exit();
}

// Get user data first
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);

if ($stmt->rowCount() === 0) {
    header('Location: users.php?error=' . urlencode('User tidak ditemukan'));
    exit();
}

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has related data
$has_related_data = false;
$related_info = [];

// Check if user is a dosen with mata kuliah
if ($user['role'] === 'dosen') {
    $query = "SELECT COUNT(*) as count FROM mata_kuliah WHERE dosen_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $mk_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($mk_count > 0) {
        $has_related_data = true;
        $related_info[] = "$mk_count mata kuliah";
    }
    
    // Check if user is a dosen with kelas
    $query = "SELECT COUNT(*) as count FROM kelas WHERE dosen_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $kelas_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($kelas_count > 0) {
        $has_related_data = true;
        $related_info[] = "$kelas_count kelas";
    }
    
    // Check if user has QR sessions
    $query = "SELECT COUNT(*) as count FROM qr_sessions WHERE dosen_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $session_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($session_count > 0) {
        $has_related_data = true;
        $related_info[] = "$session_count sesi QR";
    }
}

// Check if user is a mahasiswa with enrollments
if ($user['role'] === 'mahasiswa') {
    $query = "SELECT COUNT(*) as count FROM enrollments WHERE mahasiswa_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $enrollment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($enrollment_count > 0) {
        $has_related_data = true;
        $related_info[] = "$enrollment_count pendaftaran kelas";
    }
    
    // Check if user has attendance records
    $query = "SELECT COUNT(*) as count FROM attendance WHERE mahasiswa_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $attendance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($attendance_count > 0) {
        $has_related_data = true;
        $related_info[] = "$attendance_count record kehadiran";
    }
}

// Handle confirmation
if (isset($_POST['confirm_delete'])) {
    $force_delete = isset($_POST['force_delete']);
    
    try {
        $db->beginTransaction();
        
        if ($force_delete) {
            // Delete related data first
            if ($user['role'] === 'dosen') {
                // Update mata kuliah to remove dosen reference
                $query = "UPDATE mata_kuliah SET dosen_id = NULL WHERE dosen_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                
                // Update kelas to remove dosen reference
                $query = "UPDATE kelas SET dosen_id = NULL WHERE dosen_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                
                // Delete QR sessions (this will cascade to attendance)
                $query = "DELETE FROM qr_sessions WHERE dosen_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
            }
            
            if ($user['role'] === 'mahasiswa') {
                // Delete attendance records
                $query = "DELETE FROM attendance WHERE mahasiswa_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                
                // Delete enrollments
                $query = "DELETE FROM enrollments WHERE mahasiswa_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
            }
        }
        
        // Delete the user
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        $db->commit();
        
        header('Location: users.php?success=' . urlencode('User berhasil dihapus'));
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
    <title>Hapus User - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Hapus User</h1>
                    <div class="user-info">
                        <a href="users.php" style="color: #2d5a27; text-decoration: none; margin-right: 15px;">← Kembali</a>
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
                
                <!-- User Info -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi User yang akan Dihapus</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <strong>ID:</strong><br>
                                <?php echo $user['id']; ?>
                            </div>
                            <div>
                                <strong>Username:</strong><br>
                                <?php echo $user['username']; ?>
                            </div>
                            <div>
                                <strong>Nama Lengkap:</strong><br>
                                <?php echo $user['full_name']; ?>
                            </div>
                            <div>
                                <strong>Email:</strong><br>
                                <?php echo $user['email']; ?>
                            </div>
                            <div>
                                <strong>Role:</strong><br>
                                <span class="badge badge-<?php echo $user['role'] == 'admin' ? 'success' : ($user['role'] == 'dosen' ? 'warning' : 'primary'); ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                            <div>
                                <strong>NIM/NIP:</strong><br>
                                <?php echo $user['nim_nip'] ?? '-'; ?>
                            </div>
                            <div>
                                <strong>Terdaftar:</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
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
                                <strong>🔗 User ini memiliki data terkait:</strong>
                                <ul style="margin-top: 10px; margin-bottom: 0;">
                                    <?php foreach ($related_info as $info): ?>
                                        <li><?php echo $info; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <p><strong>Pilihan penghapusan:</strong></p>
                            <div style="margin: 20px 0;">
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                                    <strong>1. Penghapusan Normal:</strong><br>
                                    Hanya menghapus user, data terkait akan tetap ada tetapi referensi ke user akan dihapus/di-null.
                                </div>
                                <div style="background: #f8d7da; padding: 15px; border-radius: 8px;">
                                    <strong>2. Penghapusan Paksa:</strong><br>
                                    Menghapus user beserta SEMUA data terkait. <strong>Tindakan ini tidak dapat dibatalkan!</strong>
                                </div>
                            </div>
                        <?php else: ?>
                            <p>User ini tidak memiliki data terkait. Penghapusan dapat dilakukan dengan aman.</p>
                        <?php endif; ?>
                        
                        <div style="background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin-top: 20px;">
                            <strong>📝 Catatan:</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                <li>Penghapusan user bersifat permanen dan tidak dapat dibatalkan</li>
                                <li>Pastikan Anda telah membackup data jika diperlukan</li>
                                <li>Anda tidak dapat menghapus akun Anda sendiri</li>
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
                            <?php if ($has_related_data): ?>
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="force_delete" value="1" style="margin-right: 10px;">
                                        <strong>Hapus Paksa</strong> - Hapus user beserta semua data terkait
                                    </label>
                                    <p style="font-size: 0.9rem; color: #666; margin-top: 5px;">
                                        Jika tidak dicentang, hanya user yang akan dihapus dan data terkait akan tetap ada.
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="confirm_delete" value="1" required style="margin-right: 10px;">
                                    Saya memahami bahwa tindakan ini tidak dapat dibatalkan
                                </label>
                            </div>
                            
                            <div style="margin-top: 30px; display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-secondary" style="background: #dc3545; border-color: #dc3545;" onclick="return confirm('Apakah Anda benar-benar yakin ingin menghapus user ini?')">
                                    🗑️ Hapus User
                                </button>
                                <a href="users.php" class="btn btn-primary">
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
