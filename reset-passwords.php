<?php
require_once 'config/database.php';

echo "<h2>🔄 Reset Password System</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Generate new password hashes
    $passwords = [
        'admin' => ['username' => 'admin', 'password' => 'admin123'],
        'dosen1' => ['username' => 'dosen1', 'password' => 'dosen123'],
        'dosen2' => ['username' => 'dosen2', 'password' => 'dosen123'],
        'mahasiswa1' => ['username' => 'mahasiswa1', 'password' => 'mahasiswa123'],
        'mahasiswa2' => ['username' => 'mahasiswa2', 'password' => 'mahasiswa123']
    ];
    
    echo "<h3>🔐 Updating Passwords...</h3>";
    
    $query = "UPDATE users SET password = ? WHERE username = ?";
    $stmt = $db->prepare($query);
    
    foreach ($passwords as $user) {
        $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
        $stmt->execute([$hashed_password, $user['username']]);
        echo "✅ Updated password for: <strong>{$user['username']}</strong> → {$user['password']}<br>";
    }
    
    echo "<br><h3>🎯 Login Credentials:</h3>";
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; font-family: monospace;'>";
    echo "<strong>👤 Admin:</strong><br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br><br>";
    
    echo "<strong>👨‍🏫 Dosen:</strong><br>";
    echo "Username: dosen1 | Password: dosen123<br>";
    echo "Username: dosen2 | Password: dosen123<br><br>";
    
    echo "<strong>👨‍🎓 Mahasiswa:</strong><br>";
    echo "Username: mahasiswa1 | Password: mahasiswa123<br>";
    echo "Username: mahasiswa2 | Password: mahasiswa123<br>";
    echo "</div>";
    
    echo "<br><p><strong>✨ Password berhasil direset!</strong> Silakan coba login dengan kredensial di atas.</p>";
    echo "<p><a href='login.php' style='background: #2d5a27; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Login Sekarang</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;'>";
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
    echo "</div>";
}
?>
