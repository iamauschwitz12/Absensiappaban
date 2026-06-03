<?php
session_start();
session_destroy(); // Hapus semua sesi login
header("location: login.php"); // Kembalikan ke halaman login
exit;
?>