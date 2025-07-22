<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$message = '';
$success = false;
$class = null;

$class_id = $_GET['id'] ?? 0;

if (!$class_id) {
    header('Location: courses.php');
    exit();
}

// Get class data
$query = "SELECT * FROM kelas WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$class_id]);

if ($stmt->rowCount() === 0) {
    header('Location: courses.php');
    exit();
}

$class = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all mata kuliah for dropdown
$query = "SELECT id, kode_mk, nama_mk FROM mata_kuliah ORDER BY kode_mk";
$stmt = $db->prepare($query);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all dosen for dropdown
$query = "SELECT id, full_name, nim_nip FROM users WHERE role = 'dosen' ORDER BY full_name";
$stmt = $db->prepare($query);
$stmt->execute();
$dosen_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_POST) {
    $nama_kelas = $_POST['nama_kelas'] ?? '';
    $mata_kuliah_id = $_POST['mata_kuliah_id'] ?? '';
    $dosen_id = $_POST['dosen_id'] ?? '';
    $ruangan = $_POST['ruangan'] ?? '';
    $hari = $_POST['hari'] ?? '';
    $jam_mulai = $_POST['jam_mulai'] ?? '';
    $jam_selesai = $_POST['jam_selesai'] ?? '';
    
    if (empty($nama_kelas) || empty($mata_kuliah_id) || empty($dosen_id) || empty($ruangan) || empty($hari) || empty($jam_mulai) || empty($jam_selesai)) {
        $message = 'Semua field wajib diisi';
    } else {
        try {
            // Update class
            $query = "UPDATE kelas SET nama_kelas = ?, mata_kuliah_id = ?, dosen_id = ?, ruangan = ?, hari = ?, jam_mulai = ?, jam_selesai = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$nama_kelas, $mata_kuliah_id, $dosen_id, $ruangan, $hari, $jam_mulai, $jam_selesai, $class_id])) {
                $message = 'Kelas berhasil diperbarui';
                $success = true;
                
                // Refresh class data
                $query = "SELECT * FROM kelas WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$class_id]);
                $class = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = 'Gagal memperbarui kelas';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kelas - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Edit Kelas</h1>
                    <div class="user-info">
                        <a href="courses.php" style="color: #2d5a27; text-decoration: none; margin-right: 15px;">← Kembali</a>
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
                        <h3 class="card-title">Form Edit Kelas</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $success ? 'success' : 'error'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="nama_kelas">Nama Kelas</label>
                                    <input type="text" id="nama_kelas" name="nama_kelas" class="form-control" value="<?php echo $class['nama_kelas']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="mata_kuliah_id">Mata Kuliah</label>
                                    <select id="mata_kuliah_id" name="mata_kuliah_id" class="form-control" required>
                                        <option value="">-- Pilih Mata Kuliah --</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['id']; ?>" <?php echo $class['mata_kuliah_id'] == $course['id'] ? 'selected' : ''; ?>>
                                                <?php echo $course['kode_mk'] . ' - ' . $course['nama_mk']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="dosen_id">Dosen Pengajar</label>
                                    <select id="dosen_id" name="dosen_id" class="form-control" required>
                                        <option value="">-- Pilih Dosen --</option>
                                        <?php foreach ($dosen_list as $dosen): ?>
                                            <option value="<?php echo $dosen['id']; ?>" <?php echo $class['dosen_id'] == $dosen['id'] ? 'selected' : ''; ?>>
                                                <?php echo $dosen['full_name'] . ' (' . $dosen['nim_nip'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="ruangan">Ruangan</label>
                                    <input type="text" id="ruangan" name="ruangan" class="form-control" value="<?php echo $class['ruangan']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="hari">Hari</label>
                                    <select id="hari" name="hari" class="form-control" required>
                                        <option value="Senin" <?php echo $class['hari'] == 'Senin' ? 'selected' : ''; ?>>Senin</option>
                                        <option value="Selasa" <?php echo $class['hari'] == 'Selasa' ? 'selected' : ''; ?>>Selasa</option>
                                        <option value="Rabu" <?php echo $class['hari'] == 'Rabu' ? 'selected' : ''; ?>>Rabu</option>
                                        <option value="Kamis" <?php echo $class['hari'] == 'Kamis' ? 'selected' : ''; ?>>Kamis</option>
                                        <option value="Jumat" <?php echo $class['hari'] == 'Jumat' ? 'selected' : ''; ?>>Jumat</option>
                                        <option value="Sabtu" <?php echo $class['hari'] == 'Sabtu' ? 'selected' : ''; ?>>Sabtu</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="jam_mulai">Jam Mulai</label>
                                    <input type="time" id="jam_mulai" name="jam_mulai" class="form-control" value="<?php echo $class['jam_mulai']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="jam_selesai">Jam Selesai</label>
                                    <input type="time" id="jam_selesai" name="jam_selesai" class="form-control" value="<?php echo $class['jam_selesai']; ?>" required>
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                <a href="courses.php" class="btn btn-secondary" style="margin-left: 10px;">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Kelas</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <strong>ID:</strong><br>
                                <?php echo $class['id']; ?>
                            </div>
                            <div>
                                <strong>Dibuat:</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($class['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
