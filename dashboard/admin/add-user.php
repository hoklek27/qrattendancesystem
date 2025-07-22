<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$message = '';
$success = false;

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $nim_nip = $_POST['nim_nip'] ?? '';
    
    if (empty($username) || empty($password) || empty($full_name) || empty($email) || empty($role)) {
        $message = 'Semua field wajib diisi';
    } else {
        try {
            // Check if username already exists
            $query = "SELECT id FROM users WHERE username = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'Username sudah digunakan';
            } else {
                // Insert new user with plain text password
                $query = "INSERT INTO users (username, password, full_name, email, role, nim_nip) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$username, $password, $full_name, $email, $role, $nim_nip])) {
                    $message = 'User berhasil ditambahkan';
                    $success = true;
                } else {
                    $message = 'Gagal menambahkan user';
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
    <title>Tambah User - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Tambah User Baru</h1>
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
                        <h3 class="card-title">Form Tambah User</h3>
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
                                    <label for="username">Username</label>
                                    <input type="text" id="username" name="username" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">Password (Plain Text)</label>
                                    <input type="text" id="password" name="password" class="form-control" required placeholder="Contoh: user123">
                                </div>
                                
                                <div class="form-group">
                                    <label for="full_name">Nama Lengkap</label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="role">Role</label>
                                    <select id="role" name="role" class="form-control" required>
                                        <option value="">-- Pilih Role --</option>
                                        <option value="admin">Admin</option>
                                        <option value="dosen">Dosen</option>
                                        <option value="mahasiswa">Mahasiswa</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="nim_nip">NIM/NIP</label>
                                    <input type="text" id="nim_nip" name="nim_nip" class="form-control" placeholder="Opsional">
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <button type="submit" class="btn btn-primary">Tambah User</button>
                                <a href="index.php" class="btn btn-secondary" style="margin-left: 10px;">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Contoh Password</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Catatan:</strong> Password disimpan dalam bentuk plain text (tidak di-hash) untuk kemudahan.</p>
                        <ul>
                            <li><strong>Admin:</strong> admin123, admin456, dll</li>
                            <li><strong>Dosen:</strong> dosen123, dosen456, dll</li>
                            <li><strong>Mahasiswa:</strong> mahasiswa123, mhs456, dll</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
