<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Get all mata kuliah with dosen name
$query = "SELECT mk.*, u.full_name as dosen_name 
          FROM mata_kuliah mk 
          LEFT JOIN users u ON mk.dosen_id = u.id 
          ORDER BY mk.kode_mk";
$stmt = $db->prepare($query);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all kelas with mata kuliah and dosen
$query = "SELECT k.*, mk.nama_mk, mk.kode_mk, u.full_name as dosen_name 
          FROM kelas k 
          JOIN mata_kuliah mk ON k.mata_kuliah_id = mk.id 
          LEFT JOIN users u ON k.dosen_id = u.id 
          ORDER BY k.nama_kelas";
$stmt = $db->prepare($query);
$stmt->execute();
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all dosen for dropdown
$query = "SELECT id, full_name, nim_nip FROM users WHERE role = 'dosen' ORDER BY full_name";
$stmt = $db->prepare($query);
$stmt->execute();
$dosen_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mata Kuliah - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Kelola Mata Kuliah</h1>
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
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($courses); ?></div>
                        <div class="stat-label">Total Mata Kuliah</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($classes); ?></div>
                        <div class="stat-label">Total Kelas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($dosen_list); ?></div>
                        <div class="stat-label">Total Dosen</div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="margin-bottom: 20px; display: flex; gap: 10px;">
                    <a href="#" class="btn btn-primary" onclick="showAddCourseForm()">+ Tambah Mata Kuliah</a>
                    <a href="#" class="btn btn-primary" onclick="showAddClassForm()">+ Tambah Kelas</a>
                </div>
                
                <!-- Add Course Form (Hidden by default) -->
                <div id="addCourseForm" class="card" style="display: none; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Tambah Mata Kuliah Baru</h3>
                    </div>
                    <div class="card-body">
                        <form action="add-course.php" method="POST">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="kode_mk">Kode Mata Kuliah</label>
                                    <input type="text" id="kode_mk" name="kode_mk" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="nama_mk">Nama Mata Kuliah</label>
                                    <input type="text" id="nama_mk" name="nama_mk" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="sks">SKS</label>
                                    <input type="number" id="sks" name="sks" class="form-control" min="1" max="6" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="semester">Semester</label>
                                    <input type="number" id="semester" name="semester" class="form-control" min="1" max="8" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="dosen_id">Dosen Pengampu</label>
                                    <select id="dosen_id" name="dosen_id" class="form-control">
                                        <option value="">-- Pilih Dosen --</option>
                                        <?php foreach ($dosen_list as $dosen): ?>
                                            <option value="<?php echo $dosen['id']; ?>">
                                                <?php echo $dosen['full_name'] . ' (' . $dosen['nim_nip'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <button type="submit" class="btn btn-primary">Simpan</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddCourseForm()">Batal</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Add Class Form (Hidden by default) -->
                <div id="addClassForm" class="card" style="display: none; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Tambah Kelas Baru</h3>
                    </div>
                    <div class="card-body">
                        <form action="add-class.php" method="POST">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="nama_kelas">Nama Kelas</label>
                                    <input type="text" id="nama_kelas" name="nama_kelas" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="mata_kuliah_id">Mata Kuliah</label>
                                    <select id="mata_kuliah_id" name="mata_kuliah_id" class="form-control" required>
                                        <option value="">-- Pilih Mata Kuliah --</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['id']; ?>">
                                                <?php echo $course['kode_mk'] . ' - ' . $course['nama_mk']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="dosen_id_class">Dosen Pengajar</label>
                                    <select id="dosen_id_class" name="dosen_id" class="form-control" required>
                                        <option value="">-- Pilih Dosen --</option>
                                        <?php foreach ($dosen_list as $dosen): ?>
                                            <option value="<?php echo $dosen['id']; ?>">
                                                <?php echo $dosen['full_name'] . ' (' . $dosen['nim_nip'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="ruangan">Ruangan</label>
                                    <input type="text" id="ruangan" name="ruangan" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="hari">Hari</label>
                                    <select id="hari" name="hari" class="form-control" required>
                                        <option value="Senin">Senin</option>
                                        <option value="Selasa">Selasa</option>
                                        <option value="Rabu">Rabu</option>
                                        <option value="Kamis">Kamis</option>
                                        <option value="Jumat">Jumat</option>
                                        <option value="Sabtu">Sabtu</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="jam_mulai">Jam Mulai</label>
                                    <input type="time" id="jam_mulai" name="jam_mulai" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="jam_selesai">Jam Selesai</label>
                                    <input type="time" id="jam_selesai" name="jam_selesai" class="form-control" required>
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <button type="submit" class="btn btn-primary">Simpan</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAddClassForm()">Batal</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Mata Kuliah Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Daftar Mata Kuliah</h3>
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Kode MK</th>
                                        <th>Nama Mata Kuliah</th>
                                        <th>SKS</th>
                                        <th>Semester</th>
                                        <th>Dosen Pengampu</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td><?php echo $course['id']; ?></td>
                                            <td><?php echo $course['kode_mk']; ?></td>
                                            <td><?php echo $course['nama_mk']; ?></td>
                                            <td><?php echo $course['sks']; ?></td>
                                            <td><?php echo $course['semester']; ?></td>
                                            <td><?php echo $course['dosen_name'] ?? '-'; ?></td>
                                            <td>
                                                <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">Edit</a>
                                                <a href="delete-course.php?id=<?php echo $course['id']; ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Apakah Anda yakin ingin menghapus mata kuliah ini?')">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Kelas Table -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Daftar Kelas</h3>
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Kelas</th>
                                        <th>Mata Kuliah</th>
                                        <th>Dosen</th>
                                        <th>Ruangan</th>
                                        <th>Hari</th>
                                        <th>Jam</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes as $class): ?>
                                        <tr>
                                            <td><?php echo $class['id']; ?></td>
                                            <td><?php echo $class['nama_kelas']; ?></td>
                                            <td><?php echo $class['kode_mk'] . ' - ' . $class['nama_mk']; ?></td>
                                            <td><?php echo $class['dosen_name'] ?? '-'; ?></td>
                                            <td><?php echo $class['ruangan']; ?></td>
                                            <td><?php echo $class['hari']; ?></td>
                                            <td><?php echo date('H:i', strtotime($class['jam_mulai'])) . ' - ' . date('H:i', strtotime($class['jam_selesai'])); ?></td>
                                            <td>
                                                <a href="edit-class.php?id=<?php echo $class['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">Edit</a>
                                                <a href="delete-class.php?id=<?php echo $class['id']; ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Apakah Anda yakin ingin menghapus kelas ini?')">Hapus</a>
                                                <a href="manage-enrollments.php?class_id=<?php echo $class['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">Mahasiswa</a>
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
    </div>
    
    <script>
        function showAddCourseForm() {
            document.getElementById('addCourseForm').style.display = 'block';
            document.getElementById('addClassForm').style.display = 'none';
        }
        
        function hideAddCourseForm() {
            document.getElementById('addCourseForm').style.display = 'none';
        }
        
        function showAddClassForm() {
            document.getElementById('addClassForm').style.display = 'block';
            document.getElementById('addCourseForm').style.display = 'none';
        }
        
        function hideAddClassForm() {
            document.getElementById('addClassForm').style.display = 'none';
        }
    </script>
</body>
</html>
