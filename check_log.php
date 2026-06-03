<?php
// File untuk cek error log
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Coba koneksi database
include 'koneksi.php';

echo "<h2>Testing Koneksi Database</h2>";
if($conn) {
    echo "✓ Koneksi database BERHASIL<br>";
    
    // Test query
    $query = mysqli_query($conn, "SELECT nis, nama FROM siswa LIMIT 5");
    if($query) {
        echo "✓ Query berhasil<br>";
        echo "<h3>Data siswa (5 pertama):</h3>";
        echo "<table border='1'>";
        echo "<tr><th>NIS</th><th>Nama</th></tr>";
        while($row = mysqli_fetch_assoc($query)) {
            echo "<tr><td>{$row['nis']}</td><td>{$row['nama']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "✗ Query gagal: " . mysqli_error($conn);
    }
} else {
    echo "✗ Koneksi database GAGAL";
}

// Cek apakah proses_absen.php bisa diakses
echo "<h2>Testing proses_absen.php</h2>";
$testData = http_build_query(['nis' => '123']);
$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => $testData,
    ],
];
$context  = stream_context_create($options);
$result = @file_get_contents('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/proses_absen.php', false, $context);

if($result === FALSE) {
    echo "✗ Tidak bisa mengakses proses_absen.php<br>";
    echo "Error: " . error_get_last()['message'];
} else {
    echo "✓ proses_absen.php bisa diakses<br>";
    echo "Response: <pre>" . htmlspecialchars($result) . "</pre>";
}
?>