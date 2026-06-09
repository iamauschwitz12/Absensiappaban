<?php
// File untuk cek error log / melakukan diagnostic cepat terkait:
// 1) koneksi database
// 2) akses endpoint proses_absen.php

// Tampilkan semua error untuk memudahkan debugging saat test.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Termasuk file koneksi (biasanya mendefinisikan $conn / koneksi MySQLi).
include 'koneksi.php';

// ================================
// Bagian 1: Testing koneksi database
// ================================
// Heading agar jelas terlihat di browser saat halaman dibuka.
echo "<h2>Testing Koneksi Database</h2>";

if($conn) {
    // Jika variabel koneksi tersedia/valid.
    echo "✓ Koneksi database BERHASIL<br>";

    // Test query sederhana: ambil 5 data siswa untuk memastikan tabel dan query dapat dieksekusi.
    $query = mysqli_query($conn, "SELECT nis, nama FROM siswa LIMIT 5");

    if($query) {
        echo "✓ Query berhasil<br>";
        echo "<h3>Data siswa (5 pertama):</h3>";
        
        // Render tabel hasil query agar mudah diverifikasi.
        echo "<table border='1'>";
        echo "<tr><th>NIS</th><th>Nama</th></tr>";
        while($row = mysqli_fetch_assoc($query)) {
            echo "<tr><td>{$row['nis']}</td><td>{$row['nama']}</td></tr>";
        }
        echo "</table>";
    } else {
        // Jika query gagal, tampilkan pesan error MySQLi.
        echo "✗ Query gagal: " . mysqli_error($conn);
    }
} else {
    // Jika koneksi tidak terbentuk.
    echo "✗ Koneksi database GAGAL";
}

// ======================================
// Bagian 2: Testing akses proses_absen.php
// ======================================
// Heading agar terpisah dari testing database.
echo "<h2>Testing proses_absen.php</h2>";

// Susun data POST sederhana untuk men-trigger handler proses_absen.php.
$testData = http_build_query(['nis' => '123']);

// Konfigurasi opsi stream_context untuk melakukan request HTTP POST.
$options = [
    'http' => [
        // Gunakan format application/x-www-form-urlencoded sesuai format form biasa PHP.
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => $testData,
    ],
];

// Buat context dari opsi di atas.
$context  = stream_context_create($options);

// Jalankan request ke proses_absen.php dengan URL berbasis host & direktori aplikasi saat ini.
// @ dipakai untuk menyembunyikan warning agar output tetap terkontrol.
$result = @file_get_contents('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/proses_absen.php', false, $context);

// Jika gagal mengakses (file_get_contents mengembalikan FALSE), tampilkan error terakhir.
if($result === FALSE) {
    echo "✗ Tidak bisa mengakses proses_absen.php<br>";
    echo "Error: " . error_get_last()['message'];
} else {
    // Jika sukses, tampilkan respon mentah (di-escape) agar aman di HTML.
    echo "✓ proses_absen.php bisa diakses<br>";
    echo "Response: <pre>" . htmlspecialchars($result) . "</pre>";
}
?>
