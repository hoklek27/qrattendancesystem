<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('mahasiswa');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Get student's class schedule
$query = "SELECT 
            k.*,
            mk.nama_mk,
            mk.kode_mk,
            mk.sks,
            u.full_name as dosen_name
          FROM enrollments e
          JOIN kelas k ON e.kelas_id = k.id
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
          JOIN users u ON k.dosen_id = u.id
          WHERE e.mahasiswa_id = ?
          ORDER BY 
            CASE k.hari 
                WHEN 'Senin' THEN 1
                WHEN 'Selasa' THEN 2
                WHEN 'Rabu' THEN 3
                WHEN 'Kamis' THEN 4
                WHEN 'Jumat' THEN 5
                WHEN 'Sabtu' THEN 6
                WHEN 'Minggu' THEN 7
            END,
            k.jam_mulai";

$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group schedule by day
$days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$schedule_by_day = [];

foreach ($days as $day) {
    $schedule_by_day[$day] = array_filter($schedule, function($class) use ($day) {
        return $class['hari'] === $day;
    });
}

// Get today's schedule
$today = date('N'); // 1 = Monday, 7 = Sunday
$day_names = [
    1 => 'Senin',
    2 => 'Selasa', 
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu'
];
$today_name = $day_names[$today];
$today_schedule = $schedule_by_day[$today_name] ?? [];

