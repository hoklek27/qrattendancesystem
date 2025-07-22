<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$message = '';
$success = false;
$user = null;

$user_id = $_GET['id'] ?? 0;

if (!$user_id) {
    header('Location: users.php');
    exit();
}

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);

if ($stmt->rowCount() === 0) {
    header('Location: users.php');
    exit();
}

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $nim_nip = $_POST['nim_nip'] ?? '';
    
    if (empty($username) || empty($full_name) || empty($email) || empty($role)) {
        $message = 'Username, nama lengkap, email, dan role wajib diisi';
    } else {
        try {
            // Check if username already exists (except for current user)
            $query = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'Username sudah digunakan';
            } else {
                // Update user
                if (!empty($password)) {
                    // Update with new password
                    $query = "UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, role = ?, nim_nip = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$username, $password, $full_name, $email, $role, $nim_nip, $user_id]);
                } else {
                    // Update without changing password
                    $query = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, nim_nip = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$username, $full_name, $email, $role, $nim_nip, $user_id]);
                }
                
                $message = 'User berhasil diperbarui';
                $success = true;
                
                // Refresh user data
                $query = "SELECT * FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Edit User - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Edit User</h1>
                    <div class="user-info">
                        <a href="users.php" style="color: #2d5a27; text-decoration: none; margin-right: 15px;">← Kembali</a>
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
                        <h3 class="card-title">Form Edit User</h3>
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
                                    <input type="text" id="username" name="username" class="form-control" value="<?php echo $user['username']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">Password Baru (Kosongkan jika tidak diubah)</label>
                                    <input type="text" id="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                                </div>
                                
                                <div class="form-group">
                                    <label for="full_name">Nama Lengkap</label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo $user['full_name']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="role">Role</label>
                                    <select id="role" name="role" class="form-control" required>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="dosen" <?php echo $user['role'] == 'dosen' ? 'selected' : ''; ?>>Dosen</option>
                                        <option value="mahasiswa" <?php echo $user['role'] == 'mahasiswa' ? 'selected' : ''; ?>>Mahasiswa</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="nim_nip">NIM/NIP</label>
                                    <input type="text" id="nim_nip" name="nim_nip" class="form-control" value="<?php echo $user['nim_nip']; ?>" placeholder="Opsional">
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                <a href="users.php" class="btn btn-secondary" style="margin-left: 10px;">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi User</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <strong>ID:</strong><br>
                                <?php echo $user['id']; ?>
                            </div>
                            <div>
                                <strong>Terdaftar:</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                            </div>
                            <div>
                                <strong>Terakhir Diperbarui:</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
