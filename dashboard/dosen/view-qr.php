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
    exit;
}

// Get QR session data
$query = "SELECT 
            qs.*,
            k.nama_kelas,
            mk.nama_mk,
            mk.kode_mk,
            COUNT(DISTINCT e.mahasiswa_id) as total_mahasiswa,
            COUNT(DISTINCT a.mahasiswa_id) as hadir_count
          FROM qr_sessions qs
          JOIN kelas k ON qs.kelas_id = k.id
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
          JOIN enrollments e ON k.id = e.kelas_id
          LEFT JOIN attendance a ON qs.id = a.qr_session_id
          WHERE qs.id = ? AND k.dosen_id = ?
          GROUP BY qs.id";

$stmt = $db->prepare($query);
$stmt->execute([$session_id, $current_user['id']]);

if ($stmt->rowCount() === 0) {
    header('Location: index.php?error=QR session not found');
    exit;
}

$qr_session = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if QR is still active
$is_expired = strtotime($qr_session['expires_at']) < time();
$is_active = $qr_session['status'] === 'active' && !$is_expired;

// Get attendance list
$attendance_query = "SELECT 
                      a.*,
                      u.full_name,
                      u.username as nim
                    FROM attendance a
                    JOIN users u ON a.mahasiswa_id = u.id
                    WHERE a.qr_session_id = ?
                    ORDER BY a.created_at DESC";

$attendance_stmt = $db->prepare($attendance_query);
$attendance_stmt->execute([$session_id]);
$attendance_list = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle extend QR
if (isset($_POST['extend_qr'])) {
    $extend_minutes = intval($_POST['extend_minutes']);
    if ($extend_minutes > 0 && $extend_minutes <= 120) {
        $new_expires = date('Y-m-d H:i:s', strtotime("+$extend_minutes minutes"));
        $update_query = "UPDATE qr_sessions SET expires_at = ?, status = 'active' WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([$new_expires, $session_id])) {
            $success_message = "QR Code diperpanjang hingga " . date('H:i', strtotime($new_expires));
            // Refresh data
            header("Location: view-qr.php?session_id=$session_id&success=" . urlencode($success_message));
            exit;
        }
    }
}

// Handle deactivate QR
if (isset($_POST['deactivate_qr'])) {
    $update_query = "UPDATE qr_sessions SET status = 'inactive' WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    
    if ($update_stmt->execute([$session_id])) {
        $success_message = "QR Code berhasil dinonaktifkan";
        header("Location: view-qr.php?session_id=$session_id&success=" . urlencode($success_message));
        exit;
    }
}

