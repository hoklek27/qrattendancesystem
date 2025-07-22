<?php
require_once 'config/database.php';

// Script untuk membuat user dengan password yang mudah
$database = new Database();
$db = $database->getConnection();

// Hash passwords
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$dosen_password = password_hash('dosen123', PASSWORD_DEFAULT);
$mahasiswa_password = password_hash('mahasiswa123', PASSWORD_DEFAULT);

try {
    // Clear existing users
    $db->exec("DELETE FROM users");
    
    // Insert admin user
    $query = "INSERT INTO users (username, password, full_name, email, role, nim_nip) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute(['admin', $admin_password, 'Administrator', 'admin@polnes.ac.id', 'admin', 'ADM001']);
    
    // Insert dosen users
    $stmt->execute(['dosen1', $dosen_password, 'Dr. Ahmad Wijaya', 'ahmad.wijaya@polnes.ac.id', 'dosen', 'DSN001']);
    $stmt->execute(['dosen2', $dosen_password, 'Prof. Siti Nurhaliza', 'siti.nurhaliza@polnes.ac.id', 'dosen', 'DSN002']);
    
    // Insert mahasiswa users
    $stmt->execute(['mahasiswa1', $mahasiswa_password, 'Budi Santoso', 'budi.santoso@student.polnes.ac.id', 'mahasiswa', '2023001']);
    $stmt->execute(['mahasiswa2', $mahasiswa_password, 'Ani Rahayu', 'ani.rahayu@student.polnes.ac.id', 'mahasiswa', '2023002']);
    
    echo "✅ Users berhasil dibuat dengan password baru:<br>";
    echo "👤 Admin: admin / admin123<br>";
    echo "👨‍🏫 Dosen: dosen1 / dosen123<br>";
    echo "👨‍🏫 Dosen: dosen2 / dosen123<br>";
    echo "👨‍🎓 Mahasiswa: mahasiswa1 / mahasiswa123<br>";
    echo "👨‍🎓 Mahasiswa: mahasiswa2 / mahasiswa123<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
