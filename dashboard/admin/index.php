<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total users
$query = "SELECT COUNT(*) as total FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total dosen
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'dosen'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_dosen'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total mahasiswa
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'mahasiswa'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_mahasiswa'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total mata kuliah
$query = "SELECT COUNT(*) as total FROM mata_kuliah";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_mk'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent activities
$query = "SELECT a.*, u.full_name, qs.tanggal, k.nama_kelas, mk.nama_mk 
          FROM attendance a 
          JOIN users u ON a.mahasiswa_id = u.id 
          JOIN qr_sessions qs ON a.qr_session_id = qs.id 
          JOIN kelas k ON qs.kelas_id = k.id 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          ORDER BY a.scan_time DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Absensi QR Code</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Dashboard Admin</h1>
                    <div class="user-info">
                        <span>Selamat datang, <?php echo $current_user['full_name']; ?></span>
                        <a href="../../logout.php" class="logout-btn">Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="container">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                        <div class="stat-label">Total Pengguna</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_dosen']; ?></div>
                        <div class="stat-label">Total Dosen</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_mahasiswa']; ?></div>
                        <div class="stat-label">Total Mahasiswa</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_mk']; ?></div>
                        <div class="stat-label">Mata Kuliah</div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Menu Utama</h3>
                    </div>
                    <div class="card-body">
                        <div class="features-grid">
                            <div class="feature-card">
                                <div class="feature-icon">👥</div>
                                <h3>Kelola Pengguna</h3>
                                <p>Tambah, edit, dan hapus pengguna sistem</p>
                                <a href="users.php" class="btn btn-primary" style="margin-top: 15px;">Kelola</a>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">📚</div>
                                <h3>Mata Kuliah</h3>
                                <p>Kelola mata kuliah dan kelas</p>
                                <a href="courses.php" class="btn btn-primary" style="margin-top: 15px;">Kelola</a>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">📊</div>
                                <h3>Laporan</h3>
                                <p>Lihat laporan kehadiran mahasiswa</p>
                                <a href="reports.php" class="btn btn-primary" style="margin-top: 15px;">Lihat</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Aktivitas Terbaru</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_attendance) > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Mahasiswa</th>
                                        <th>Mata Kuliah</th>
                                        <th>Kelas</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                        <th>Waktu Scan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_attendance as $attendance): ?>
                                        <tr>
                                            <td><?php echo $attendance['full_name']; ?></td>
                                            <td><?php echo $attendance['nama_mk']; ?></td>
                                            <td><?php echo $attendance['nama_kelas']; ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $attendance['status'] == 'hadir' ? 'success' : ($attendance['status'] == 'sakit' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($attendance['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($attendance['tanggal'])); ?></td>
                                            <td><?php echo date('H:i:s', strtotime($attendance['scan_time'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Belum ada aktivitas absensi.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
