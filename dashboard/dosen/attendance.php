<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('dosen');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    header('Location: index.php');
    exit();
}

// Get session details
$query = "SELECT qs.*, k.nama_kelas, mk.nama_mk, mk.kode_mk 
          FROM qr_sessions qs 
          JOIN kelas k ON qs.kelas_id = k.id 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          WHERE qs.id = ? AND qs.dosen_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$session_id, $current_user['id']]);

if ($stmt->rowCount() === 0) {
    header('Location: index.php');
    exit();
}

$session = $stmt->fetch(PDO::FETCH_ASSOC);

// Get enrolled students
$query = "SELECT u.id, u.full_name, u.nim_nip 
          FROM enrollments e 
          JOIN users u ON e.mahasiswa_id = u.id 
          WHERE e.kelas_id = ? 
          ORDER BY u.full_name";
$stmt = $db->prepare($query);
$stmt->execute([$session['kelas_id']]);
$enrolled_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance records
$query = "SELECT a.*, u.full_name, u.nim_nip 
          FROM attendance a 
          JOIN users u ON a.mahasiswa_id = u.id 
          WHERE a.qr_session_id = ? 
          ORDER BY a.scan_time";
$stmt = $db->prepare($query);
$stmt->execute([$session_id]);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create attendance map
$attendance_map = [];
foreach ($attendance_records as $record) {
    $attendance_map[$record['mahasiswa_id']] = $record;
}

// Handle manual attendance update
if ($_POST && isset($_POST['update_attendance'])) {
    $student_id = $_POST['student_id'];
    $status = $_POST['status'];
    
    if (isset($attendance_map[$student_id])) {
        // Update existing record
        $query = "UPDATE attendance SET status = ? WHERE qr_session_id = ? AND mahasiswa_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$status, $session_id, $student_id]);
    } else {
        // Insert new record
        $query = "INSERT INTO attendance (qr_session_id, mahasiswa_id, status) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$session_id, $student_id, $status]);
    }
    
    // Refresh page
    header("Location: attendance.php?session_id=$session_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kehadiran - Dashboard Dosen</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Kehadiran Mahasiswa</h1>
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
                <!-- Session Info -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Sesi</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <strong>Mata Kuliah:</strong><br>
                                <?php echo $session['kode_mk'] . ' - ' . $session['nama_mk']; ?>
                            </div>
                            <div>
                                <strong>Kelas:</strong><br>
                                <?php echo $session['nama_kelas']; ?>
                            </div>
                            <div>
                                <strong>Tanggal:</strong><br>
                                <?php echo date('d/m/Y', strtotime($session['tanggal'])); ?>
                            </div>
                            <div>
                                <strong>Waktu:</strong><br>
                                <?php echo date('H:i', strtotime($session['jam_mulai'])) . ' - ' . date('H:i', strtotime($session['jam_selesai'])); ?>
                            </div>
                            <div>
                                <strong>Status:</strong><br>
                                <span class="badge badge-<?php echo $session['status'] == 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($session['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($enrolled_students); ?></div>
                        <div class="stat-label">Total Mahasiswa</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($attendance_records); ?></div>
                        <div class="stat-label">Sudah Absen</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($attendance_records, function($r) { return $r['status'] == 'hadir'; })); ?></div>
                        <div class="stat-label">Hadir</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($enrolled_students) - count($attendance_records); ?></div>
                        <div class="stat-label">Belum Absen</div>
                    </div>
                </div>
                
                <!-- Attendance List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Daftar Kehadiran</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>NIM</th>
                                    <th>Nama Mahasiswa</th>
                                    <th>Status</th>
                                    <th>Waktu Scan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($enrolled_students as $student): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo $student['nim_nip']; ?></td>
                                        <td><?php echo $student['full_name']; ?></td>
                                        <td>
                                            <?php if (isset($attendance_map[$student['id']])): ?>
                                                <span class="badge badge-<?php echo $attendance_map[$student['id']]['status'] == 'hadir' ? 'success' : ($attendance_map[$student['id']]['status'] == 'sakit' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($attendance_map[$student['id']]['status']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Belum Absen</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if (isset($attendance_map[$student['id']])) {
                                                echo date('H:i:s', strtotime($attendance_map[$student['id']]['scan_time']));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline-block;">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <select name="status" onchange="this.form.submit()" style="padding: 4px; border: 1px solid #ddd; border-radius: 4px;">
                                                    <option value="">-- Pilih --</option>
                                                    <option value="hadir" <?php echo (isset($attendance_map[$student['id']]) && $attendance_map[$student['id']]['status'] == 'hadir') ? 'selected' : ''; ?>>Hadir</option>
                                                    <option value="sakit" <?php echo (isset($attendance_map[$student['id']]) && $attendance_map[$student['id']]['status'] == 'sakit') ? 'selected' : ''; ?>>Sakit</option>
                                                    <option value="alfa" <?php echo (isset($attendance_map[$student['id']]) && $attendance_map[$student['id']]['status'] == 'alfa') ? 'selected' : ''; ?>>Alfa</option>
                                                </select>
                                                <input type="hidden" name="update_attendance" value="1">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
