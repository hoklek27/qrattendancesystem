<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Handle delete user
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // Prevent deleting self
    if ($user_id == $current_user['id']) {
        $error = "Anda tidak dapat menghapus akun Anda sendiri.";
    } else {
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$user_id])) {
            $success = "User berhasil dihapus.";
        } else {
            $error = "Gagal menghapus user.";
        }
    }
}

// Get all users
$query = "SELECT * FROM users ORDER BY role, full_name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count users by role
$admin_count = 0;
$dosen_count = 0;
$mahasiswa_count = 0;

foreach ($users as $user) {
    if ($user['role'] == 'admin') {
        $admin_count++;
    } elseif ($user['role'] == 'dosen') {
        $dosen_count++;
    } elseif ($user['role'] == 'mahasiswa') {
        $mahasiswa_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Dashboard Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Kelola Pengguna</h1>
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
                        <div class="stat-number"><?php echo count($users); ?></div>
                        <div class="stat-label">Total Pengguna</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $admin_count; ?></div>
                        <div class="stat-label">Admin</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $dosen_count; ?></div>
                        <div class="stat-label">Dosen</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $mahasiswa_count; ?></div>
                        <div class="stat-label">Mahasiswa</div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="margin-bottom: 20px;">
                    <a href="add-user.php" class="btn btn-primary">+ Tambah Pengguna Baru</a>
                </div>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Daftar Pengguna</h3>
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Nama Lengkap</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>NIM/NIP</th>
                                        <th>Terdaftar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo $user['username']; ?></td>
                                            <td><?php echo $user['full_name']; ?></td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role'] == 'admin' ? 'success' : ($user['role'] == 'dosen' ? 'warning' : 'primary'); ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['nim_nip'] ?? '-'; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">Edit</a>
                                                <?php if ($user['id'] != $current_user['id']): ?>
                                                    <a href="delete-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Apakah Anda yakin ingin menghapus user ini?')">Hapus</a>
                                                <?php endif; ?>
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
</body>
</html>
