<?php
require_once 'config/database.php';

// Script untuk membuat user dengan password plain text
$database = new Database();
$db = $database->getConnection();

try {
    // Clear existing users
    $db->exec("DELETE FROM users");
    
    // Insert users with plain text passwords
    $query = "INSERT INTO users (username, password, full_name, email, role, nim_nip) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    // Insert admin user
    $stmt->execute(['admin', 'admin123', 'Administrator', 'admin@polnes.ac.id', 'admin', 'ADM001']);
    
    // Insert dosen users
    $stmt->execute(['dosen1', 'dosen123', 'Dr. Ahmad Wijaya', 'ahmad.wijaya@polnes.ac.id', 'dosen', 'DSN001']);
    $stmt->execute(['dosen2', 'dosen123', 'Prof. Siti Nurhaliza', 'siti.nurhaliza@polnes.ac.id', 'dosen', 'DSN002']);
    
    // Insert mahasiswa users
    $stmt->execute(['mahasiswa1', 'mahasiswa123', 'Budi Santoso', 'budi.santoso@student.polnes.ac.id', 'mahasiswa', '2023001']);
    $stmt->execute(['mahasiswa2', 'mahasiswa123', 'Ani Rahayu', 'ani.rahayu@student.polnes.ac.id', 'mahasiswa', '2023002']);
    
    echo "<h2>✅ Setup Berhasil!</h2>";
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🎯 Login Credentials (Plain Text Password):</h3>";
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
    
    echo "<p><a href='login.php' style='background: #2d5a27; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Login Sekarang</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;'>";
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
    echo "</div>";
}
?>
