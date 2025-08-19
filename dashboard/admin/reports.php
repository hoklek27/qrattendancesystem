<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();

// Get all classes for filter
$query = "SELECT k.*, mk.nama_mk, mk.kode_mk, u.full_name as dosen_name
          FROM kelas k 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          JOIN users u ON k.dosen_id = u.id
          ORDER BY mk.kode_mk, k.nama_kelas";
$stmt = $db->prepare($query);
$stmt->execute();
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sessions for selected class
$selected_class = $_GET['kelas_id'] ?? '';
$selected_session = $_GET['session_id'] ?? '';
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_status = $_GET['status'] ?? '';

$sessions = [];
if ($selected_class) {
    $session_query = "SELECT qs.*, DATE_FORMAT(qs.tanggal, '%d/%m/%Y') as formatted_date
                      FROM qr_sessions qs 
                      WHERE qs.kelas_id = ? 
                      ORDER BY qs.tanggal DESC, qs.jam_mulai DESC";
    $session_stmt = $db->prepare($session_query);
    $session_stmt->execute([$selected_class]);
    $sessions = $session_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Build query conditions
$where_conditions = ["1=1"];
$params = [];

if (!empty($selected_class)) {
    $where_conditions[] = "k.id = ?";
    $params[] = $selected_class;
}

if (!empty($selected_session)) {
    $where_conditions[] = "qs.id = ?";
    $params[] = $selected_session;
}

if (!empty($selected_month)) {
    $where_conditions[] = "DATE_FORMAT(qs.tanggal, '%Y-%m') = ?";
    $params[] = $selected_month;
}

if (!empty($selected_status)) {
    if ($selected_status === 'tidak_hadir') {
        $where_conditions[] = "a.id IS NULL";
    } else {
        $where_conditions[] = "a.status = ?";
        $params[] = $selected_status;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get attendance data with all enrolled students
$query = "SELECT 
            qs.id as session_id,
            qs.tanggal,
            qs.jam_mulai,
            qs.jam_selesai,
            qs.qr_code,
            mk.kode_mk,
            mk.nama_mk,
            k.nama_kelas,
            u.full_name as dosen_name,
            m.full_name as mahasiswa_name,
            m.nim_nip,
            COALESCE(a.status, 'alfa') as status,
            a.scan_time
          FROM qr_sessions qs
          JOIN kelas k ON qs.kelas_id = k.id
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
          JOIN users u ON k.dosen_id = u.id
          JOIN enrollments e ON k.id = e.kelas_id
          JOIN users m ON e.mahasiswa_id = m.id
          LEFT JOIN attendance a ON qs.id = a.qr_session_id AND m.id = a.mahasiswa_id
          WHERE $where_clause
          ORDER BY qs.tanggal DESC, qs.jam_mulai DESC, mk.kode_mk, m.full_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$summary_query = "SELECT 
                    COUNT(DISTINCT qs.id) as total_sessions,
                    COUNT(DISTINCT k.id) as total_classes,
                    COUNT(DISTINCT m.id) as total_students,
                    COUNT(CASE WHEN a.status = 'hadir' THEN 1 END) as total_hadir,
                    COUNT(CASE WHEN a.status = 'sakit' THEN 1 END) as total_sakit,
                    COUNT(CASE WHEN a.status = 'izin' THEN 1 END) as total_izin,
                    COUNT(CASE WHEN a.id IS NULL THEN 1 END) as total_alfa
                  FROM qr_sessions qs
                  JOIN kelas k ON qs.kelas_id = k.id
                  JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
                  JOIN users u ON k.dosen_id = u.id
                  JOIN enrollments e ON k.id = e.kelas_id
                  JOIN users m ON e.mahasiswa_id = m.id
                  LEFT JOIN attendance a ON qs.id = a.qr_session_id AND m.id = a.mahasiswa_id
                  WHERE $where_clause";

$stmt = $db->prepare($summary_query);
$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate attendance percentage
$total_possible = ($summary['total_sessions'] ?? 0) * ($summary['total_students'] ?? 0);
$attendance_percentage = $total_possible > 0 ? 
    (($summary['total_hadir'] ?? 0) / $total_possible) * 100 : 0;

// Handle PDF Export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Simple HTML to PDF conversion
    header('Content-Type: text/html');
    header('Content-Disposition: inline; filename="Laporan_Kehadiran_Admin_' . date('Y-m-d') . '.html"');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Kehadiran Admin</title>
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .info { margin-bottom: 20px; }
            .stats { margin-bottom: 30px; }
            .stats table { width: 100%; border-collapse: collapse; }
            .stats th, .stats td { border: 1px solid #000; padding: 8px; text-align: center; }
            .attendance-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .attendance-table th, .attendance-table td { border: 1px solid #000; padding: 6px; font-size: 10px; }
            .session-header { background-color: #f0f0f0; font-weight: bold; }
            .status-hadir { background-color: #d4edda; }
            .status-sakit { background-color: #fff3cd; }
            .status-izin { background-color: #d1ecf1; }
            .status-alfa { background-color: #f8d7da; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>LAPORAN KEHADIRAN MAHASISWA</h2>
            <h3>Politeknik Negeri Samarinda</h3>
            <h4>Dashboard Administrator</h4>
        </div>
        
        <div class="info">
            <strong>Periode:</strong> ' . date('F Y', strtotime($selected_month . '-01')) . '<br>';
            
    if ($selected_class) {
        $class_info = array_filter($classes, function($c) use ($selected_class) {
            return $c['id'] == $selected_class;
        });
        if (!empty($class_info)) {
            $class = reset($class_info);
            echo '<strong>Mata Kuliah:</strong> ' . htmlspecialchars($class['kode_mk'] . ' - ' . $class['nama_mk'] . ' (' . $class['nama_kelas'] . ')') . '<br>';
            echo '<strong>Dosen:</strong> ' . htmlspecialchars($class['dosen_name']) . '<br>';
        }
    }
    
    echo '<strong>Tanggal Cetak:</strong> ' . date('d/m/Y H:i:s') . '
        </div>
        
        <div class="stats">
            <h4>RINGKASAN STATISTIK</h4>
            <table>
                <tr>
                    <th>Total Sesi</th>
                    <th>Total Kelas</th>
                    <th>Total Mahasiswa</th>
                    <th>Hadir</th>
                    <th>Sakit</th>
                    <th>Izin</th>
                    <th>Alfa</th>
                    <th>Persentase Kehadiran</th>
                </tr>
                <tr>
                    <td>' . ($summary['total_sessions'] ?? 0) . '</td>
                    <td>' . ($summary['total_classes'] ?? 0) . '</td>
                    <td>' . ($summary['total_students'] ?? 0) . '</td>
                    <td>' . ($summary['total_hadir'] ?? 0) . '</td>
                    <td>' . ($summary['total_sakit'] ?? 0) . '</td>
                    <td>' . ($summary['total_izin'] ?? 0) . '</td>
                    <td>' . ($summary['total_alfa'] ?? 0) . '</td>
                    <td>' . number_format($attendance_percentage, 1) . '%</td>
                </tr>
            </table>
        </div>';
        
    if (!empty($attendance_data)) {
        echo '<h4>DETAIL KEHADIRAN PER SESI</h4>
        <table class="attendance-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Waktu</th>
                    <th>Mata Kuliah</th>
                    <th>Kelas</th>
                    <th>Dosen</th>
                    <th>Mahasiswa</th>
                    <th>NIM</th>
                    <th>Status</th>
                    <th>Waktu Scan</th>
                </tr>
            </thead>
            <tbody>';
            
        $no = 1;
        $current_session = null;
        foreach ($attendance_data as $row) {
            $session_key = $row['session_id'] . '_' . $row['tanggal'];
            if ($current_session !== $session_key) {
                $current_session = $session_key;
                
                // Calculate session statistics
                $session_students = array_filter($attendance_data, function($item) use ($row) {
                    return $item['session_id'] === $row['session_id'];
                });
                
                $session_hadir = count(array_filter($session_students, function($s) { return $s['status'] === 'hadir'; }));
                $session_sakit = count(array_filter($session_students, function($s) { return $s['status'] === 'sakit'; }));
                $session_izin = count(array_filter($session_students, function($s) { return $s['status'] === 'izin'; }));
                $session_alfa = count(array_filter($session_students, function($s) { return $s['status'] === 'alfa'; }));
                $session_total = count($session_students);
                $session_percentage = $session_total > 0 ? ($session_hadir / $session_total) * 100 : 0;
                
                echo '<tr class="session-header">
                        <td colspan="10">
                            <strong>Sesi: ' . date('d/m/Y', strtotime($row['tanggal'])) . ' - 
                            ' . htmlspecialchars($row['kode_mk']) . ' (' . htmlspecialchars($row['nama_kelas']) . ') - ' . htmlspecialchars($row['dosen_name']) . '</strong>
                            - H:' . $session_hadir . ' S:' . $session_sakit . ' I:' . $session_izin . ' A:' . $session_alfa . ' 
                            (' . number_format($session_percentage, 1) . '%)
                        </td>
                      </tr>';
            }
            
            echo '<tr class="status-' . $row['status'] . '">
                    <td>' . $no++ . '</td>
                    <td>' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>
                    <td>' . $row['jam_mulai'] . ' - ' . $row['jam_selesai'] . '</td>
                    <td>' . htmlspecialchars($row['kode_mk'] . ' - ' . $row['nama_mk']) . '</td>
                    <td>' . htmlspecialchars($row['nama_kelas']) . '</td>
                    <td>' . htmlspecialchars($row['dosen_name']) . '</td>
                    <td>' . htmlspecialchars($row['mahasiswa_name']) . '</td>
                    <td>' . htmlspecialchars($row['nim_nip']) . '</td>
                    <td>' . ucfirst($row['status']) . '</td>
                    <td>' . ($row['scan_time'] ? date('H:i:s', strtotime($row['scan_time'])) : '-') . '</td>
                  </tr>';
        }
        
        echo '</tbody>
        </table>';
    }
    
    echo '<script>window.print();</script>
    </body>
    </html>';
    exit;
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laporan_kehadiran_admin_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Tanggal',
        'Jam',
        'Kode MK',
        'Mata Kuliah',
        'Kelas',
        'Dosen',
        'Mahasiswa',
        'NIM',
        'Status',
        'Waktu Scan',
        'QR Code'
    ]);
    
    // CSV Data
    foreach ($attendance_data as $row) {
        fputcsv($output, [
            date('d/m/Y', strtotime($row['tanggal'])),
            $row['jam_mulai'] . ' - ' . $row['jam_selesai'],
            $row['kode_mk'],
            $row['nama_mk'],
            $row['nama_kelas'],
            $row['dosen_name'],
            $row['mahasiswa_name'],
            $row['nim_nip'],
            ucfirst($row['status']),
            $row['scan_time'] ? date('H:i:s', strtotime($row['scan_time'])) : '-',
            $row['qr_code']
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
    <title>Laporan Kehadiran - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card.hadir { border-left: 4px solid #28a745; }
        .stat-card.sakit { border-left: 4px solid #ffc107; }
        .stat-card.izin { border-left: 4px solid #17a2b8; }
        .stat-card.alfa { border-left: 4px solid #dc3545; }
        .stat-card.total { border-left: 4px solid #2d5a27; }
        .stat-card.percentage { border-left: 4px solid #6f42c1; }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-number.hadir { color: #28a745; }
        .stat-number.sakit { color: #ffc107; }
        .stat-number.izin { color: #17a2b8; }
        .stat-number.alfa { color: #dc3545; }
        .stat-number.total { color: #2d5a27; }
        .stat-number.percentage { color: #6f42c1; }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .filter-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 0.9em;
        }
        
        .attendance-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-hadir {
            background: #d4edda;
            color: #155724;
        }
        
        .status-sakit {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-izin {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-alfa {
            background: #f8d7da;
            color: #721c24;
        }
        
        .session-group {
            background: #e9ecef;
            font-weight: bold;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .attendance-table {
                font-size: 0.8em;
            }
            
            .attendance-table th,
            .attendance-table td {
                padding: 6px 4px;
            }
            
            .export-buttons {
                flex-direction: column;
            }
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
                        <a href="index.php" style="color: #2d5a27; text-decoration: none; margin-right: 15px;">← Kembali</a>
                        <span>Admin</span>
                        <a href="../../logout.php" class="logout-btn">Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="container">
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-number total"><?php echo $summary['total_sessions'] ?? 0; ?></div>
                        <div class="stat-label">Total Sesi</div>
                    </div>
                    <div class="stat-card hadir">
                        <div class="stat-number hadir"><?php echo $summary['total_hadir'] ?? 0; ?></div>
                        <div class="stat-label">Hadir</div>
                    </div>
                    <div class="stat-card sakit">
                        <div class="stat-number sakit"><?php echo $summary['total_sakit'] ?? 0; ?></div>
                        <div class="stat-label">Sakit</div>
                    </div>
                    <div class="stat-card izin">
                        <div class="stat-number izin"><?php echo $summary['total_izin'] ?? 0; ?></div>
                        <div class="stat-label">Izin</div>
                    </div>
                    <div class="stat-card alfa">
                        <div class="stat-number alfa"><?php echo $summary['total_alfa'] ?? 0; ?></div>
                        <div class="stat-label">Alfa</div>
                    </div>
                    <div class="stat-card percentage">
                        <div class="stat-number percentage"><?php echo number_format($attendance_percentage, 1); ?>%</div>
                        <div class="stat-label">Persentase Kehadiran</div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Filter Laporan</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="filter-form">
                            <div class="filter-row">
                                <div class="form-group">
                                    <label for="kelas_id">Kelas</label>
                                    <select id="kelas_id" name="kelas_id" class="form-control" onchange="loadSessions()">
                                        <option value="">Semua Kelas</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo $class['kode_mk'] . ' - ' . $class['nama_mk'] . ' (' . $class['nama_kelas'] . ') - ' . $class['dosen_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="session_id">Sesi Perkuliahan</label>
                                    <select id="session_id" name="session_id" class="form-control">
                                        <option value="">Semua Sesi</option>
                                        <?php foreach ($sessions as $session): ?>
                                            <option value="<?php echo $session['id']; ?>" <?php echo $selected_session == $session['id'] ? 'selected' : ''; ?>>
                                                <?php echo $session['formatted_date'] . ' (' . $session['jam_mulai'] . ' - ' . $session['jam_selesai'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="month">Bulan</label>
                                    <input type="month" id="month" name="month" class="form-control" value="<?php echo $selected_month; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        <option value="hadir" <?php echo $selected_status === 'hadir' ? 'selected' : ''; ?>>Hadir</option>
                                        <option value="sakit" <?php echo $selected_status === 'sakit' ? 'selected' : ''; ?>>Sakit</option>
                                        <option value="izin" <?php echo $selected_status === 'izin' ? 'selected' : ''; ?>>Izin</option>
                                        <option value="alfa" <?php echo $selected_status === 'alfa' ? 'selected' : ''; ?>>Alfa</option>
                                        <option value="tidak_hadir" <?php echo $selected_status === 'tidak_hadir' ? 'selected' : ''; ?>>Tidak Hadir</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="?" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Export Buttons -->
                <div class="export-buttons">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf'])); ?>" class="btn btn-danger">
                        📄 Export PDF
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success">
                        📊 Export CSV
                    </a>
                </div>
                
                <!-- Report Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Data Kehadiran</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendance_data)): ?>
                            <div class="alert alert-info">
                                Tidak ada data kehadiran untuk filter yang dipilih.
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="attendance-table">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Waktu</th>
                                            <th>Mata Kuliah</th>
                                            <th>Kelas</th>
                                            <th>Dosen</th>
                                            <th>Mahasiswa</th>
                                            <th>NIM</th>
                                            <th>Status</th>
                                            <th>Waktu Scan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $current_session = null;
                                        foreach ($attendance_data as $row): 
                                            $session_key = $row['session_id'] . '_' . $row['tanggal'];
                                            if ($current_session !== $session_key):
                                                $current_session = $session_key;
                                                
                                                // Calculate session statistics
                                                $session_students = array_filter($attendance_data, function($item) use ($row) {
                                                    return $item['session_id'] === $row['session_id'];
                                                });
                                                
                                                $session_hadir = count(array_filter($session_students, function($s) { return $s['status'] === 'hadir'; }));
                                                $session_sakit = count(array_filter($session_students, function($s) { return $s['status'] === 'sakit'; }));
                                                $session_izin = count(array_filter($session_students, function($s) { return $s['status'] === 'izin'; }));
                                                $session_alfa = count(array_filter($session_students, function($s) { return $s['status'] === 'alfa'; }));
                                                $session_total = count($session_students);
                                                $session_percentage = $session_total > 0 ? ($session_hadir / $session_total) * 100 : 0;
                                        ?>
                                                <tr class="session-group">
                                                    <td colspan="9">
                                                        <strong>Sesi: <?php echo date('d/m/Y', strtotime($row['tanggal'])); ?> - 
                                                        <?php echo $row['kode_mk']; ?> (<?php echo $row['nama_kelas']; ?>) - <?php echo $row['dosen_name']; ?></strong>
                                                        - H:<?php echo $session_hadir; ?> S:<?php echo $session_sakit; ?> I:<?php echo $session_izin; ?> A:<?php echo $session_alfa; ?> 
                                                        (<?php echo number_format($session_percentage, 1); ?>%)
                                                    </td>
                                                </tr>
                                        <?php endif; ?>
                                        
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                            <td><?php echo $row['jam_mulai'] . ' - ' . $row['jam_selesai']; ?></td>
                                            <td><?php echo $row['kode_mk'] . ' - ' . $row['nama_mk']; ?></td>
                                            <td><?php echo $row['nama_kelas']; ?></td>
                                            <td><?php echo $row['dosen_name']; ?></td>
                                            <td><?php echo $row['mahasiswa_name']; ?></td>
                                            <td><?php echo $row['nim_nip']; ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                                    <?php echo ucfirst($row['status']); ?>
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

    <script>
        function loadSessions() {
            const kelasId = document.getElementById('kelas_id').value;
            const sessionSelect = document.getElementById('session_id');
            
            // Clear current options
            sessionSelect.innerHTML = '<option value="">Semua Sesi</option>';
            
            if (kelasId) {
                fetch(`../dosen/get-sessions.php?kelas_id=${kelasId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            data.sessions.forEach(session => {
                                const option = document.createElement('option');
                                option.value = session.id;
                                option.textContent = `${session.formatted_date} (${session.jam_mulai} - ${session.jam_selesai})`;
                                sessionSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => console.error('Error loading sessions:', error));
            }
        }
    </script>
</body>
</html>
