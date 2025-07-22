<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('dosen');

$current_user = getCurrentUser();
$database = new Database();
$db = $database->getConnection();

// Get dosen's classes
$query = "SELECT k.id, k.nama_kelas, mk.nama_mk, mk.kode_mk 
          FROM kelas k 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          WHERE k.dosen_id = ? 
          ORDER BY mk.kode_mk, k.nama_kelas";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filters
$selected_class = $_GET['kelas_id'] ?? '';
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_status = $_GET['status'] ?? '';

// Build query conditions
$conditions = ["k.dosen_id = ?"];
$params = [$current_user['id']];

if ($selected_class) {
    $conditions[] = "k.id = ?";
    $params[] = $selected_class;
}

if ($selected_month) {
    $conditions[] = "DATE_FORMAT(qs.created_at, '%Y-%m') = ?";
    $params[] = $selected_month;
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

// Get attendance data
$query = "SELECT 
            qs.id as session_id,
            qs.qr_code,
            qs.created_at as session_time,
            qs.expires_at,
            k.nama_kelas,
            mk.nama_mk,
            mk.kode_mk,
            COUNT(a.id) as total_attendance,
            COUNT(CASE WHEN a.status = 'hadir' THEN 1 END) as hadir_count,
            COUNT(CASE WHEN a.status = 'terlambat' THEN 1 END) as terlambat_count,
            (SELECT COUNT(*) FROM enrollments WHERE kelas_id = k.id) as total_students
          FROM qr_sessions qs
          JOIN kelas k ON qs.kelas_id = k.id
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
          LEFT JOIN attendance a ON qs.id = a.qr_session_id
          $where_clause
          GROUP BY qs.id, k.id, mk.id
          ORDER BY qs.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get overall statistics
$stats_query = "SELECT 
                  COUNT(DISTINCT qs.id) as total_sessions,
                  COUNT(DISTINCT k.id) as total_classes,
                  COUNT(a.id) as total_attendance,
                  COUNT(CASE WHEN a.status = 'hadir' THEN 1 END) as total_hadir,
                  AVG(CASE WHEN total_students.count > 0 THEN (attendance_count.count * 100.0 / total_students.count) ELSE 0 END) as avg_attendance_rate
                FROM qr_sessions qs
                JOIN kelas k ON qs.kelas_id = k.id
                LEFT JOIN attendance a ON qs.id = a.qr_session_id
                LEFT JOIN (
                    SELECT kelas_id, COUNT(*) as count 
                    FROM enrollments 
                    GROUP BY kelas_id
                ) total_students ON k.id = total_students.kelas_id
                LEFT JOIN (
                    SELECT qr_session_id, COUNT(*) as count 
                    FROM attendance 
                    GROUP BY qr_session_id
                ) attendance_count ON qs.id = attendance_count.qr_session_id
                $where_clause";

$stmt = $db->prepare($stats_query);
$stmt->execute($params);
$overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laporan_kehadiran_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Tanggal Sesi',
        'Kode MK',
        'Mata Kuliah', 
        'Kelas',
        'QR Code',
        'Total Mahasiswa',
        'Hadir',
        'Terlambat',
        'Tidak Hadir',
        'Persentase Kehadiran'
    ]);
    
    // CSV data
    foreach ($sessions as $session) {
        $tidak_hadir = $session['total_students'] - $session['total_attendance'];
        $persentase = $session['total_students'] > 0 ? 
            round(($session['total_attendance'] / $session['total_students']) * 100, 2) : 0;
            
        fputcsv($output, [
            date('d/m/Y H:i', strtotime($session['session_time'])),
            $session['kode_mk'],
            $session['nama_mk'],
            $session['nama_kelas'],
            $session['qr_code'],
            $session['total_students'],
            $session['hadir_count'],
            $session['terlambat_count'],
            $tidak_hadir,
            $persentase . '%'
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kehadiran - Dashboard Dosen</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #2d5a27 0%, #4a7c59 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .attendance-rate {
            padding: 4px 8px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
        }
        
        .rate-excellent { background: #28a745; }
        .rate-good { background: #17a2b8; }
        .rate-fair { background: #ffc107; color: #000; }
        .rate-poor { background: #dc3545; }
        
        .session-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .export-buttons {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Laporan Kehadiran</h1>
                    <div class="user-info">
                        <a href="index.php" style="color: #2d5a27; text-decoration: none; margin-right: 15px;">← Dashboard</a>
                        <span><?php echo $current_user['full_name']; ?></span>
                        <a href="../../logout.php" class="logout-btn">Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="container">
                <!-- Statistics Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $overall_stats['total_sessions'] ?? 0; ?></div>
                        <div class="stat-label">Total Sesi</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $overall_stats['total_classes'] ?? 0; ?></div>
                        <div class="stat-label">Kelas Aktif</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $overall_stats['total_attendance'] ?? 0; ?></div>
                        <div class="stat-label">Total Kehadiran</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo round($overall_stats['avg_attendance_rate'] ?? 0, 1); ?>%</div>
                        <div class="stat-label">Rata-rata Kehadiran</div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters">
                    <form method="GET" class="filter-row">
                        <div class="filter-group">
                            <label for="kelas_id">Kelas</label>
                            <select name="kelas_id" id="kelas_id" class="form-control">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo $class['kode_mk'] . ' - ' . $class['nama_kelas']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="month">Bulan</label>
                            <input type="month" name="month" id="month" class="form-control" value="<?php echo $selected_month; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="?" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
                
                <!-- Export Buttons -->
                <div class="export-buttons">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success">
                        📊 Export CSV
                    </a>
                    <a href="qr-history.php" class="btn btn-info">
                        📱 Lihat QR History
                    </a>
                </div>
                
                <!-- Sessions Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Riwayat Sesi Kehadiran</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sessions)): ?>
                            <div class="alert alert-info">
                                Tidak ada data sesi kehadiran untuk filter yang dipilih.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tanggal & Waktu</th>
                                            <th>Mata Kuliah</th>
                                            <th>Kelas</th>
                                            <th>QR Code</th>
                                            <th>Kehadiran</th>
                                            <th>Persentase</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): 
                                            $tidak_hadir = $session['total_students'] - $session['total_attendance'];
                                            $persentase = $session['total_students'] > 0 ? 
                                                round(($session['total_attendance'] / $session['total_students']) * 100, 2) : 0;
                                            
                                            $rate_class = 'rate-poor';
                                            if ($persentase >= 90) $rate_class = 'rate-excellent';
                                            elseif ($persentase >= 75) $rate_class = 'rate-good';
                                            elseif ($persentase >= 60) $rate_class = 'rate-fair';
                                            
                                            $is_expired = strtotime($session['expires_at']) < time();
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo date('d/m/Y', strtotime($session['session_time'])); ?></strong><br>
                                                    <small><?php echo date('H:i', strtotime($session['session_time'])); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo $session['kode_mk']; ?></strong><br>
                                                    <small><?php echo $session['nama_mk']; ?></small>
                                                </td>
                                                <td><?php echo $session['nama_kelas']; ?></td>
                                                <td>
                                                    <code style="font-size: 11px;"><?php echo substr($session['qr_code'], 0, 20) . '...'; ?></code>
                                                </td>
                                                <td>
                                                    <strong>Hadir:</strong> <?php echo $session['hadir_count']; ?><br>
                                                    <strong>Terlambat:</strong> <?php echo $session['terlambat_count']; ?><br>
                                                    <strong>Tidak Hadir:</strong> <?php echo $tidak_hadir; ?><br>
                                                    <small>Total: <?php echo $session['total_students']; ?> mahasiswa</small>
                                                </td>
                                                <td>
                                                    <span class="attendance-rate <?php echo $rate_class; ?>">
                                                        <?php echo $persentase; ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($is_expired): ?>
                                                        <span class="badge badge-secondary">Expired</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">Aktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="get-attendance.php?session_id=<?php echo $session['session_id']; ?>" 
                                                       class="btn btn-sm btn-info" target="_blank">
                                                        👥 Detail
                                                    </a>
                                                </td>
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
    
    <script src="../../assets/js/main.js"></script>
</body>
</html>
