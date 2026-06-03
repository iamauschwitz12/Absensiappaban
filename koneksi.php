<?php
ob_start();

$host = "localhost";
$user = "root";
$pass = "password_kamu";
$db   = "tele";

$conn = mysqli_connect($host, $user, $pass, $db);

// Ambil timezone dari database
$q_time = mysqli_query($conn, "SELECT timezone FROM pengaturan WHERE id=1");
$res_time = mysqli_fetch_assoc($q_time);
$timezone_aktif = $res_time['timezone'] ?? 'Asia/Jakarta';

// Set timezone PHP
date_default_timezone_set($timezone_aktif);

// Set timezone MySQL agar fungsi NOW() di SQL juga ikut berubah
$now = new DateTime();
$mins = $now->getOffset() / 60;
$sgn = ($mins < 0 ? -1 : 1);
$mins = abs($mins);
$hrs = floor($mins / 60);
$mins -= $hrs * 60;
$offset = sprintf('%+d:%02d', $hrs * $sgn, $mins);
mysqli_query($conn, "SET time_zone='$offset'");
?>
