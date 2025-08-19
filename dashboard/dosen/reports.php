<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('dosen');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Get dosen's classes for filter
$query = "SELECT k.*, mk.nama_mk, mk.kode_mk 
        FROM kelas k 
        JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
        WHERE k.dosen_id = ?
        ORDER BY mk.kode_mk, k.nama_kelas";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get QR sessions for selected class
$selected_class = $_GET['kelas_id'] ?? '';
$selected_session = $_GET['session_id'] ?? '';
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_status = $_GET['status'] ?? '';

// Get sessions for selected class
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
$where_conditions = ["k.dosen_id = ?"];
$params = [$current_user['id']];

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
            u.full_name as mahasiswa_name,
            u.nim_nip,
            COALESCE(a.status, 'alfa') as status,
            a.scan_time,
            COUNT(DISTINCT e.mahasiswa_id) OVER (PARTITION BY qs.id) as total_mahasiswa,
            COUNT(DISTINCT CASE WHEN a.status = 'hadir' THEN a.mahasiswa_id END) OVER (PARTITION BY qs.id) as hadir_count,
            COUNT(DISTINCT CASE WHEN a.status = 'sakit' THEN a.mahasiswa_id END) OVER (PARTITION BY qs.id) as sakit_count,
            COUNT(DISTINCT CASE WHEN a.status = 'izin' THEN a.mahasiswa_id END) OVER (PARTITION BY qs.id) as izin_count
          FROM qr_sessions qs
          JOIN kelas k ON qs.kelas_id = k.id
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
          JOIN enrollments e ON k.id = e.kelas_id
          JOIN users u ON e.mahasiswa_id = u.id
          LEFT JOIN attendance a ON qs.id = a.qr_session_id AND u.id = a.mahasiswa_id
          WHERE $where_clause
          ORDER BY qs.tanggal DESC, qs.jam_mulai DESC, mk.kode_mk, u.full_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$summary_query = "SELECT 
                    COUNT(DISTINCT qs.id) as total_sessions,
                    COUNT(DISTINCT e.mahasiswa_id) as total_students,
                    COUNT(DISTINCT CASE WHEN a.status = 'hadir' THEN a.id END) as total_hadir,
                    COUNT(DISTINCT CASE WHEN a.status = 'sakit' THEN a.id END) as total_sakit,
                    COUNT(DISTINCT CASE WHEN a.status = 'izin' THEN a.id END) as total_izin,
                    COUNT(DISTINCT CASE WHEN a.id IS NULL THEN CONCAT(qs.id, '_', e.mahasiswa_id) END) as total_alfa
                  FROM qr_sessions qs
                  JOIN kelas k ON qs.kelas_id = k.id
                  JOIN enrollments e ON k.id = e.kelas_id
                  LEFT JOIN attendance a ON qs.id = a.qr_session_id AND e.mahasiswa_id = a.mahasiswa_id
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
    require_once '../../vendor/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('QR Attendance System');
    $pdf->SetAuthor($current_user['full_name']);
    $pdf->SetTitle('Laporan Kehadiran - ' . date('d/m/Y'));
    
    // Set margins
    $pdf->SetMargins(15, 27, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 15, 'LAPORAN KEHADIRAN MAHASISWA', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Politeknik Negeri Samarinda', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Report info
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 8, 'Dosen:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, $current_user['full_name'], 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 8, 'Periode:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, date('F Y', strtotime($selected_month . '-01')), 0, 1, 'L');
    
    if ($selected_class) {
        $class_info = array_filter($classes, function($c) use ($selected_class) {
            return $c['id'] == $selected_class;
        });
        if (!empty($class_info)) {
            $class = reset($class_info);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(40, 8, 'Mata Kuliah:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 8, $class['kode_mk'] . ' - ' . $class['nama_mk'] . ' (' . $class['nama_kelas'] . ')', 0, 1, 'L');
        }
    }
    
    $pdf->Ln(5);
    
    // Summary statistics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'RINGKASAN STATISTIK', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 8, 'Total Sesi:', 1, 0, 'L');
    $pdf->Cell(30, 8, $summary['total_sessions'] ?? 0, 1, 0, 'C');
    $pdf->Cell(50, 8, 'Total Mahasiswa:', 1, 0, 'L');
    $pdf->Cell(30, 8, $summary['total_students'] ?? 0, 1, 1, 'C');
    
    $pdf->Cell(50, 8, 'Hadir:', 1, 0, 'L');
    $pdf->Cell(30, 8, $summary['total_hadir'] ?? 0, 1, 0, 'C');
    $pdf->Cell(50, 8, 'Sakit:', 1, 0, 'L');
    $pdf->Cell(30, 8, $summary['total_sakit'] ?? 0, 1, 1, 'C');
    
    $pdf->Cell(50, 8, 'Izin:', 1, 0, 'L');
    $pdf->Cell(30, 8, $summary['total_izin'] ?? 0, 1, 0, 'C');
    $pdf->Cell(50, 8, 'Alfa:', 1, 0, 'L');
    $pdf->Cell(30, 8, $summary['total_alfa'] ?? 0, 1, 1, 'C');
    
    $pdf->Cell(50, 8, 'Persentase Kehadiran:', 1, 0, 'L');
    $pdf->Cell(30, 8, number_format($attendance_percentage, 1) . '%', 1, 0, 'C');
    $pdf->Cell(80, 8, '', 0, 1, 'C');
    
    $pdf->Ln(10);
    
    // Attendance table
    if (!empty($attendance_data)) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'DETAIL KEHADIRAN', 0, 1, 'L');
        
        // Group by session
        $sessions_grouped = [];
        foreach ($attendance_data as $row) {
            $session_key = $row['session_id'] . '_' . $row['tanggal'];
            if (!isset($sessions_grouped[$session_key])) {
                $sessions_grouped[$session_key] = [
                    'info' => $row,
                    'students' => []
                ];
            }
            $sessions_grouped[$session_key]['students'][] = $row;
        }
        
        foreach ($sessions_grouped as $session_data) {
            $session_info = $session_data['info'];
            $students = $session_data['students'];
            
            // Session header
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 8, 'Sesi: ' . date('d/m/Y', strtotime($session_info['tanggal'])) . ' - ' . 
                       $session_info['kode_mk'] . ' (' . $session_info['nama_kelas'] . ')', 0, 1, 'L');
            
            // Table header
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(10, 8, 'No', 1, 0, 'C');
            $pdf->Cell(50, 8, 'Nama Mahasiswa', 1, 0, 'C');
            $pdf->Cell(25, 8, 'NIM', 1, 0, 'C');
            $pdf->Cell(20, 8, 'Status', 1, 0, 'C');
            $pdf->Cell(30, 8, 'Waktu Scan', 1, 1, 'C');
            
            // Table data
            $pdf->SetFont('helvetica', '', 8);
            $no = 1;
            foreach ($students as $student) {
                $pdf->Cell(10, 6, $no++, 1, 0, 'C');
                $pdf->Cell(50, 6, $student['mahasiswa_name'], 1, 0, 'L');
                $pdf->Cell(25, 6, $student['nim_nip'], 1, 0, 'C');
                $pdf->Cell(20, 6, ucfirst($student['status']), 1, 0, 'C');
                $pdf->Cell(30, 6, $student['scan_time'] ? date('H:i:s', strtotime($student['scan_time'])) : '-', 1, 1, 'C');
            }
            
            // Session summary
            $hadir = count(array_filter($students, function($s) { return $s['status'] === 'hadir'; }));
            $sakit = count(array_filter($students, function($s) { return $s['status'] === 'sakit'; }));
            $izin = count(array_filter($students, function($s) { return $s['status'] === 'izin'; }));
            $alfa = count(array_filter($students, function($s) { return $s['status'] === 'alfa'; }));
            $total = count($students);
            $percentage = $total > 0 ? ($hadir / $total) * 100 : 0;
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(85, 6, 'Total:', 1, 0, 'R');
            $pdf->Cell(20, 6, 'H:' . $hadir . ' S:' . $sakit . ' I:' . $izin . ' A:' . $alfa, 1, 0, 'C');
            $pdf->Cell(30, 6, number_format($percentage, 1) . '%', 1, 1, 'C');
            
            $pdf->Ln(5);
        }
    }
    
    // Output PDF
    $filename = 'Laporan_Kehadiran_' . $current_user['full_name'] . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laporan_kehadiran_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Tanggal',
        'Jam',
        'Kode MK',
        'Mata Kuliah',
        'Kelas',
        'Mahasiswa',
        'NIM',
        'Status',
        'Waktu Scan'
    ]);
    
    // CSV Data
    foreach ($attendance_data as $row) {
        fputcsv($output, [
            date('d/m/Y', strtotime($row['tanggal'])),
            $row['jam_mulai'] . ' - ' . $row['jam_selesai'],
            $row['kode_mk'],
            $row['nama_mk'],
            $row['nama_kelas'],
            $row['mahasiswa_name'],
            $row['nim_nip'],
            ucfirst($row['status']),
            $row['scan_time'] ? date('H:i:s', strtotime($row['scan_time'])) : '-'
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
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2d5a27;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .attendance-table th {
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
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .attendance-table {
                font-size: 0.9em;
            }
            
            .attendance-table th,
            .attendance-table td {
                padding: 8px 4px;
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
                        <span><?php echo $current_user['full_name']; ?></span>
                        <a href="../../logout.php" class="logout-btn">Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="container">
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $summary['total_sessions'] ?? 0; ?></div>
                        <div class="stat-label">Total Sesi</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $summary['total_hadir'] ?? 0; ?></div>
                        <div class="stat-label">Total Hadir</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $summary['total_sakit'] ?? 0; ?></div>
                        <div class="stat-label">Total Sakit</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $summary['total_izin'] ?? 0; ?></div>
                        <div class="stat-label">Total Izin</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $summary['total_alfa'] ?? 0; ?></div>
                        <div class="stat-label">Total Alfa</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($attendance_percentage, 1); ?>%</div>
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
                                                <?php echo $class['kode_mk'] . ' - ' . $class['nama_mk'] . ' (' . $class['nama_kelas'] . ')'; ?>
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
                                                $session_total = count($session_students);
                                                $session_percentage = $session_total > 0 ? ($session_hadir / $session_total) * 100 : 0;
                                        ?>
                                                <tr class="session-group">
                                                    <td colspan="8">
                                                        <strong>Sesi: <?php echo date('d/m/Y', strtotime($row['tanggal'])); ?> - 
                                                        <?php echo $row['kode_mk']; ?> (<?php echo $row['nama_kelas']; ?>)</strong>
                                                        - Kehadiran: <?php echo $session_hadir; ?>/<?php echo $session_total; ?> (<?php echo number_format($session_percentage, 1); ?>%)
                                                    </td>
                                                </tr>
                                        <?php endif; ?>
                                        
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                            <td><?php echo $row['jam_mulai'] . ' - ' . $row['jam_selesai']; ?></td>
                                            <td><?php echo $row['kode_mk'] . ' - ' . $row['nama_mk']; ?></td>
                                            <td><?php echo $row['nama_kelas']; ?></td>
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
                fetch(`get-sessions.php?kelas_id=${kelasId}`)
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