// Get recent QR sessions for today's classes
$today_qr_sessions = [];
if (!empty($today_schedule)) {
    $class_ids = array_column($today_schedule, 'id');
    $placeholders = str_repeat('?,', count($class_ids) - 1) . '?';
    
    $query = "SELECT qs.*, k.nama_kelas, mk.kode_mk
              FROM qr_sessions qs
              JOIN kelas k ON qs.kelas_id = k.id
              JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
              WHERE qs.kelas_id IN ($placeholders) 
              AND qs.tanggal = CURDATE()
              AND qs.status = 'active'
              ORDER BY qs.jam_mulai DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($class_ids);
    $today_qr_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Kuliah - Dashboard Mahasiswa</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .today-highlight {
            background: linear-gradient(135deg, #2d5a27, #4a7c59);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .today-title {
            font-size: 1.5em;
            margin: 0 0 15px 0;
        }
        
        .today-classes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .today-class {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #fff;
        }
        
        .class-time {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 5px;
        }
        
        .class-info {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .qr-status {
            margin-top: 10px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .qr-active {
            background: #28a745;
            color: white;
        }
        
        .qr-inactive {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .day-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .day-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
            color: #2d5a27;
        }
        
        .day-today {
            background: #2d5a27;
            color: white;
        }
        
        .day-classes {
            padding: 15px;
        }
        
        .class-item {
            padding: 12px;
            margin-bottom: 10px;
            border-left: 4px solid #2d5a27;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .class-item:last-child {
            margin-bottom: 0;
        }
        
        .class-item.current-time {
            background: #e8f5e8;
            border-left-color: #28a745;
        }
        
        .class-name {
            font-weight: bold;
            color: #2d5a27;
            margin-bottom: 5px;
        }
        
        .class-details {
            font-size: 0.9em;
            color: #666;
            line-height: 1.4;
        }
        
        .no-classes {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 20px;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-top: 4px solid #2d5a27;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2d5a27;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .today-classes {
                grid-template-columns: 1fr;
            }
            
            .schedule-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Jadwal Kuliah</h1>
                    <div class="user-info">
                        <a href="index.php" style="color: #2d5a27; text-decoration: none; margin-right: 15px;">← Kembali</a>
                        <span><?php echo $current_user['full_name']; ?></span>
                        <a href="../../logout.php" class="logout-btn">Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="container">
                <!-- Today's Schedule Highlight -->
                <div class="today-highlight">
                    <h2 class="today-title">📅 Jadwal Hari Ini - <?php echo $today_name . ', ' . date('d F Y'); ?></h2>
                    
                    <?php if (empty($today_schedule)): ?>
                        <p>Tidak ada jadwal kuliah hari ini. Selamat beristirahat! 😊</p>
                    <?php else: ?>
                        <div class="today-classes">
                            <?php foreach ($today_schedule as $class): ?>
                                <?php
                                // Check if there's an active QR session for this class
                                $active_qr = null;
                                foreach ($today_qr_sessions as $qr) {
                                    if ($qr['kelas_id'] == $class['id']) {
                                        $active_qr = $qr;
                                        break;
                                    }
                                }
                                
                                // Check if current time is within class time
                                $current_time = date('H:i:s');
                                $is_current = ($current_time >= $class['jam_mulai'] && $current_time <= $class['jam_selesai']);
                                ?>
                                <div class="today-class <?php echo $is_current ? 'current-time' : ''; ?>">
                                    <div class="class-time">
                                        <?php echo $class['jam_mulai'] . ' - ' . $class['jam_selesai']; ?>
                                        <?php if ($is_current): ?>
                                            <span style="color: #ffd700;">🔴 SEDANG BERLANGSUNG</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="class-info">
                                        <strong><?php echo $class['kode_mk'] . ' - ' . $class['nama_mk']; ?></strong><br>
                                        Kelas: <?php echo $class['nama_kelas']; ?> | Ruang: <?php echo $class['ruangan']; ?><br>
                                        Dosen: <?php echo $class['dosen_name']; ?> | SKS: <?php echo $class['sks']; ?>
                                    </div>
                                    
                                    <?php if ($active_qr): ?>
                                        <div class="qr-status qr-active">
                                            ✅ QR Code Aktif - <a href="scan-qr.php" style="color: white; text-decoration: underline;">Scan Sekarang</a>
                                        </div>
                                    <?php else: ?>
                                        <div class="qr-status qr-inactive">
                                            ⏳ QR Code Belum Aktif
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Schedule Statistics -->
                <div class="stats-summary">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo count($schedule); ?></div>
                        <div class="stat-label">Total Mata Kuliah</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo array_sum(array_column($schedule, 'sks')); ?></div>
                        <div class="stat-label">Total SKS</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo count($today_schedule); ?></div>
                        <div class="stat-label">Kelas Hari Ini</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo count($today_qr_sessions); ?></div>
                        <div class="stat-label">QR Aktif Hari Ini</div>
                    </div>
                </div>
                
                <!-- Weekly Schedule -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Jadwal Mingguan</h3>
                    </div>
                    <div class="card-body">
                        <div class="schedule-grid">
                            <?php foreach ($days as $day): ?>
                                <div class="day-card">
                                    <div class="day-header <?php echo $day === $today_name ? 'day-today' : ''; ?>">
                                        <?php echo $day; ?>
                                        <?php if ($day === $today_name): ?>
                                            <span style="float: right;">📍 Hari Ini</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="day-classes">
                                        <?php if (empty($schedule_by_day[$day])): ?>
                                            <div class="no-classes">Tidak ada jadwal</div>
                                        <?php else: ?>
                                            <?php foreach ($schedule_by_day[$day] as $class): ?>
                                                <?php
                                                $is_current_class = false;
                                                if ($day === $today_name) {
                                                    $current_time = date('H:i:s');
                                                    $is_current_class = ($current_time >= $class['jam_mulai'] && $current_time <= $class['jam_selesai']);
                                                }
                                                ?>
                                                <div class="class-item <?php echo $is_current_class ? 'current-time' : ''; ?>">
                                                    <div class="class-name">
                                                        <?php echo $class['kode_mk'] . ' - ' . $class['nama_mk']; ?>
                                                        <?php if ($is_current_class): ?>
                                                            <span style="color: #28a745; font-size: 0.8em;">● LIVE</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="class-details">
                                                        ⏰ <?php echo $class['jam_mulai'] . ' - ' . $class['jam_selesai']; ?><br>
                                                        🏫 Ruang: <?php echo $class['ruangan']; ?> | Kelas: <?php echo $class['nama_kelas']; ?><br>
                                                        👨‍🏫 <?php echo $class['dosen_name']; ?> | 📚 <?php echo $class['sks']; ?> SKS
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Aksi Cepat</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <a href="scan-qr.php" class="btn btn-primary">📱 Scan QR Code</a>
                            <a href="attendance-history.php" class="btn btn-info">📊 Riwayat Kehadiran</a>
                            <?php if (!empty($today_qr_sessions)): ?>
                                <span class="btn btn-success" style="cursor: default;">
                                    ✅ <?php echo count($today_qr_sessions); ?> QR Aktif Hari Ini
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update current time indicator every minute
        setInterval(function() {
            location.reload();
        }, 60000);
        
        // Highlight current time classes
        function updateCurrentTimeHighlight() {
            const now = new Date();
            const currentTime = now.getHours().toString().padStart(2, '0') + ':' + 
                               now.getMinutes().toString().padStart(2, '0') + ':00';
            
            // This would be more complex in a real implementation
            // For now, we rely on PHP to determine current classes
        }
    </script>
</body>
</html>
