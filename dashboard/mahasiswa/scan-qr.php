<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('mahasiswa');

$current_user = getCurrentUser();
$message = '';
$success = false;

// Handle file upload for QR image
if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../uploads/qr_temp/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = uniqid() . '_' . $_FILES['qr_image']['name'];
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['qr_image']['tmp_name'], $uploadPath)) {
        // Here you would typically use a QR code reading library
        // For now, we'll show instructions to manually read the QR
        $message = 'Gambar QR berhasil diupload. Silakan baca kode QR dari gambar dan masukkan secara manual di bawah.';
        
        // Clean up uploaded file after processing
        unlink($uploadPath);
    }
}

if ($_POST && isset($_POST['qr_data'])) {
    $qr_data = trim($_POST['qr_data'] ?? '');
    
    if (empty($qr_data)) {
        $message = 'Data QR Code tidak boleh kosong';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $qr_info = null;
            
            // Try to decode as JSON first
            $decoded = json_decode($qr_data, true);
            if ($decoded && isset($decoded['session_id']) && isset($decoded['qr_code'])) {
                $qr_info = $decoded;
            } else {
                // Try as plain QR code
                if (strpos($qr_data, 'QR_') === 0) {
                    // Find session by QR code
                    $query = "SELECT id, qr_code, kelas_id, expires_at FROM qr_sessions WHERE qr_code = ? AND status = 'active'";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$qr_data]);
                    
                    if ($stmt->rowCount() > 0) {
                        $session = $stmt->fetch(PDO::FETCH_ASSOC);
                        $qr_info = [
                            'session_id' => $session['id'],
                            'qr_code' => $session['qr_code'],
                            'kelas_id' => $session['kelas_id'],
                            'expires_at' => $session['expires_at']
                        ];
                    }
                }
            }
            
            if (!$qr_info) {
                $message = 'Format QR Code tidak valid. Pastikan Anda menyalin kode dengan benar.';
            } else {
                // Verify QR session is still active
                $query = "SELECT qs.*, k.nama_kelas, mk.nama_mk, mk.kode_mk
                          FROM qr_sessions qs 
                          JOIN kelas k ON qs.kelas_id = k.id 
                          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
                          WHERE qs.id = ? AND qs.qr_code = ? AND qs.status = 'active' AND qs.expires_at > NOW()";
                $stmt = $db->prepare($query);
                $stmt->execute([$qr_info['session_id'], $qr_info['qr_code']]);
                
                if ($stmt->rowCount() === 0) {
                    $message = 'QR Code tidak valid, tidak aktif, atau sudah kedaluwarsa';
                } else {
                    $session = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Check if student is enrolled in this class
                    $query = "SELECT id FROM enrollments WHERE mahasiswa_id = ? AND kelas_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$current_user['id'], $session['kelas_id']]);
                    
                    if ($stmt->rowCount() === 0) {
                        $message = 'Anda tidak terdaftar di kelas ' . $session['kode_mk'] . ' - ' . $session['nama_kelas'];
                    } else {
                        // Check if already attended
                        $query = "SELECT id, scan_time FROM attendance WHERE qr_session_id = ? AND mahasiswa_id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$qr_info['session_id'], $current_user['id']]);
                        
                        if ($stmt->rowCount() > 0) {
                            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                            $message = 'Anda sudah melakukan absensi untuk sesi ini pada ' . date('d/m/Y H:i:s', strtotime($existing['scan_time']));
                        } else {
                            // Record attendance
                            $query = "INSERT INTO attendance (qr_session_id, mahasiswa_id, status, scan_time) VALUES (?, ?, 'hadir', NOW())";
                            $stmt = $db->prepare($query);
                            
                            if ($stmt->execute([$qr_info['session_id'], $current_user['id']])) {
                                $message = 'Absensi berhasil dicatat untuk ' . $session['kode_mk'] . ' - ' . $session['nama_mk'] . ' (' . $session['nama_kelas'] . ')';
                                $success = true;
                            } else {
                                $message = 'Gagal mencatat absensi. Silakan coba lagi.';
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $message = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR Code - Dashboard Mahasiswa</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .scanner-container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .camera-container {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        
        #camera {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border: 2px solid #00ff00;
            border-radius: 8px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
        }
        
        .input-method {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .method-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .method-tab {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-weight: 500;
        }
        
        .method-tab.active {
            border-bottom-color: #2d5a27;
            color: #2d5a27;
        }
        
        .method-content {
            display: none;
        }
        
        .method-content.active {
            display: block;
        }
        
        .status-indicator {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        
        .status-waiting {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-scanning {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .https-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .file-upload-area:hover {
            border-color: #2d5a27;
            background: #e8f5e8;
        }
        
        .file-upload-area.dragover {
            border-color: #2d5a27;
            background: #e8f5e8;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Scan QR Code</h1>
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
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Scan QR Code untuk Absensi</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $success ? 'success' : 'error'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="https-warning">
                            <strong>⚠️ Perhatian:</strong> Jika kamera tidak berfungsi di HP, ini karena browser memerlukan HTTPS untuk akses kamera. 
                            Gunakan salah satu metode alternatif di bawah ini.
                        </div>
                        
                        <div class="scanner-container">
                            <div class="method-tabs">
                                <button class="method-tab active" onclick="switchMethod('camera')">📷 Kamera</button>
                                <button class="method-tab" onclick="switchMethod('upload')">📁 Upload Gambar</button>
                                <button class="method-tab" onclick="switchMethod('manual')">⌨️ Input Manual</button>
                            </div>
                            
                            <!-- Camera Method -->
                            <div id="camera-method" class="method-content active">
                                <div id="status-indicator" class="status-indicator status-waiting">
                                    Klik "Mulai Kamera" untuk memulai scan
                                </div>
                                
                                <div class="camera-container">
                                    <video id="camera" autoplay playsinline muted></video>
                                    <div class="scanner-overlay"></div>
                                </div>
                                
                                <div style="text-align: center; margin-top: 20px;">
                                    <button id="startCamera" class="btn btn-primary">Mulai Kamera</button>
                                    <button id="stopCamera" class="btn btn-secondary" style="display: none;">Stop Kamera</button>
                                    <button id="switchCamera" class="btn btn-info" style="display: none;">Ganti Kamera</button>
                                </div>
                            </div>
                            
                            <!-- Upload Method -->
                            <div id="upload-method" class="method-content">
                                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                    <div class="file-upload-area" id="fileUploadArea">
                                        <div>
                                            <h4>📱 Upload Foto QR Code</h4>
                                            <p>Ambil foto QR Code dengan kamera HP, lalu upload di sini</p>
                                            <input type="file" id="qr_image" name="qr_image" accept="image/*" capture="camera" style="display: none;">
                                            <button type="button" class="btn btn-primary" onclick="document.getElementById('qr_image').click()">
                                                Pilih/Ambil Foto
                                            </button>
                                            <p style="margin-top: 10px; font-size: 14px; color: #666;">
                                                Atau drag & drop foto QR code ke area ini
                                            </p>
                                        </div>
                                    </div>
                                    <div id="uploadPreview" style="margin-top: 15px; display: none;">
                                        <img id="previewImage" style="max-width: 200px; border-radius: 8px;">
                                        <p>Foto berhasil dipilih. Klik tombol di bawah untuk upload.</p>
                                        <button type="submit" class="btn btn-success">Upload & Proses</button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Manual Method -->
                            <div id="manual-method" class="method-content">
                                <form method="POST" id="manualForm">
                                    <div class="form-group">
                                        <label for="qr_data">Kode QR</label>
                                        <textarea id="qr_data" name="qr_data" class="form-control" rows="4" placeholder="Masukkan kode QR di sini...

Contoh format yang didukung:
1. QR_1234567890_1_5678
2. {"session_id":123,"qr_code":"QR_1234567890_1_5678","kelas_id":1}"></textarea>
                                        <small class="form-text text-muted">
                                            Salin kode QR yang ditampilkan dosen (bisa berupa kode sederhana atau JSON lengkap)
                                        </small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Submit Absensi</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Solusi Masalah Kamera</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>🔒 Mengapa Kamera Tidak Berfungsi?</h5>
                                <ul>
                                    <li>Browser memerlukan HTTPS untuk akses kamera</li>
                                    <li>Localhost di HP tidak memiliki sertifikat SSL</li>
                                    <li>Kebijakan keamanan browser mobile</li>
                                </ul>
                                
                                <h5 style="margin-top: 20px;">💡 Solusi yang Tersedia:</h5>
                                <ol>
                                    <li><strong>Upload Foto:</strong> Ambil foto QR dengan kamera HP</li>
                                    <li><strong>Input Manual:</strong> Ketik/salin kode QR</li>
                                    <li><strong>Gunakan HTTPS:</strong> Setup SSL untuk localhost</li>
                                </ol>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; padding: 15px; background: #e8f5e8; border-radius: 8px;">
                            <strong>🎯 Rekomendasi untuk Penggunaan Sehari-hari:</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                <li><strong>Di Laptop/PC:</strong> Gunakan kamera untuk scan langsung</li>
                                <li><strong>Di HP:</strong> Gunakan upload foto atau input manual</li>
                                <li><strong>Untuk Produksi:</strong> Setup HTTPS atau gunakan hosting dengan SSL</li>
                                <li><strong>Untuk Testing:</strong> Ngrok adalah solusi tercepat</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <script>
        const video = document.getElementById('camera');
        const startBtn = document.getElementById('startCamera');
        const stopBtn = document.getElementById('stopCamera');
        const switchBtn = document.getElementById('switchCamera');
        const qrDataInput = document.getElementById('qr_data');
        const statusIndicator = document.getElementById('status-indicator');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const qrImageInput = document.getElementById('qr_image');
        const uploadPreview = document.getElementById('uploadPreview');
        const previewImage = document.getElementById('previewImage');
        
        let stream = null;
        let scanning = false;
        let currentFacingMode = 'environment';
        
        // Method switching
        function switchMethod(method) {
            // Update tabs
            document.querySelectorAll('.method-tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update content
            document.querySelectorAll('.method-content').forEach(content => content.classList.remove('active'));
            document.getElementById(method + '-method').classList.add('active');
            
            // Stop camera if switching away from camera method
            if (method !== 'camera') {
                stopCamera();
            }
        }
        
        // Camera functionality
        startBtn.addEventListener('click', startCamera);
        stopBtn.addEventListener('click', stopCamera);
        switchBtn.addEventListener('click', switchCamera);

        function updateStatus(message, type = 'waiting') {
            statusIndicator.textContent = message;
            statusIndicator.className = `status-indicator status-${type}`;
        }

        async function startCamera() {
            try {
                updateStatus('Meminta akses kamera...', 'waiting');
                
                const constraints = {
                    video: {
                        facingMode: currentFacingMode,
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    }
                };
                
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                
                video.onloadedmetadata = () => {
                    video.play();
                    startBtn.style.display = 'none';
                    stopBtn.style.display = 'inline-block';
                    switchBtn.style.display = 'inline-block';
                    
                    scanning = true;
                    updateStatus('Kamera aktif - Arahkan ke QR Code', 'scanning');
                    scanQRCode();
                };
                
            } catch (err) {
                console.error('Camera error:', err);
                updateStatus('Gagal mengakses kamera', 'error');
                
                let errorMsg = 'Tidak dapat mengakses kamera. ';
                if (err.name === 'NotAllowedError') {
                    errorMsg += 'Izinkan akses kamera di browser Anda, atau gunakan metode upload/manual.';
                } else if (err.name === 'NotFoundError') {
                    errorMsg += 'Kamera tidak ditemukan. Gunakan metode upload/manual.';
                } else if (err.name === 'NotSupportedError') {
                    errorMsg += 'Browser tidak mendukung kamera. Gunakan metode upload/manual.';
                } else {
                    errorMsg += 'Coba gunakan HTTPS atau metode alternatif.';
                }
                
                alert(errorMsg);
            }
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            
            video.srcObject = null;
            scanning = false;
            
            startBtn.style.display = 'inline-block';
            stopBtn.style.display = 'none';
            switchBtn.style.display = 'none';
            
            updateStatus('Kamera dimatikan', 'waiting');
        }

        async function switchCamera() {
            stopCamera();
            currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
            await startCamera();
        }

        function scanQRCode() {
            if (!scanning || !video.videoWidth || !video.videoHeight) {
                if (scanning) {
                    requestAnimationFrame(scanQRCode);
                }
                return;
            }
            
            try {
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height);
                
                if (code) {
                    updateStatus('QR Code terdeteksi!', 'scanning');
                    qrDataInput.value = code.data;
                    stopCamera();
                    
                    const preview = code.data.length > 100 ? code.data.substring(0, 100) + '...' : code.data;
                    
                    if (confirm(`QR Code berhasil di-scan!\n\nData: ${preview}\n\nLanjutkan absensi?`)) {
                        document.getElementById('manualForm').submit();
                    } else {
                        updateStatus('Scan dibatalkan', 'waiting');
                    }
                }
            } catch (error) {
                console.error('Scan error:', error);
            }
            
            if (scanning) {
                requestAnimationFrame(scanQRCode);
            }
        }

        // File upload functionality
        qrImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    uploadPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                qrImageInput.files = files;
                qrImageInput.dispatchEvent(new Event('change'));
            }
        });

        // Manual form validation
        document.getElementById('manualForm').addEventListener('submit', function(e) {
            const qrData = qrDataInput.value.trim();
            if (!qrData) {
                e.preventDefault();
                alert('Masukkan kode QR terlebih dahulu!');
                return;
            }
            
            if (!qrData.includes('QR_') && !qrData.includes('session_id')) {
                if (!confirm('Format kode QR mungkin tidak valid. Lanjutkan?')) {
                    e.preventDefault();
                    return;
                }
            }
        });

        // Stop camera when page is unloaded
        window.addEventListener('beforeunload', stopCamera);
    </script>
</body>
</html>
