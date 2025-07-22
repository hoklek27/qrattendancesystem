-- Database setup for QR Attendance System (Simple Password Version)
CREATE DATABASE IF NOT EXISTS qr_attendance_system;
USE qr_attendance_system;

-- Users table (Admin, Dosen, Mahasiswa)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'dosen', 'mahasiswa') NOT NULL,
    nim_nip VARCHAR(20) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Mata Kuliah table
CREATE TABLE mata_kuliah (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_mk VARCHAR(10) UNIQUE NOT NULL,
    nama_mk VARCHAR(100) NOT NULL,
    sks INT NOT NULL,
    semester INT NOT NULL,
    dosen_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Kelas table
CREATE TABLE kelas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kelas VARCHAR(50) NOT NULL,
    mata_kuliah_id INT,
    dosen_id INT,
    ruangan VARCHAR(50),
    hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu') NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mata_kuliah_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE,
    FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Enrollment table (Mahasiswa enrolled in Kelas)
CREATE TABLE enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mahasiswa_id INT,
    kelas_id INT,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (mahasiswa_id, kelas_id)
);

-- QR Sessions table (for each class session)
CREATE TABLE qr_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kelas_id INT,
    dosen_id INT,
    qr_code VARCHAR(255) UNIQUE NOT NULL,
    tanggal DATE NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    status ENUM('active', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    qr_session_id INT,
    mahasiswa_id INT,
    status ENUM('hadir', 'sakit', 'alfa') DEFAULT 'hadir',
    scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    FOREIGN KEY (qr_session_id) REFERENCES qr_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (qr_session_id, mahasiswa_id)
);

-- Insert users with plain text passwords
INSERT INTO users (username, password, full_name, email, role, nim_nip) VALUES 
('admin', 'admin123', 'Administrator', 'admin@polnes.ac.id', 'admin', 'ADM001'),
('dosen1', 'dosen123', 'Dr. Ahmad Wijaya', 'ahmad.wijaya@polnes.ac.id', 'dosen', 'DSN001'),
('dosen2', 'dosen123', 'Prof. Siti Nurhaliza', 'siti.nurhaliza@polnes.ac.id', 'dosen', 'DSN002'),
('mahasiswa1', 'mahasiswa123', 'Budi Santoso', 'budi.santoso@student.polnes.ac.id', 'mahasiswa', '2023001'),
('mahasiswa2', 'mahasiswa123', 'Ani Rahayu', 'ani.rahayu@student.polnes.ac.id', 'mahasiswa', '2023002');

-- Insert sample mata kuliah
INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, semester, dosen_id) VALUES 
('TI101', 'Pemrograman Dasar', 3, 1, 2),
('TI102', 'Basis Data', 3, 2, 3);

-- Insert sample kelas
INSERT INTO kelas (nama_kelas, mata_kuliah_id, dosen_id, ruangan, hari, jam_mulai, jam_selesai) VALUES 
('TI-1A', 1, 2, 'Lab Komputer 1', 'Senin', '08:00:00', '10:30:00'),
('TI-1B', 2, 3, 'Lab Komputer 2', 'Selasa', '10:30:00', '13:00:00');

-- Insert sample enrollments
INSERT INTO enrollments (mahasiswa_id, kelas_id) VALUES 
(4, 1), (5, 1), (4, 2), (5, 2);
