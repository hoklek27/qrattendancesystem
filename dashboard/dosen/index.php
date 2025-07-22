<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('dosen');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Get dosen's classes
$query = "SELECT k.*, mk.nama_mk, mk.kode_mk 
          FROM kelas k 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          WHERE k.dosen_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's sessions
$query = "SELECT qs.*, k.nama_kelas, mk.nama_mk 
          FROM qr_sessions qs 
          JOIN kelas k ON qs.kelas_id = k.id 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          WHERE qs.dosen_id = ? AND DATE(qs.tanggal) = CURDATE() 
          ORDER BY qs.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$today_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

// Total classes
$stats['total_classes'] = count($classes);

// Today's attendance count
$query = "SELECT COUNT(*) as total 
          FROM attendance a 
          JOIN qr_sessions qs ON a.qr_session_id = qs.id 
          WHERE qs.dosen_id = ? AND DATE(qs.tanggal) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$stats['today_attendance'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active sessions
$query = "SELECT COUNT(*) as total 
          FROM qr_sessions 
          WHERE dosen_id = ? AND status = 'active' AND expires_at > NOW()";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$stats['active_sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen - Sistem Absensi QR Code</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Dashboard Dosen</h1>
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
                        <div class="stat-number"><?php echo $stats['total_classes']; ?></div>
                        <div class="stat-label">Total Kelas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['today_attendance']; ?></div>
                        <div class="stat-label">Absensi Hari Ini</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['active_sessions']; ?></div>
                        <div class="stat-label">Sesi Aktif</div>
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
                                <h3>Buat QR Code</h3>
                                <p>Generate QR Code untuk absensi kelas</p>
                                <a href="generate-qr.php" class="btn btn-primary" style="margin-top: 15px;">Buat QR</a>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">👥</div>
                                <h3>Lihat Kehadiran</h3>
                                <p>Monitor kehadiran mahasiswa real-time</p>
                                <a href="attendance.php" class="btn btn-primary" style="margin-top: 15px;">Lihat</a>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">📊</div>
                                <h3>Laporan Kelas</h3>
                                <p>Laporan kehadiran per kelas</p>
                                <a href="reports.php" class="btn btn-primary" style="margin-top: 15px;">Laporan</a>
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
                                        <th>Ruangan</th>
                                        <th>Hari</th>
                                        <th>Jam</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes as $class): ?>
                                        <tr>
                                            <td><?php echo $class['kode_mk']; ?></td>
                                            <td><?php echo $class['nama_mk']; ?></td>
                                            <td><?php echo $class['nama_kelas']; ?></td>
                                            <td><?php echo $class['ruangan']; ?></td>
                                            <td><?php echo $class['hari']; ?></td>
                                            <td><?php echo date('H:i', strtotime($class['jam_mulai'])) . ' - ' . date('H:i', strtotime($class['jam_selesai'])); ?></td>
                                            <td>
                                                <a href="generate-qr.php?class_id=<?php echo $class['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">QR Code</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Anda belum memiliki kelas yang diampu.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Today's Sessions -->
                <?php if (count($today_sessions) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Sesi Hari Ini</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Mata Kuliah</th>
                                    <th>Kelas</th>
                                    <th>Status</th>
                                    <th>Dibuat</th>
                                    <th>Berakhir</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_sessions as $session): ?>
                                    <tr>
                                        <td><?php echo $session['nama_mk']; ?></td>
                                        <td><?php echo $session['nama_kelas']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $session['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('H:i:s', strtotime($session['created_at'])); ?></td>
                                        <td><?php echo date('H:i:s', strtotime($session['expires_at'])); ?></td>
                                        <td>
                                            <a href="view-qr.php?session_id=<?php echo $session['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">Lihat QR</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
