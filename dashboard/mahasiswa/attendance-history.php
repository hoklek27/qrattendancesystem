<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('mahasiswa');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Get student's enrolled classes
$query = "SELECT k.*, mk.nama_mk, mk.kode_mk, u.full_name as dosen_name
          FROM enrollments e
          JOIN kelas k ON e.kelas_id = k.id
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
          JOIN users u ON k.dosen_id = u.id
          WHERE e.mahasiswa_id = ?
          ORDER BY mk.kode_mk";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$enrolled_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter parameters
$selected_class = $_GET['kelas_id'] ?? '';
$selected_month = $_GET['month'] ?? date('Y-m');

// Build attendance history query
$where_conditions = ["e.mahasiswa_id = ?"];
$params = [$current_user['id']];

if (!empty($selected_class)) {
    $where_conditions[] = "k.id = ?";
    $params[] = $selected_class;
}

if (!empty($selected_month)) {
    $where_conditions[] = "DATE_FORMAT(qs.tanggal, '%Y-%m') = ?";
    $params[] = $selected_month;
}

$where_clause = implode(' AND ', $where_conditions);

// Get attendance history
$query = "SELECT 
            qs.tanggal,
            qs.jam_mulai,
            qs.jam_selesai,
            mk.kode_mk,
            mk.nama_mk,
            k.nama_kelas,
            u.full_name as dosen_name,
            a.status,
            a.scan_time,
            CASE WHEN a.id IS NOT NULL THEN 'Hadir' ELSE 'Tidak Hadir' END as kehadiran
          FROM enrollments e
          JOIN kelas k ON e.kelas_id = k.id
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
          JOIN users u ON k.dosen_id = u.id
          JOIN qr_sessions qs ON k.id = qs.kelas_id
          LEFT JOIN attendance a ON qs.id = a.qr_session_id AND a.mahasiswa_id = e.mahasiswa_id
          WHERE $where_clause
          ORDER BY qs.tanggal DESC, qs.jam_mulai DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$stats_query = "SELECT 
                  k.id,
                  mk.kode_mk,
                  mk.nama_mk,
                  k.nama_kelas,
                  COUNT(DISTINCT qs.id) as total_sessions,
                  COUNT(DISTINCT CASE WHEN a.status = 'hadir' THEN a.id END) as attended_sessions,
                  ROUND((COUNT(DISTINCT CASE WHEN a.status = 'hadir' THEN a.id END) / COUNT(DISTINCT qs.id)) * 100, 1) as attendance_percentage
                FROM enrollments e
                JOIN kelas k ON e.kelas_id = k.id
                JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
                JOIN qr_sessions qs ON k.id = qs.kelas_id
                LEFT JOIN attendance a ON qs.id = a.qr_session_id AND a.mahasiswa_id = e.mahasiswa_id
                WHERE $where_clause
                GROUP BY k.id
                ORDER BY mk.kode_mk";

$stmt = $db->prepare($query);
$stmt->execute($params);
$class_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Kehadiran - Dashboard Mahasiswa</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #2d5a27;
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .stat-title {
            font-weight: bold;
            color: #2d5a27;
            margin: 0;
        }
        
        .stat-percentage {
            font-size: 1.5em;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .percentage-good {
            background: #d4edda;
            color: #155724;
        }
        
        .percentage-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .percentage-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .stat-details {
            font-size: 0.9em;
            color: #666;
        }
        
        .filter-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .history-table th,
        .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .history-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .status-hadir {
            background: #d4edda;
            color: #155724;
        }
        
        .status-tidak-hadir {
            background: #f8d7da;
            color: #721c24;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .warning-box h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .history-table {
                font-size: 0.9em;
            }
            
            .history-table th,
            .history-table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Riwayat Kehadiran</h1>
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
                <!-- Warning for low attendance -->
                <?php 
                $low_attendance_classes = array_filter($class_stats, function($class) {
                    return $class['attendance_percentage'] < 75;
                });
                
                if (!empty($low_attendance_classes)): 
                ?>
                    <div class="warning-box">
                        <h4>⚠️ Peringatan Kehadiran</h4>
                        <p>Anda memiliki kehadiran di bawah 75% pada mata kuliah berikut:</p>
                        <ul>
                            <?php foreach ($low_attendance_classes as $class): ?>
                                <li>
                                    <strong><?php echo $class['kode_mk']; ?> - <?php echo $class['nama_mk']; ?></strong>
                                    (<?php echo $class['attendance_percentage']; ?>% - <?php echo $class['attended_sessions']; ?>/<?php echo $class['total_sessions']; ?> sesi)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p><small>Pastikan kehadiran Anda minimal 75% untuk dapat mengikuti ujian.</small></p>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics per Class -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Statistik Kehadiran per Mata Kuliah</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($class_stats)): ?>
                            <div class="alert alert-info">
                                Belum ada data kehadiran.
                            </div>
                        <?php else: ?>
                            <div class="stats-grid">
                                <?php foreach ($class_stats as $class): ?>
                                    <div class="stat-card">
                                        <div class="stat-header">
                                            <h4 class="stat-title"><?php echo $class['kode_mk']; ?></h4>
                                            <div class="stat-percentage <?php 
                                                echo $class['attendance_percentage'] >= 75 ? 'percentage-good' : 
                                                    ($class['attendance_percentage'] >= 60 ? 'percentage-warning' : 'percentage-danger'); 
                                            ?>">
                                                <?php echo $class['attendance_percentage']; ?>%
                                            </div>
                                        </div>
                                        <p style="margin: 5px 0; font-weight: 500;"><?php echo $class['nama_mk']; ?></p>
                                        <p style="margin: 5px 0; font-size: 0.9em; color: #666;">Kelas: <?php echo $class['nama_kelas']; ?></p>
                                        <div class="stat-details">
                                            <p>Hadir: <?php echo $class['attended_sessions']; ?> dari <?php echo $class['total_sessions']; ?> sesi</p>
                                            <p>Tidak hadir: <?php echo $class['total_sessions'] - $class['attended_sessions']; ?> sesi</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Filter Riwayat</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="filter-form">
                            <div class="filter-row">
                                <div class="form-group">
                                    <label for="kelas_id">Mata Kuliah</label>
                                    <select id="kelas_id" name="kelas_id" class="form-control">
                                        <option value="">Semua Mata Kuliah</option>
                                        <?php foreach ($enrolled_classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo $class['kode_mk'] . ' - ' . $class['nama_mk'] . ' (' . $class['nama_kelas'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="month">Bulan</label>
                                    <input type="month" id="month" name="month" class="form-control" value="<?php echo $selected_month; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="?" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- History Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Riwayat Kehadiran Detail</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendance_history)): ?>
                            <div class="alert alert-info">
                                Tidak ada riwayat kehadiran untuk filter yang dipilih.
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="history-table">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Waktu</th>
                                            <th>Mata Kuliah</th>
                                            <th>Kelas</th>
                                            <th>Dosen</th>
                                            <th>Status</th>
                                            <th>Waktu Scan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_history as $row): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                                <td><?php echo $row['jam_mulai'] . ' - ' . $row['jam_selesai']; ?></td>
                                                <td><?php echo $row['kode_mk'] . ' - ' . $row['nama_mk']; ?></td>
                                                <td><?php echo $row['nama_kelas']; ?></td>
                                                <td><?php echo $row['dosen_name']; ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $row['status'] ? 'hadir' : 'tidak-hadir'; ?>">
                                                        <?php echo $row['kehadiran']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $row['scan_time'] ? date('H:i:s', strtotime($row['scan_time'])) : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
