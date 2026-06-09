<?php
// Memulai output buffering agar output ke browser ditunda sampai script selesai dieksekusi
ob_start();

// Konfigurasi host database
$host = "localhost";
// Username database
$user = "root";
// Password database
$pass = "password_kamu";
// Nama database yang digunakan
$db   = "tele";

// Membuat koneksi menggunakan mysqli (menghasilkan resource/connection object)
$conn = mysqli_connect($host, $user, $pass, $db);

// Ambil timezone aktif dari tabel 'pengaturan' untuk id=1
$q_time = mysqli_query($conn, "SELECT timezone FROM pengaturan WHERE id=1");
// Ambil 1 baris hasil query sebagai array asosiatif
$res_time = mysqli_fetch_assoc($q_time);
// Ambil nilai timezone; jika NULL/tidak ada, fallback ke Asia/Jakarta
$timezone_aktif = $res_time['timezone'] ?? 'Asia/Jakarta';

// Set timezone untuk seluruh fungsi tanggal/waktu PHP
date_default_timezone_set($timezone_aktif);

// Set timezone MySQL supaya fungsi NOW() di query SQL ikut menggunakan timezone yang sama
$now = new DateTime();
// Offset timezone saat ini dalam satuan menit
$mins = $now->getOffset() / 60;
// Tentukan tanda offset (+ atau -)
$sgn = ($mins < 0 ? -1 : 1);
// Ambil nilai absolut menit
$mins = abs($mins);
// Hitung jam dari offset menit
$hrs = floor($mins / 60);
// Sisa menit setelah dikurangi jam
$mins -= $hrs * 60;
// Bentuk offset menjadi format '+HH:MM' atau '-HH:MM'
$offset = sprintf('%+d:%02d', $hrs * $sgn, $mins);
// Set timezone pada session MySQL untuk koneksi ini
mysqli_query($conn, "SET time_zone='$offset'");
?>
