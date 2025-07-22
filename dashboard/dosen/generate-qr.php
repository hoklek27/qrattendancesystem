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

$message = '';
$qr_session = null;

if ($_POST) {
    $kelas_id = $_POST['kelas_id'] ?? '';
    $duration = $_POST['duration'] ?? 30; // default 30 minutes
    
    if (empty($kelas_id)) {
        $message = 'Pilih kelas terlebih dahulu';
    } else {
        // Generate unique QR code
        $qr_code = 'QR_' . time() . '_' . $kelas_id . '_' . rand(1000, 9999);
        $expires_at = date('Y-m-d H:i:s', strtotime("+$duration minutes"));
        
        // Insert QR session
        $query = "INSERT INTO qr_sessions (kelas_id, dosen_id, qr_code, tanggal, jam_mulai, jam_selesai, expires_at) 
                  VALUES (?, ?, ?, CURDATE(), CURTIME(), DATE_ADD(CURTIME(), INTERVAL ? MINUTE), ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$kelas_id, $current_user['id'], $qr_code, $duration, $expires_at])) {
            $session_id = $db->lastInsertId();
            
            // Get session details
            $query = "SELECT qs.*, k.nama_kelas, mk.nama_mk 
                      FROM qr_sessions qs 
                      JOIN kelas k ON qs.kelas_id = k.id 
                      JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
                      WHERE qs.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$session_id]);
            $qr_session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $message = 'QR Code berhasil dibuat!';
        } else {
            $message = 'Gagal membuat QR Code';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate QR Code - Dashboard Dosen</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Generate QR Code</h1>
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
                <?php if (!$qr_session): ?>
                    <!-- QR Generation Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Buat QR Code Absensi</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($message): ?>
                                <div class="alert alert-<?php echo strpos($message, 'berhasil') !== false ? 'success' : 'error'; ?>">
                                    <?php echo $message; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="form-group">
                                    <label for="kelas_id">Pilih Kelas</label>
                                    <select id="kelas_id" name="kelas_id" class="form-control" required>
                                        <option value="">-- Pilih Kelas --</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo $class['kode_mk'] . ' - ' . $class['nama_mk'] . ' (' . $class['nama_kelas'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="duration">Durasi Aktif (menit)</label>
                                    <select id="duration" name="duration" class="form-control">
                                        <option value="15">15 menit</option>
                                        <option value="30" selected>30 menit</option>
                                        <option value="45">45 menit</option>
                                        <option value="60">60 menit</option>
                                        <option value="90">90 menit</option>
                                        <option value="120">120 menit</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Generate QR Code</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- QR Code Display -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">QR Code Absensi</h3>
                        </div>
                        <div class="card-body">
                            <div class="qr-container">
                                <div class="qr-info">
                                    <h4><?php echo $qr_session['nama_mk']; ?></h4>
                                    <p><strong>Kelas:</strong> <?php echo $qr_session['nama_kelas']; ?></p>
                                    <p><strong>Tanggal:</strong> <?php echo date('d/m/Y', strtotime($qr_session['tanggal'])); ?></p>
                                    <p><strong>Waktu:</strong> <?php echo date('H:i', strtotime($qr_session['jam_mulai'])) . ' - ' . date('H:i', strtotime($qr_session['jam_selesai'])); ?></p>
                                    <p><strong>Berakhir:</strong> <?php echo date('d/m/Y H:i:s', strtotime($qr_session['expires_at'])); ?></p>
                                </div>
                                
                                <div class="qr-code">
                                    <div id="qrcode" style="margin: 20px auto; text-align: center;"></div>
                                </div>
                                
                                <div style="margin-top: 20px;">
                                    <p><strong>Kode QR:</strong> <?php echo $qr_session['qr_code']; ?></p>
                                    <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                                        <a href="attendance.php?session_id=<?php echo $qr_session['id']; ?>" class="btn btn-primary">Lihat Kehadiran</a>
                                        <a href="generate-qr.php" class="btn btn-secondary">Buat QR Baru</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Real-time Attendance -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Kehadiran Real-time</h3>
                        </div>
                        <div class="card-body">
                            <div id="attendance-list">
                                <p>Memuat data kehadiran...</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($qr_session): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <script>
        // Generate QR Code using qrcode-generator library
        const qrData = JSON.stringify({
            session_id: <?php echo $qr_session['id']; ?>,
            qr_code: '<?php echo $qr_session['qr_code']; ?>',
            kelas_id: <?php echo $qr_session['kelas_id']; ?>,
            expires_at: '<?php echo $qr_session['expires_at']; ?>'
        });
        
        // Create QR code
        const qr = qrcode(0, 'M');
        qr.addData(qrData);
        qr.make();
        
        // Create QR code element
        const qrElement = qr.createImgTag(8, 10);
        document.getElementById('qrcode').innerHTML = qrElement;
        
        // Auto-refresh attendance every 5 seconds
        function loadAttendance() {
            fetch('get-attendance.php?session_id=<?php echo $qr_session['id']; ?>')
                .then(response => response.json())
                .then(data => {
                    let html = '';
                    if (data.length > 0) {
                        html = '<table class="table"><thead><tr><th>Mahasiswa</th><th>NIM</th><th>Status</th><th>Waktu</th></tr></thead><tbody>';
                        data.forEach(item => {
                            const badgeClass = item.status === 'hadir' ? 'success' : (item.status === 'sakit' ? 'warning' : 'danger');
                            html += `<tr>
                                <td>${item.full_name}</td>
                                <td>${item.nim_nip}</td>
                                <td><span class="badge badge-${badgeClass}">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span></td>
                                <td>${new Date(item.scan_time).toLocaleTimeString()}</td>
                            </tr>`;
                        });
                        html += '</tbody></table>';
                    } else {
                        html = '<p>Belum ada mahasiswa yang absen.</p>';
                    }
                    document.getElementById('attendance-list').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading attendance:', error);
                });
        }
        
        // Load attendance immediately and then every 5 seconds
        loadAttendance();
        setInterval(loadAttendance, 5000);
        
        // Check if QR code is expired
        function checkExpiration() {
            const expiresAt = new Date('<?php echo $qr_session['expires_at']; ?>');
            const now = new Date();
            
            if (now > expiresAt) {
                alert('QR Code telah kedaluwarsa!');
                location.href = 'generate-qr.php';
            }
        }
        
        // Check expiration every minute
        setInterval(checkExpiration, 60000);
    </script>
    <?php endif; ?>
</body>
</html>