$success_message = $_GET['success'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View QR Code - <?php echo $qr_session['kode_mk']; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .qr-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .qr-display {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 2px solid #e9ecef;
        }
        
        .qr-info {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .qr-code-canvas {
            margin: 20px 0;
            border: 3px solid #2d5a27;
            border-radius: 8px;
            padding: 10px;
            background: white;
        }
        
        .status-indicator {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            margin: 10px 0;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .status-expired {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .status-inactive {
            background: #e2e3e5;
            color: #6c757d;
            border: 2px solid #d6d8db;
        }
        
        .attendance-counter {
            background: linear-gradient(135deg, #2d5a27, #4a7c59);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin: 20px 0;
        }
        
        .counter-number {
            font-size: 3em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .progress-bar {
            background: rgba(255,255,255,0.3);
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: white;
            transition: width 0.3s ease;
        }
        
        .qr-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        
        .attendance-list {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .attendance-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .attendance-item:last-child {
            border-bottom: none;
        }
        
        .attendance-item:nth-child(even) {
            background: #f8f9fa;
        }
        
        .student-info {
            flex: 1;
        }
        
        .student-name {
            font-weight: bold;
            color: #2d5a27;
        }
        
        .student-nim {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .attendance-time {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .qr-data-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            margin: 10px 0;
            border: 1px solid #dee2e6;
        }
        
        .copy-button {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .copy-button:hover {
            background: #138496;
        }
        
        .timer-display {
            font-size: 1.2em;
            font-weight: bold;
            color: #dc3545;
            margin: 10px 0;
        }
        
        .extend-form {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
            margin: 10px 0;
        }
        
        @media (max-width: 768px) {
            .qr-container {
                grid-template-columns: 1fr;
            }
            
            .qr-actions {
                flex-direction: column;
            }
            
            .counter-number {
                font-size: 2em;
            }
            
            .attendance-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
        
        .refresh-indicator {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .share-options {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #b8daff;
        }
        
        .share-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        
        .share-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">QR Code - <?php echo $qr_session['kode_mk']; ?></h1>
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
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="qr-container">
                    <!-- QR Code Display -->
                    <div class="qr-display">
                        <h3><?php echo $qr_session['nama_mk']; ?></h3>
                        <p><strong>Kelas:</strong> <?php echo $qr_session['nama_kelas']; ?></p>
                        <p><strong>Tanggal:</strong> <?php echo date('d/m/Y', strtotime($qr_session['tanggal'])); ?></p>
                        <p><strong>Waktu:</strong> <?php echo $qr_session['jam_mulai'] . ' - ' . $qr_session['jam_selesai']; ?></p>
                        
                        <div class="status-indicator status-<?php echo $is_active ? 'active' : ($is_expired ? 'expired' : 'inactive'); ?>">
                            <?php 
                            if ($is_active) {
                                echo '🟢 AKTIF';
                            } elseif ($is_expired) {
                                echo '🔴 EXPIRED';
                            } else {
                                echo '⚫ INACTIVE';
                            }
                            ?>
                        </div>
                        
                        <?php if ($is_active): ?>
                            <div class="timer-display" id="countdown">
                                Menghitung mundur...
                            </div>
                        <?php endif; ?>
                        
                        <div class="qr-code-canvas" id="qrcode"></div>
                        
                        <div class="qr-data-display">
                            <strong>QR Code:</strong> <?php echo $qr_session['qr_code']; ?>
                            <button class="copy-button" onclick="copyToClipboard('<?php echo $qr_session['qr_code']; ?>')">📋 Copy</button>
                        </div>
                        
                        <div class="share-options">
                            <h4>📤 Bagikan QR Code:</h4>
                            <button class="share-button" onclick="shareWhatsApp()">📱 WhatsApp</button>
                            <button class="share-button" onclick="shareQRImage()">🖼️ Download Gambar</button>
                            <button class="share-button" onclick="printQR()">🖨️ Print</button>
                        </div>
                    </div>
                    
                    <!-- QR Info & Controls -->
                    <div class="qr-info">
                        <h3>Informasi QR Code</h3>
                        
                        <div class="attendance-counter">
                            <div>Kehadiran Real-time</div>
                            <div class="counter-number" id="attendanceCount"><?php echo $qr_session['hadir_count']; ?></div>
                            <div>dari <?php echo $qr_session['total_mahasiswa']; ?> mahasiswa</div>
                            
                            <?php 
                            $percentage = $qr_session['total_mahasiswa'] > 0 ? 
                                ($qr_session['hadir_count'] / $qr_session['total_mahasiswa']) * 100 : 0;
                            ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div><?php echo round($percentage, 1); ?>% hadir</div>
                        </div>
                        
                        <div class="form-group">
                            <label><strong>Dibuat:</strong></label>
                            <p><?php echo date('d/m/Y H:i:s', strtotime($qr_session['created_at'])); ?></p>
                        </div>
                        
                        <div class="form-group">
                            <label><strong>Berakhir:</strong></label>
                            <p><?php echo date('d/m/Y H:i:s', strtotime($qr_session['expires_at'])); ?></p>
                        </div>
                        
                        <div class="form-group">
                            <label><strong>Status:</strong></label>
                            <p><?php echo $qr_session['status']; ?></p>
                        </div>
                        
                        <!-- QR Controls -->
                        <div class="qr-actions">
                            <?php if ($is_active): ?>
                                <form method="POST" style="display: inline;">
                                    <button type="submit" name="deactivate_qr" class="btn btn-danger" 
                                            onclick="return confirm('Yakin ingin menonaktifkan QR Code?')">
                                        ⏹️ Nonaktifkan
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if (!$is_active || $is_expired): ?>
                                <div class="extend-form">
                                    <h4>🔄 Perpanjang/Aktifkan QR Code</h4>
                                    <form method="POST" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                        <select name="extend_minutes" class="form-control" style="width: auto;">
                                            <option value="15">15 menit</option>
                                            <option value="30" selected>30 menit</option>
                                            <option value="45">45 menit</option>
                                            <option value="60">60 menit</option>
                                            <option value="90">90 menit</option>
                                            <option value="120">120 menit</option>
                                        </select>
                                        <button type="submit" name="extend_qr" class="btn btn-success">
                                            ⏰ Perpanjang
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            
                            <a href="get-attendance.php?session_id=<?php echo $session_id; ?>" 
                               class="btn btn-info" target="_blank">
                                📊 Detail Kehadiran
                            </a>
                            
                            <button onclick="refreshAttendance()" class="btn btn-primary" id="refreshBtn">
                                🔄 Refresh Data
                            </button>
                            
                            <a href="qr-history.php" class="btn btn-secondary">
                                📜 Riwayat QR
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Live Attendance List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📋 Daftar Kehadiran Live</h3>
                        <div style="margin-left: auto;">
                            <span id="lastUpdate">Terakhir update: <?php echo date('H:i:s'); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="attendance-list" id="attendanceList">
                            <?php if (empty($attendance_list)): ?>
                                <div class="attendance-item">
                                    <div class="student-info">
                                        <div style="text-align: center; color: #6c757d; font-style: italic;">
                                            Belum ada mahasiswa yang hadir
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($attendance_list as $attendance): ?>
                                    <div class="attendance-item">
                                        <div class="student-info">
                                            <div class="student-name"><?php echo htmlspecialchars($attendance['full_name']); ?></div>
                                            <div class="student-nim">NIM: <?php echo htmlspecialchars($attendance['nim']); ?></div>
                                        </div>
                                        <div class="attendance-time">
                                            <?php echo date('H:i:s', strtotime($attendance['created_at'])); ?>
                                            <br>
                                            <small style="color: #28a745;">✓ Hadir</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        // Generate QR Code
        const qrData = JSON.stringify({
            session_id: <?php echo $session_id; ?>,
            qr_code: '<?php echo $qr_session['qr_code']; ?>',
            kelas_id: <?php echo $qr_session['kelas_id']; ?>,
            expires_at: '<?php echo $qr_session['expires_at']; ?>'
        });
        
        QRCode.toCanvas(document.getElementById('qrcode'), qrData, {
            width: 280,
            margin: 2,
            color: {
                dark: '#2d5a27',
                light: '#FFFFFF'
            }
        });
        
        // Countdown timer
        <?php if ($is_active): ?>
        function updateCountdown() {
            const expiresAt = new Date('<?php echo $qr_session['expires_at']; ?>').getTime();
            const now = new Date().getTime();
            const distance = expiresAt - now;
            
            if (distance > 0) {
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('countdown').innerHTML = 
                    `⏰ Berakhir dalam: ${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            } else {
                document.getElementById('countdown').innerHTML = '🔴 QR Code sudah expired';
                document.getElementById('countdown').style.color = '#dc3545';
                // Auto refresh page when expired
                setTimeout(() => location.reload(), 2000);
            }
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
        
        // Auto refresh attendance every 10 seconds
        function refreshAttendance() {
            const refreshBtn = document.getElementById('refreshBtn');
            const originalText = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<span class="refresh-indicator">🔄</span> Refreshing...';
            refreshBtn.disabled = true;
            
            fetch(`get-attendance-live.php?session_id=<?php echo $session_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update attendance count
                        document.getElementById('attendanceCount').textContent = data.attendance_count;
                        
                        // Update progress bar
                        const percentage = data.total_students > 0 ? (data.attendance_count / data.total_students) * 100 : 0;
                        document.querySelector('.progress-fill').style.width = percentage + '%';
                        
                        // Update attendance list
                        const attendanceList = document.getElementById('attendanceList');
                        if (data.attendance_list.length === 0) {
                            attendanceList.innerHTML = `
                                <div class="attendance-item">
                                    <div class="student-info">
                                        <div style="text-align: center; color: #6c757d; font-style: italic;">
                                            Belum ada mahasiswa yang hadir
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            attendanceList.innerHTML = data.attendance_list.map(attendance => `
                                <div class="attendance-item">
                                    <div class="student-info">
                                        <div class="student-name">${attendance.full_name}</div>
                                        <div class="student-nim">NIM: ${attendance.nim}</div>
                                    </div>
                                    <div class="attendance-time">
                                        ${new Date(attendance.created_at).toLocaleTimeString('id-ID')}
                                        <br>
                                        <small style="color: #28a745;">✓ Hadir</small>
                                    </div>
                                </div>
                            `).join('');
                        }
                        
                        // Update last update time
                        document.getElementById('lastUpdate').textContent = 
                            'Terakhir update: ' + new Date().toLocaleTimeString('id-ID');
                    }
                })
                .catch(error => {
                    console.error('Error refreshing attendance:', error);
                })
                .finally(() => {
                    refreshBtn.innerHTML = originalText;
                    refreshBtn.disabled = false;
                });
        }
        
        // Auto refresh every 15 seconds
        setInterval(refreshAttendance, 15000);
        
        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('✅ Berhasil disalin ke clipboard!');
            }).catch(err => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('✅ Berhasil disalin ke clipboard!');
            });
        }
        
        // Share functions
        function shareWhatsApp() {
            const message = `🎓 *Absensi Kuliah*\n\n` +
                           `📚 Mata Kuliah: <?php echo $qr_session['nama_mk']; ?>\n` +
                           `🏫 Kelas: <?php echo $qr_session['nama_kelas']; ?>\n` +
                           `📅 Tanggal: <?php echo date('d/m/Y', strtotime($qr_session['tanggal'])); ?>\n` +
                           `⏰ Waktu: <?php echo $qr_session['jam_mulai'] . ' - ' . $qr_session['jam_selesai']; ?>\n\n` +
                           `🔗 QR Code: <?php echo $qr_session['qr_code']; ?>\n\n` +
                           `Silakan scan QR code atau input manual untuk absensi.`;
            
            const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
        }
        
        function shareQRImage() {
            const canvas = document.querySelector('#qrcode canvas');
            const link = document.createElement('a');
            link.download = `QR_${<?php echo $session_id; ?>}_<?php echo $qr_session['kode_mk']; ?>.png`;
            link.href = canvas.toDataURL();
            link.click();
        }
        
        function printQR() {
            const printWindow = window.open('', '_blank');
            const canvas = document.querySelector('#qrcode canvas');
            const qrImageData = canvas.toDataURL();
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>QR Code - <?php echo $qr_session['kode_mk']; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
                        .qr-print { border: 2px solid #2d5a27; padding: 20px; margin: 20px auto; width: fit-content; }
                        h1 { color: #2d5a27; }
                        .info { margin: 10px 0; }
                    </style>
                </head>
                <body>
                    <h1><?php echo $qr_session['nama_mk']; ?></h1>
                    <div class="qr-print">
                        <img src="${qrImageData}" alt="QR Code" style="width: 300px; height: 300px;">
                        <div class="info"><strong>Kelas:</strong> <?php echo $qr_session['nama_kelas']; ?></div>
                        <div class="info"><strong>Tanggal:</strong> <?php echo date('d/m/Y', strtotime($qr_session['tanggal'])); ?></div>
                        <div class="info"><strong>Waktu:</strong> <?php echo $qr_session['jam_mulai'] . ' - ' . $qr_session['jam_selesai']; ?></div>
                        <div class="info"><strong>QR Code:</strong> <?php echo $qr_session['qr_code']; ?></div>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>
