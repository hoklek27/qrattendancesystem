<?php
// Script untuk generate hash password yang benar
echo "<h2>Password Hash Generator</h2>";

$passwords = [
    'admin123' => password_hash('admin123', PASSWORD_DEFAULT),
    'dosen123' => password_hash('dosen123', PASSWORD_DEFAULT),
    'mahasiswa123' => password_hash('mahasiswa123', PASSWORD_DEFAULT)
];

echo "<h3>Generated Password Hashes:</h3>";
foreach ($passwords as $plain => $hash) {
    echo "<strong>$plain:</strong><br>";
    echo "<code>$hash</code><br><br>";
}

echo "<h3>SQL Update Statements:</h3>";
echo "<pre>";
echo "-- Update admin password\n";
echo "UPDATE users SET password = '" . $passwords['admin123'] . "' WHERE username = 'admin';\n\n";

echo "-- Update dosen passwords\n";
echo "UPDATE users SET password = '" . $passwords['dosen123'] . "' WHERE username = 'dosen1';\n";
echo "UPDATE users SET password = '" . $passwords['dosen123'] . "' WHERE username = 'dosen2';\n\n";

echo "-- Update mahasiswa passwords\n";
echo "UPDATE users SET password = '" . $passwords['mahasiswa123'] . "' WHERE username = 'mahasiswa1';\n";
echo "UPDATE users SET password = '" . $passwords['mahasiswa123'] . "' WHERE username = 'mahasiswa2';\n";
echo "</pre>";

// Test verification
echo "<h3>Password Verification Test:</h3>";
foreach ($passwords as $plain => $hash) {
    $verify = password_verify($plain, $hash);
    echo "$plain: " . ($verify ? "✅ Valid" : "❌ Invalid") . "<br>";
}
?>
