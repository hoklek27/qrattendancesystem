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

// Filter parameters
$selected_class = $_GET['kelas_id'] ?? '';
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_status = $_GET['status'] ?? '';

// Build query for attendance report
$where_conditions = ["1=1"];
$params = [];

if (!empty($selected_class)) {
    $where_conditions[] = "k.id = ?";
    $params[] = $selected_class;
}

if (!empty($selected_month)) {
    $where_conditions[] = "DATE_FORMAT(qs.tanggal, '%Y-%m') = ?";
    $params[] = $selected_month;
}

if (!empty($selected_status)) {
    if ($selected_status === 'hadir') {
        $where_conditions[] = "a.status = 'hadir'";
    } elseif ($selected_status === 'tidak_hadir') {
        $where_conditions[] = "a.id IS NULL";
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get attendance data with evidence
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
            a.status,
            a.scan_time,
            COUNT(DISTINCT e.mahasiswa_id) as total_mahasiswa,
            COUNT(DISTINCT CASE WHEN a.status = 'hadir' THEN a.mahasiswa_id END) as hadir_count
          FROM qr_sessions qs
          JOIN kelas k ON qs.kelas_id = k.id
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
          JOIN users u ON k.dosen_id = u.id
          JOIN enrollments e ON k.id = e.kelas_id
          LEFT JOIN attendance a ON qs.id = a.qr_session_id AND e.mahasiswa_id = a.mahasiswa_id
          LEFT JOIN users m ON a.mahasiswa_id = m.id
          WHERE $where_clause
          GROUP BY qs.id, a.id
          ORDER BY qs.tanggal DESC, qs.jam_mulai DESC, mk.kode_mk, m.full_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$summary_query = "SELECT 
                    COUNT(DISTINCT qs.id) as total_sessions,
                    COUNT(DISTINCT CASE WHEN a.status = 'hadir' THEN a.id END) as total_attendance,
                    COUNT(DISTINCT e.mahasiswa_id) as total_students,
                    COUNT(DISTINCT k.id) as total_classes,
                    AVG(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) * 100 as avg_attendance_rate
                  FROM qr_sessions qs
                  JOIN kelas k ON qs.kelas_id = k.id
                  JOIN enrollments e ON k.id = e.kelas_id
                  LEFT JOIN attendance a ON qs.id = a.qr_session_id AND e.mahasiswa_id = a.mahasiswa_id
                  WHERE $where_clause";

$stmt = $db->prepare($query);
$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Export to CSV
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
            $row['mahasiswa_name'] ?? 'Tidak Hadir',
            $row['nim_nip'] ?? '-',
            $row['status'] ?? 'Tidak Hadir',
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        
        .status-tidak-hadir {
            background: #f8d7da;
            color: #721c24;
        }
        
        .session-group {
            background: #e9ecef;
            font-weight: bold;
        }
        
        .evidence-btn {
            padding: 4px 8px;
            font-size: 0.8em;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            background: #007bff;
            color: white;
        }
        
        .evidence-btn:hover {
            background: #0056b3;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .attendance-table {
                font-size: 0.8em;
            }
            
            .attendance-table th,
            .attendance-table td {
                padding: 6px 4px;
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
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $summary['total_sessions'] ?? 0; ?></div>
                        <div class="stat-label">Total Sesi</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $summary['total_attendance'] ?? 0; ?></div>
                        <div class="stat-label">Total Kehadiran</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $summary['total_students'] ?? 0; ?></div>
                        <div class="stat-label">Total Mahasiswa</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $summary['total_classes'] ?? 0; ?></div>
                        <div class="stat-label">Total Kelas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($summary['avg_attendance_rate'] ?? 0, 1); ?>%</div>
                        <div class="stat-label">Rata-rata Kehadiran</div>
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
                                    <select id="kelas_id" name="kelas_id" class="form-control">
                                        <option value="">Semua Kelas</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo $class['kode_mk'] . ' - ' . $class['nama_mk'] . ' (' . $class['nama_kelas'] . ') - ' . $class['dosen_name']; ?>
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
                
                <!-- Report Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Data Kehadiran</h3>
                        <div style="margin-left: auto;">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success">
                                📊 Export CSV
                            </a>
                        </div>
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
                                            <th>Bukti</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $current_session = null;
                                        foreach ($attendance_data as $row): 
                                            $session_key = $row['session_id'] . '_' . $row['tanggal'];
                                            if ($current_session !== $session_key):
                                                $current_session = $session_key;
                                        ?>
                                                <tr class="session-group">
                                                    <td colspan="10">
                                                        <strong>Sesi: <?php echo date('d/m/Y', strtotime($row['tanggal'])); ?> - 
                                                        <?php echo $row['kode_mk']; ?> (<?php echo $row['nama_kelas']; ?>)</strong>
                                                        - Hadir: <?php echo $row['hadir_count']; ?>/<?php echo $row['total_mahasiswa']; ?>
                                                    </td>
                                                </tr>
                                        <?php endif; ?>
                                        
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                            <td><?php echo $row['jam_mulai'] . ' - ' . $row['jam_selesai']; ?></td>
                                            <td><?php echo $row['kode_mk'] . ' - ' . $row['nama_mk']; ?></td>
                                            <td><?php echo $row['nama_kelas']; ?></td>
                                            <td><?php echo $row['dosen_name']; ?></td>
                                            <td><?php echo $row['mahasiswa_name'] ?? 'Tidak Hadir'; ?></td>
                                            <td><?php echo $row['nim_nip'] ?? '-'; ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $row['status'] ? 'hadir' : 'tidak-hadir'; ?>">
                                                    <?php echo $row['status'] ? ucfirst($row['status']) : 'Tidak Hadir'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['scan_time'] ? date('H:i:s', strtotime($row['scan_time'])) : '-'; ?></td>
                                            <td>
                                                <?php if ($row['status']): ?>
                                                    <button class="evidence-btn" onclick="showEvidence('<?php echo $row['qr_code']; ?>', '<?php echo $row['mahasiswa_name']; ?>', '<?php echo $row['scan_time']; ?>')">
                                                        📋 Bukti
                                                    </button>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
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

    <!-- Evidence Modal -->
    <div id="evidenceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEvidenceModal()">&times;</span>
            <h3>Bukti Kehadiran</h3>
            <div id="evidenceContent"></div>
        </div>
    </div>

    <script>
        function showEvidence(qrCode, studentName, scanTime) {
            const content = `
                <div style="padding: 20px;">
                    <h4>Detail Kehadiran</h4>
                    <p><strong>Mahasiswa:</strong> ${studentName}</p>
                    <p><strong>Waktu Scan:</strong> ${new Date(scanTime).toLocaleString('id-ID')}</p>
                    <p><strong>QR Code:</strong> ${qrCode}</p>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #e8f5e8; border-radius: 8px;">
                        <h5>✅ Verifikasi Kehadiran</h5>
                        <p>Mahasiswa telah berhasil melakukan scan QR Code pada waktu yang tercatat.</p>
                        <p><small>Sistem mencatat waktu scan secara otomatis dan tidak dapat dimanipulasi.</small></p>
                    </div>
                </div>
            `;
            
            document.getElementById('evidenceContent').innerHTML = content;
            document.getElementById('evidenceModal').style.display = 'block';
        }
        
        function closeEvidenceModal() {
            document.getElementById('evidenceModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('evidenceModal');
            if (event.target === modal) {
                closeEvidenceModal();
            }
        }
    </script>
</body>
</html>
