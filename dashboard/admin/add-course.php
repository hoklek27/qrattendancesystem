<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();

if ($_POST) {
    $kode_mk = $_POST['kode_mk'] ?? '';
    $nama_mk = $_POST['nama_mk'] ?? '';
    $sks = $_POST['sks'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $dosen_id = $_POST['dosen_id'] ?? null;
    
    if (empty($kode_mk) || empty($nama_mk) || empty($sks) || empty($semester)) {
        $error = "Semua field wajib diisi kecuali dosen pengampu.";
    } else {
        // Check if kode_mk already exists
        $query = "SELECT id FROM mata_kuliah WHERE kode_mk = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$kode_mk]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Kode mata kuliah sudah digunakan.";
        } else {
            // Insert new mata kuliah
            $query = "INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, semester, dosen_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$kode_mk, $nama_mk, $sks, $semester, $dosen_id])) {
                $success = "Mata kuliah berhasil ditambahkan.";
            } else {
                $error = "Gagal menambahkan mata kuliah.";
            }
        }
    }
}

// Redirect back to courses page
header('Location: courses.php' . (isset($error) ? '?error=' . urlencode($error) : (isset($success) ? '?success=' . urlencode($success) : '')));
exit();
?>
