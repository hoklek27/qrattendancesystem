<?php
require_once 'config/database.php';

echo "<h2>🔄 Update Password ke Plain Text</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Update all passwords to plain text
    $updates = [
        ['username' => 'admin', 'password' => 'admin123'],
        ['username' => 'dosen1', 'password' => 'dosen123'],
        ['username' => 'dosen2', 'password' => 'dosen123'],
        ['username' => 'mahasiswa1', 'password' => 'mahasiswa123'],
        ['username' => 'mahasiswa2', 'password' => 'mahasiswa123']
    ];
    
    $query = "UPDATE users SET password = ? WHERE username = ?";
    $stmt = $db->prepare($query);
    
    echo "<h3>📝 Updating passwords to plain text...</h3>";
    
    foreach ($updates as $update) {
        $stmt->execute([$update['password'], $update['username']]);
        echo "✅ Updated <strong>{$update['username']}</strong> → password: <code>{$update['password']}</code><br>";
    }
    
    echo "<br><div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 8px;'>";
    echo "<h3>🎯 Login Credentials (Plain Text):</h3>";
    echo "<strong>👤 Admin:</strong> admin / admin123<br>";
    echo "<strong>👨‍🏫 Dosen 1:</strong> dosen1 / dosen123<br>";
    echo "<strong>👨‍🏫 Dosen 2:</strong> dosen2 / dosen123<br>";
    echo "<strong>👨‍🎓 Mahasiswa 1:</strong> mahasiswa1 / mahasiswa123<br>";
    echo "<strong>👨‍🎓 Mahasiswa 2:</strong> mahasiswa2 / mahasiswa123<br>";
    echo "</div>";
    
    echo "<br><p><a href='login.php' style='background: #2d5a27; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Test Login</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;'>";
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
    echo "</div>";
}
?>
