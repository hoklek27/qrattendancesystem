<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$message = '';
$success = false;
$course = null;

$course_id = $_GET['id'] ?? 0;

if (!$course_id) {
    header('Location: courses.php');
    exit();
}

// Get course data
$query = "SELECT * FROM mata_kuliah WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$course_id]);

if ($stmt->rowCount() === 0) {
    header('Location: courses.php');
    exit();
}

$course = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all dosen for dropdown
$query = "SELECT id, full_name, nim_nip FROM users WHERE role = 'dosen' ORDER BY full_name";
$stmt = $db->prepare($query);
$stmt->execute();
$dosen_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_POST) {
    $kode_mk = $_POST['kode_mk'] ?? '';
    $nama_mk = $_POST['nama_mk'] ?? '';
    $sks = $_POST['sks'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $dosen_id = $_POST['dosen_id'] ?? null;
    
    if (empty($kode_mk) || empty($nama_mk) || empty($sks) || empty($semester)) {
        $message = 'Kode MK, nama MK, SKS, dan semester wajib diisi';
    } else {
        try {
            // Check if kode_mk already exists (except for current course)
            $query = "SELECT id FROM mata_kuliah WHERE kode_mk = ? AND id != ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$kode_mk, $course_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'Kode mata kuliah sudah digunakan';
            } else {
                // Update course
                $query = "UPDATE mata_kuliah SET kode_mk = ?, nama_mk = ?, sks = ?, semester = ?, dosen_id = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$kode_mk, $nama_mk, $sks, $semester, $dosen_id, $course_id])) {
                    $message = 'Mata kuliah berhasil diperbarui';
                    $success = true;
                    
                    // Refresh course data
                    $query = "SELECT * FROM mata_kuliah WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $message = 'Gagal memperbarui mata kuliah';
                }
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
    <title>Edit Mata Kuliah - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Edit Mata Kuliah</h1>
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
                        <h3 class="card-title">Form Edit Mata Kuliah</h3>
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
                                    <label for="kode_mk">Kode Mata Kuliah</label>
                                    <input type="text" id="kode_mk" name="kode_mk" class="form-control" value="<?php echo $course['kode_mk']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="nama_mk">Nama Mata Kuliah</label>
                                    <input type="text" id="nama_mk" name="nama_mk" class="form-control" value="<?php echo $course['nama_mk']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="sks">SKS</label>
                                    <input type="number" id="sks" name="sks" class="form-control" min="1" max="6" value="<?php echo $course['sks']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="semester">Semester</label>
                                    <input type="number" id="semester" name="semester" class="form-control" min="1" max="8" value="<?php echo $course['semester']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="dosen_id">Dosen Pengampu</label>
                                    <select id="dosen_id" name="dosen_id" class="form-control">
                                        <option value="">-- Pilih Dosen --</option>
                                        <?php foreach ($dosen_list as $dosen): ?>
                                            <option value="<?php echo $dosen['id']; ?>" <?php echo $course['dosen_id'] == $dosen['id'] ? 'selected' : ''; ?>>
                                                <?php echo $dosen['full_name'] . ' (' . $dosen['nim_nip'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                        <h3 class="card-title">Informasi Mata Kuliah</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <strong>ID:</strong><br>
                                <?php echo $course['id']; ?>
                            </div>
                            <div>
                                <strong>Dibuat:</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($course['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
