<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$class_id = $_GET['class_id'] ?? 0;

if (!$class_id) {
    header('Location: courses.php');
    exit();
}

// Get class details
$query = "SELECT k.*, mk.nama_mk, mk.kode_mk, u.full_name as dosen_name 
          FROM kelas k 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          LEFT JOIN users u ON k.dosen_id = u.id 
          WHERE k.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$class_id]);

if ($stmt->rowCount() === 0) {
    header('Location: courses.php');
    exit();
}

$class = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle enrollment actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $mahasiswa_id = $_POST['mahasiswa_id'] ?? 0;
    
    if ($action === 'enroll' && $mahasiswa_id) {
        // Check if already enrolled
        $query = "SELECT id FROM enrollments WHERE mahasiswa_id = ? AND kelas_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$mahasiswa_id, $class_id]);
        
        if ($stmt->rowCount() === 0) {
            // Enroll student
            $query = "INSERT INTO enrollments (mahasiswa_id, kelas_id) VALUES (?, ?)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$mahasiswa_id, $class_id])) {
                $success = "Mahasiswa berhasil ditambahkan ke kelas.";
            } else {
                $error = "Gagal menambahkan mahasiswa ke kelas.";
            }
        } else {
            $error = "Mahasiswa sudah terdaftar di kelas ini.";
        }
    } elseif ($action === 'unenroll' && $mahasiswa_id) {
        // Unenroll student
        $query = "DELETE FROM enrollments WHERE mahasiswa_id = ? AND kelas_id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$mahasiswa_id, $class_id])) {
            $success = "Mahasiswa berhasil dihapus dari kelas.";
        } else {
            $error = "Gagal menghapus mahasiswa dari kelas.";
        }
    }
}

// Get enrolled students
$query = "SELECT u.id, u.full_name, u.nim_nip, u.email 
          FROM enrollments e 
          JOIN users u ON e.mahasiswa_id = u.id 
          WHERE e.kelas_id = ? 
          ORDER BY u.full_name";
$stmt = $db->prepare($query);
$stmt->execute([$class_id]);
$enrolled_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available students (not enrolled)
$query = "SELECT id, full_name, nim_nip, email 
          FROM users 
          WHERE role = 'mahasiswa' 
          AND id NOT IN (SELECT mahasiswa_id FROM enrollments WHERE kelas_id = ?) 
          ORDER BY full_name";
$stmt = $db->prepare($query);
$stmt->execute([$class_id]);
$available_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mahasiswa Kelas - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Kelola Mahasiswa Kelas</h1>
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
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Class Info -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Kelas</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <strong>Nama Kelas:</strong><br>
                                <?php echo $class['nama_kelas']; ?>
                            </div>
                            <div>
                                <strong>Mata Kuliah:</strong><br>
                                <?php echo $class['kode_mk'] . ' - ' . $class['nama_mk']; ?>
                            </div>
                            <div>
                                <strong>Dosen:</strong><br>
                                <?php echo $class['dosen_name'] ?? '-'; ?>
                            </div>
                            <div>
                                <strong>Ruangan:</strong><br>
                                <?php echo $class['ruangan']; ?>
                            </div>
                            <div>
                                <strong>Jadwal:</strong><br>
                                <?php echo $class['hari'] . ', ' . date('H:i', strtotime($class['jam_mulai'])) . ' - ' . date('H:i', strtotime($class['jam_selesai'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Enrolled Students -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Mahasiswa Terdaftar (<?php echo count($enrolled_students); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($enrolled_students) > 0): ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>NIM</th>
                                            <th>Nama Mahasiswa</th>
                                            <th>Email</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($enrolled_students as $student): ?>
                                            <tr>
                                                <td><?php echo $student['nim_nip']; ?></td>
                                                <td><?php echo $student['full_name']; ?></td>
                                                <td><?php echo $student['email']; ?></td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="unenroll">
                                                        <input type="hidden" name="mahasiswa_id" value="<?php echo $student['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Apakah Anda yakin ingin menghapus mahasiswa ini dari kelas?')">Hapus</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>Belum ada mahasiswa yang terdaftar di kelas ini.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Available Students -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Tambah Mahasiswa ke Kelas</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($available_students) > 0): ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>NIM</th>
                                            <th>Nama Mahasiswa</th>
                                            <th>Email</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($available_students as $student): ?>
                                            <tr>
                                                <td><?php echo $student['nim_nip']; ?></td>
                                                <td><?php echo $student['full_name']; ?></td>
                                                <td><?php echo $student['email']; ?></td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="enroll">
                                                        <input type="hidden" name="mahasiswa_id" value="<?php echo $student['id']; ?>">
                                                        <button type="submit" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">Tambahkan</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>Semua mahasiswa sudah terdaftar di kelas ini.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
