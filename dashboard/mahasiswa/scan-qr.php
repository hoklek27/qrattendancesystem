<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('mahasiswa');

$current_user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR Code - Dashboard Mahasiswa</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .scan-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .scan-tabs {
            display: flex;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
            margin-bottom: 0;
        }
        
        .scan-tab {
            flex: 1;
            padding: 15px 20px;
            background: #e9ecef;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .scan-tab.active {
            background: white;
            color: #2d5a27;
        }
        
        .scan-tab:hover {
            background: #dee2e6;
        }
        
        .scan-tab.active:hover {
            background: white;
        }
        
        .scan-content {
            background: white;
            border-radius: 0 0 8px 8px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .tab-panel {
            display: none;
        }
        
        .tab-panel.active {
            display: block;
        }
        
        .camera-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        #video {
            width: 100%;
            max-width: 400px;
            height: 300px;
            border: 2px solid #2d5a27;
            border-radius: 8px;
            background: #000;
        }
        
        .camera-controls {
            margin: 20px 0;
        }
        
        .upload-area {
            border: 2px dashed #2d5a27;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            background: #e9ecef;
            border-color: #1e3a1a;
        }
        
        .upload-area.dragover {
            background: #d4edda;
            border-color: #28a745;
        }
        
        .upload-icon {
            font-size: 3em;
            color: #2d5a27;
            margin-bottom: 15px;
        }
        
        .manual-input {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
            resize: vertical;
        }
        
        .manual-input:focus {
            border-color: #2d5a27;
            outline: none;
        }
        
        .status-selector {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .status-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .status-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .status-option:hover {
            border-color: #2d5a27;
        }
        
        .status-option.selected {
            border-color: #2d5a27;
            background: #d4edda;
        }
        
        .status-option input[type="radio"] {
            margin: 0;
        }
        
        .result-container {
            margin-top: 20px;
            padding: 20px;
            border-radius: 8px;
            display: none;
        }
        
        .result-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .result-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2d5a27;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .preview-image {
            max-width: 300px;
            max-height: 300px;
            border: 2px solid #2d5a27;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        @media (max-width: 768px) {
            .scan-tabs {
                flex-direction: column;
            }
            
            .scan-content {
                padding: 20px;
            }
            
            .status-options {
                flex-direction: column;
            }
            
            .status-option {
                justify-content: center;
            }
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
                <div class="scan-container">
                    <!-- Tabs -->
                    <div class="scan-tabs">
                        <button class="scan-tab active" onclick="switchTab('camera')">📷 Kamera</button>
                        <button class="scan-tab" onclick="switchTab('upload')">📁 Upload Gambar</button>
                        <button class="scan-tab" onclick="switchTab('manual')">⌨️ Input Manual</button>
                    </div>
                    
                    <div class="scan-content">
                        <!-- Status Selector -->
                        <div class="status-selector">
                            <h4 style="text-align: center; margin-bottom: 15px;">Pilih Status Kehadiran:</h4>
                            <div class="status-options">
                                <label class="status-option selected">
                                    <input type="radio" name="attendance_status" value="hadir" checked>
                                    <span>✅ Hadir</span>
                                </label>
                                <label class="status-option">
                                    <input type="radio" name="attendance_status" value="sakit">
                                    <span>🤒 Sakit</span>
                                </label>
                                <label class="status-option">
                                    <input type="radio" name="attendance_status" value="izin">
                                    <span>📝 Izin</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Camera Tab -->
                        <div id="camera-tab" class="tab-panel active">
                            <div class="camera-container">
                                <video id="video" autoplay muted playsinline></video>
                                <canvas id="canvas" style="display: none;"></canvas>
                            </div>
                            
                            <div class="camera-controls">
                                <button id="startCamera" class="btn btn-primary">📷 Mulai Kamera</button>
                                <button id="captureBtn" class="btn btn-success" style="display: none;">📸 Ambil Foto</button>
                                <button id="stopCamera" class="btn btn-secondary" style="display: none;">⏹️ Stop Kamera</button>
                            </div>
                            
                            <div id="cameraError" class="alert alert-warning" style="display: none;">
                                <strong>⚠️ Kamera tidak dapat diakses!</strong><br>
                                Ini biasanya terjadi karena:
                                <ul>
                                    <li>Akses melalui HTTP (bukan HTTPS)</li>
                                    <li>Permission kamera ditolak</li>
                                    <li>Kamera sedang digunakan aplikasi lain</li>
                                </ul>
                                Silakan gunakan metode <strong>Upload Gambar</strong> atau <strong>Input Manual</strong>.
                            </div>
                        </div>
                        
                        <!-- Upload Tab -->
                        <div id="upload-tab" class="tab-panel">
                            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                                <div class="upload-icon">📁</div>
                                <h4>Klik untuk memilih gambar QR Code</h4>
                                <p>atau drag & drop gambar ke sini</p>
                                <p><small>Format: JPG, PNG, WebP (Max: 5MB)</small></p>
                            </div>
                            
                            <input type="file" id="fileInput" accept="image/*" capture="environment" style="display: none;">
                            
                            <div id="imagePreview" style="text-align: center; display: none;">
                                <img id="previewImg" class="preview-image" alt="Preview">
                                <br>
                                <button id="processImage" class="btn btn-primary">🔍 Proses Gambar</button>
                                <button id="clearImage" class="btn btn-secondary">🗑️ Hapus</button>
                            </div>
                        </div>
                        
                        <!-- Manual Tab -->
                        <div id="manual-tab" class="tab-panel">
                            <h4>Input QR Code Manual</h4>
                            <p>Paste kode QR yang diberikan dosen:</p>
                            
                            <textarea id="manualInput" class="manual-input" 
                                      placeholder="Paste QR code di sini...&#10;&#10;Format bisa berupa:&#10;1. JSON: {&quot;session_id&quot;:123,&quot;qr_code&quot;:&quot;ABC123&quot;}&#10;2. String sederhana: ABC123XYZ"></textarea>
                            
                            <div style="margin-top: 15px;">
                                <button id="submitManual" class="btn btn-primary">✅ Submit Absensi</button>
                                <button id="clearManual" class="btn btn-secondary">🗑️ Clear</button>
                            </div>
                        </div>
                        
                        <!-- Loading -->
                        <div id="loading" class="loading">
                            <div class="spinner"></div>
                            <p>Memproses absensi...</p>
                        </div>
                        
                        <!-- Result -->
                        <div id="result" class="result-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <script>
        let stream = null;
        let isProcessing = false;
        
        // Tab switching
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.scan-tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update tab panels
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Stop camera if switching away from camera tab
            if (tabName !== 'camera' && stream) {
                stopCamera();
            }
        }
        
        // Status selector
        document.querySelectorAll('.status-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        
        // Camera functionality
        document.getElementById('startCamera').addEventListener('click', startCamera);
        document.getElementById('captureBtn').addEventListener('click', captureImage);
        document.getElementById('stopCamera').addEventListener('click', stopCamera);
        
        async function startCamera() {
            try {
                const constraints = {
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    }
                };
                
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                const video = document.getElementById('video');
                video.srcObject = stream;
                
                document.getElementById('startCamera').style.display = 'none';
                document.getElementById('captureBtn').style.display = 'inline-block';
                document.getElementById('stopCamera').style.display = 'inline-block';
                document.getElementById('cameraError').style.display = 'none';
                
            } catch (error) {
                console.error('Camera error:', error);
                document.getElementById('cameraError').style.display = 'block';
            }
        }
        
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            
            document.getElementById('startCamera').style.display = 'inline-block';
            document.getElementById('captureBtn').style.display = 'none';
            document.getElementById('stopCamera').style.display = 'none';
        }
        
        function captureImage() {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const context = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0);
            
            // Try to decode QR code
            const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            
            if (code) {
                submitAttendance(code.data);
            } else {
                showResult('error', 'QR Code tidak terdeteksi. Pastikan QR Code terlihat jelas dan coba lagi.');
            }
        }
        
        // Upload functionality
        const fileInput = document.getElementById('fileInput');
        const uploadArea = document.querySelector('.upload-area');
        
        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            if (!file.type.startsWith('image/')) {
                showResult('error', 'File harus berupa gambar (JPG, PNG, WebP)');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                showResult('error', 'Ukuran file maksimal 5MB');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = document.getElementById('previewImg');
                img.src = e.target.result;
                document.getElementById('imagePreview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
        
        document.getElementById('processImage').addEventListener('click', () => {
            const img = document.getElementById('previewImg');
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            context.drawImage(img, 0, 0);
            
            const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            
            if (code) {
                submitAttendance(code.data);
            } else {
                showResult('error', 'QR Code tidak terdeteksi dalam gambar. Pastikan gambar jelas dan QR Code terlihat.');
            }
        });
        
        document.getElementById('clearImage').addEventListener('click', () => {
            document.getElementById('imagePreview').style.display = 'none';
            fileInput.value = '';
        });
        
        // Manual input
        document.getElementById('submitManual').addEventListener('click', () => {
            const input = document.getElementById('manualInput').value.trim();
            if (!input) {
                showResult('error', 'Silakan masukkan kode QR');
                return;
            }
            
            submitAttendance(input);
        });
        
        document.getElementById('clearManual').addEventListener('click', () => {
            document.getElementById('manualInput').value = '';
        });
        
        // Submit attendance
        async function submitAttendance(qrData) {
            if (isProcessing) return;
            
            isProcessing = true;
            showLoading(true);
            hideResult();
            
            const selectedStatus = document.querySelector('input[name="attendance_status"]:checked').value;
            
            // Get location if available
            let latitude = null;
            let longitude = null;
            
            if (navigator.geolocation) {
                try {
                    const position = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, {
                            timeout: 5000,
                            enableHighAccuracy: false
                        });
                    });
                    latitude = position.coords.latitude;
                    longitude = position.coords.longitude;
                } catch (error) {
                    console.log('Location not available:', error);
                }
            }
            
            try {
                const response = await fetch('submit-attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        qr_code: qrData,
                        status: selectedStatus,
                        latitude: latitude,
                        longitude: longitude
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showResult('success', result.message, result.data);
                    
                    // Clear inputs after successful submission
                    document.getElementById('manualInput').value = '';
                    if (document.getElementById('imagePreview').style.display === 'block') {
                        document.getElementById('clearImage').click();
                    }
                } else {
                    showResult('error', result.message);
                }
                
            } catch (error) {
                showResult('error', 'Terjadi kesalahan koneksi. Silakan coba lagi.');
                console.error('Submit error:', error);
            }
            
            showLoading(false);
            isProcessing = false;
        }
        
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }
        
        function showResult(type, message, data = null) {
            const resultDiv = document.getElementById('result');
            resultDiv.className = `result-container result-${type}`;
            
            let html = `<strong>${type === 'success' ? '✅ Berhasil!' : '❌ Error!'}</strong><br>${message}`;
            
            if (data) {
                html += `
                    <div style="margin-top: 15px; padding: 15px; background: rgba(255,255,255,0.3); border-radius: 8px;">
                        <strong>Detail Absensi:</strong><br>
                        📚 Mata Kuliah: ${data.mata_kuliah}<br>
                        🏫 Kelas: ${data.kelas}<br>
                        📅 Tanggal: ${data.tanggal}<br>
                        ⏰ Waktu: ${data.waktu}<br>
                        📋 Status: ${data.status}
                    </div>
                `;
            }
            
            resultDiv.innerHTML = html;
            resultDiv.style.display = 'block';
            
            // Auto hide after 10 seconds for success
            if (type === 'success') {
                setTimeout(() => {
                    hideResult();
                }, 10000);
            }
        }
        
        function hideResult() {
            document.getElementById('result').style.display = 'none';
        }
        
        // Auto-start camera on page load if on camera tab
        window.addEventListener('load', () => {
            // Check if HTTPS or localhost
            if (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1') {
                // Auto start camera after 1 second
                setTimeout(() => {
                    if (document.getElementById('camera-tab').classList.contains('active')) {
                        startCamera();
                    }
                }, 1000);
            } else {
                document.getElementById('cameraError').style.display = 'block';
            }
        });
    </script>
</body>
</html>
