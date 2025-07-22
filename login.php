<?php
require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    header("Location: dashboard/$role/index.php");
    exit();
}

$error = '';

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, username, password, full_name, role, nim_nip FROM users WHERE username = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Simple password comparison without hashing
            if ($password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nim_nip'] = $user['nim_nip'];
                
                header("Location: dashboard/{$user['role']}/index.php");
                exit();
            } else {
                $error = 'Password salah';
            }
        } else {
            $error = 'Username tidak ditemukan';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Absensi QR Code</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2>Login Sistem</h2>
                <p>Masuk ke akun Anda</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn-login">Login</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="index.html" style="color: #2d5a27; text-decoration: none;">← Kembali ke Beranda</a>
            </div>
            
            <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 0.9rem;">
                <strong>Demo Login:</strong><br>
                Admin: admin / admin123<br>
                Dosen: dosen1 / dosen123<br>
                Mahasiswa: mahasiswa1 / mahasiswa123
            </div>
        </div>
    </div>
</body>
</html>
