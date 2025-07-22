<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();

if ($_POST) {
    $nama_kelas = $_POST['nama_kelas'] ?? '';
    $mata_kuliah_id = $_POST['mata_kuliah_id'] ?? '';
    $dosen_id = $_POST['dosen_id'] ?? '';
    $ruangan = $_POST['ruangan'] ?? '';
    $hari = $_POST['hari'] ?? '';
    $jam_mulai = $_POST['jam_mulai'] ?? '';
    $jam_selesai = $_POST['jam_selesai'] ?? '';
    
    if (empty($nama_kelas) || empty($mata_kuliah_id) || empty($dosen_id) || empty($ruangan) || empty($hari) || empty($jam_mulai) || empty($jam_selesai)) {
        $error = "Semua field wajib diisi.";
    } else {
        // Insert new kelas
        $query = "INSERT INTO kelas (nama_kelas, mata_kuliah_id, dosen_id, ruangan, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$nama_kelas, $mata_kuliah_id, $dosen_id, $ruangan, $hari, $jam_mulai, $jam_selesai])) {
            $success = "Kelas berhasil ditambahkan.";
        } else {
            $error = "Gagal menambahkan kelas.";
        }
    }
}

// Redirect back to courses page
header('Location: courses.php' . (isset($error) ? '?error=' . urlencode($error) : (isset($success) ? '?success=' . urlencode($success) : '')));
exit();
?>
