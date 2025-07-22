<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('dosen');

$current_user = getCurrentUser();
$database = new Database();
$db = $database->getConnection();

// Handle reactivate QR
if (isset($_POST['reactivate']) && isset($_POST['session_id'])) {
    $session_id = $_POST['session_id'];
    
    // Update expiry time to 15 minutes from now
    $new_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $query = "UPDATE qr_sessions SET expires_at = ?, status = 'active' WHERE id = ? AND kelas_id IN (SELECT id FROM kelas WHERE dosen_id = ?)";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$new_expiry, $session_id, $current_user['id']])) {
        $message = "QR Code berhasil diaktifkan kembali hingga " . date('H:i', strtotime($new_expiry));
        $success = true;
    } else {
        $message = "Gagal mengaktifkan QR Code";
        $success = false;
    }
}

// Get QR sessions history
$query = "SELECT 
            qs.*,
            k.nama_kelas,
            mk.nama_mk,
            mk.kode_mk,
            COUNT(a.id) as attendance_count,
            (SELECT COUNT(*) FROM enrollments WHERE kelas_id = k.id) as total_students
          FROM qr_sessions qs
          JOIN kelas k ON qs.kelas_id = k.id
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id
          LEFT JOIN attendance a ON qs.id = a.qr_session_id
          WHERE k.dosen_id = ?
          GROUP BY qs.id
          ORDER BY qs.created_at DESC
          LIMIT 50";

$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$qr_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR History - Dashboard Dosen</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .qr-display {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .qr-code {
            font-family: monospace;
            font-size: 12px;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            word-break: break-all;
            margin: 10px 0;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active { background: #28a745; color: white; }
        .status-expired { background: #dc3545; color: white; }
        .status-inactive { background: #6c757d; color: white; }
        
        .attendance-stats {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
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
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
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
        
        .close:hover { color: black; }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">QR Code History</h1>
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
                <?php if (isset($message)): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'error'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Riwayat QR Code (50 Terakhir)</h3>
                        <div style="float: right;">
                            <a href="generate-qr.php" class="btn btn-primary">+ Generate QR Baru</a>
                            <a href="reports.php" class="btn btn-info">📊 Laporan</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($qr_sessions)): ?>
                            <div class="alert alert-info">
                                Belum ada QR Code yang dibuat. <a href="generate-qr.php">Buat QR Code pertama</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tanggal Dibuat</th>
                                            <th>Mata Kuliah</th>
                                            <th>Kelas</th>
                                            <th>Status</th>
                                            <th>Kehadiran</th>
                                            <th>Expires</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($qr_sessions as $session): 
                                            $is_expired = strtotime($session['expires_at']) < time();
                                            $is_active = $session['status'] === 'active' && !$is_expired;
                                            
                                            $status_class = 'status-inactive';
                                            $status_text = 'Inactive';
                                            
                                            if ($is_active) {
                                                $status_class = 'status-active';
                                                $status_text = 'Aktif';
                                            } elseif ($is_expired) {
                                                $status_class = 'status-expired';
                                                $status_text = 'Expired';
                                            }
                                            
                                            $attendance_rate = $session['total_students'] > 0 ? 
                                                round(($session['attendance_count'] / $session['total_students']) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo date('d/m/Y', strtotime($session['created_at'])); ?></strong><br>
                                                    <small><?php echo date('H:i', strtotime($session['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo $session['kode_mk']; ?></strong><br>
                                                    <small><?php echo $session['nama_mk']; ?></small>
                                                </td>
                                                <td><?php echo $session['nama_kelas']; ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="attendance-stats">
                                                        <strong><?php echo $session['attendance_count']; ?></strong> / <?php echo $session['total_students']; ?><br>
                                                        <small><?php echo $attendance_rate; ?>% hadir</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y H:i', strtotime($session['expires_at'])); ?>
                                                    <?php if ($is_expired): ?>
                                                        <br><small style="color: #dc3545;">Sudah expired</small>
                                                    <?php else: ?>
                                                        <br><small style="color: #28a745;">
                                                            <?php 
                                                            $remaining = strtotime($session['expires_at']) - time();
                                                            echo $remaining > 0 ? floor($remaining/60) . ' menit lagi' : 'Expired';
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button onclick="showQR('<?php echo $session['id']; ?>')" class="btn btn-sm btn-info">
                                                        👁️ Lihat QR
                                                    </button>
                                                    
                                                    <?php if ($is_expired || $session['status'] !== 'active'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                            <button type="submit" name="reactivate" class="btn btn-sm btn-warning" 
                                                                    onclick="return confirm('Aktifkan kembali QR Code ini untuk 15 menit?')">
                                                                🔄 Aktifkan
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <a href="get-attendance.php?session_id=<?php echo $session['id']; ?>" 
                                                       class="btn btn-sm btn-success" target="_blank">
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

    <!-- QR Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeQR()">&times;</span>
            <div id="qrContent">
                <div style="text-align: center;">
                    <div>Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        function showQR(sessionId) {
            const modal = document.getElementById('qrModal');
            const content = document.getElementById('qrContent');
            
            // Show modal
            modal.style.display = 'block';
            content.innerHTML = '<div style="text-align: center;"><div>Loading QR Code...</div></div>';
            
            // Fetch QR data
            fetch(`get-qr-data.php?session_id=${sessionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const qrData = JSON.stringify({
                            session_id: data.session.id,
                            qr_code: data.session.qr_code,
                            kelas_id: data.session.kelas_id,
                            expires_at: data.session.expires_at
                        });
                        
                        content.innerHTML = `
                            <div class="qr-display">
                                <h4>${data.session.kode_mk} - ${data.session.nama_kelas}</h4>
                                <div id="qrcode-${sessionId}"></div>
                                <div class="qr-code">
                                    <strong>QR Code:</strong><br>
                                    ${data.session.qr_code}
                                </div>
                                <div class="qr-code">
                                    <strong>JSON Data:</strong><br>
                                    ${qrData}
                                </div>
                                <p><strong>Status:</strong> ${data.session.status}</p>
                                <p><strong>Expires:</strong> ${new Date(data.session.expires_at).toLocaleString('id-ID')}</p>
                                <button onclick="copyToClipboard('${data.session.qr_code}')" class="btn btn-primary">
                                    📋 Copy QR Code
                                </button>
                                <button onclick="copyToClipboard('${qrData.replace(/'/g, "\\\'")}')" class="btn btn-secondary">
                                    📋 Copy JSON
                                </button>
                            </div>
                        `;
                        
                        // Generate QR Code
                        QRCode.toCanvas(document.getElementById(`qrcode-${sessionId}`), qrData, {
                            width: 256,
                            margin: 2,
                            color: {
                                dark: '#000000',
                                light: '#FFFFFF'
                            }
                        });
                    } else {
                        content.innerHTML = `<div class="alert alert-error">${data.message}</div>`;
                    }
                })
                .catch(error => {
                    content.innerHTML = `<div class="alert alert-error">Error loading QR Code: ${error.message}</div>`;
                });
        }
        
        function closeQR() {
            document.getElementById('qrModal').style.display = 'none';
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Berhasil disalin ke clipboard!');
            }).catch(err => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Berhasil disalin ke clipboard!');
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('qrModal');
            if (event.target === modal) {
                closeQR();
            }
        }
    </script>
</body>
</html>
