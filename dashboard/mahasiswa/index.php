<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('mahasiswa');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Get mahasiswa's enrolled classes
$query = "SELECT k.*, mk.nama_mk, mk.kode_mk, u.full_name as dosen_name 
          FROM enrollments e 
          JOIN kelas k ON e.kelas_id = k.id 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          JOIN users u ON k.dosen_id = u.id 
          WHERE e.mahasiswa_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent attendance
$query = "SELECT a.*, qs.tanggal, k.nama_kelas, mk.nama_mk 
          FROM attendance a 
          JOIN qr_sessions qs ON a.qr_session_id = qs.id 
          JOIN kelas k ON qs.kelas_id = k.id 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          WHERE a.mahasiswa_id = ? 
          ORDER BY a.scan_time DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

// Total enrolled classes
$stats['total_classes'] = count($classes);

// Total attendance this month
$query = "SELECT COUNT(*) as total 
          FROM attendance a 
          JOIN qr_sessions qs ON a.qr_session_id = qs.id 
          WHERE a.mahasiswa_id = ? AND MONTH(qs.tanggal) = MONTH(CURDATE()) AND YEAR(qs.tanggal) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$stats['monthly_attendance'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Present count this month
$query = "SELECT COUNT(*) as total 
          FROM attendance a 
          JOIN qr_sessions qs ON a.qr_session_id = qs.id 
          WHERE a.mahasiswa_id = ? AND a.status = 'hadir' AND MONTH(qs.tanggal) = MONTH(CURDATE()) AND YEAR(qs.tanggal) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$stats['present_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate attendance percentage
$stats['attendance_percentage'] = $stats['monthly_attendance'] > 0 ? round(($stats['present_count'] / $stats['monthly_attendance']) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa - Sistem Absensi QR Code</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Dashboard Mahasiswa</h1>
                    <div class="user-info">
                        <span>Selamat datang, <?php echo $current_user['full_name']; ?> (<?php echo $current_user['nim_nip']; ?>)</span>
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
                        <div class="stat-number"><?php echo $stats['total_classes']; ?></div>
                        <div class="stat-label">Kelas Diambil</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['monthly_attendance']; ?></div>
                        <div class="stat-label">Absensi Bulan Ini</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['present_count']; ?></div>
                        <div class="stat-label">Hadir Bulan Ini</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['attendance_percentage']; ?>%</div>
                        <div class="stat-label">Persentase Kehadiran</div>
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
                                <div class="feature-icon">📱</div>
                                <h3>Scan QR Code</h3>
                                <p>Scan QR Code untuk absensi kelas</p>
                                <a href="scan-qr.php" class="btn btn-primary" style="margin-top: 15px;">Scan QR</a>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">📊</div>
                                <h3>Riwayat Absensi</h3>
                                <p>Lihat riwayat kehadiran Anda</p>
                                <a href="attendance-history.php" class="btn btn-primary" style="margin-top: 15px;">Lihat Riwayat</a>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">📚</div>
                                <h3>Jadwal Kelas</h3>
                                <p>Lihat jadwal kelas yang diambil</p>
                                <a href="schedule.php" class="btn btn-primary" style="margin-top: 15px;">Lihat Jadwal</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- My Classes -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Kelas Saya</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($classes) > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Kode MK</th>
                                        <th>Mata Kuliah</th>
                                        <th>Kelas</th>
                                        <th>Dosen</th>
                                        <th>Ruangan</th>
                                        <th>Hari</th>
                                        <th>Jam</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes as $class): ?>
                                        <tr>
                                            <td><?php echo $class['kode_mk']; ?></td>
                                            <td><?php echo $class['nama_mk']; ?></td>
                                            <td><?php echo $class['nama_kelas']; ?></td>
                                            <td><?php echo $class['dosen_name']; ?></td>
                                            <td><?php echo $class['ruangan']; ?></td>
                                            <td><?php echo $class['hari']; ?></td>
                                            <td><?php echo date('H:i', strtotime($class['jam_mulai'])) . ' - ' . date('H:i', strtotime($class['jam_selesai'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Anda belum terdaftar di kelas manapun. Silakan hubungi admin untuk mendaftar kelas.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Attendance -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Riwayat Absensi Terbaru</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_attendance) > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr>
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
                            <p>Belum ada riwayat absensi.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
