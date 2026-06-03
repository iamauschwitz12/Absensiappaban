<?php
include 'koneksi.php';

// Ambil token dari database
$q = mysqli_query($conn, "SELECT tg_bot_token FROM pengaturan WHERE id=1");
$p = mysqli_fetch_assoc($q);
$token = $p['tg_bot_token'];

$chat_id = "886521209"; // Masukkan ID yang Anda input di data siswa
$pesan = "Tes pengiriman sistem...";

$url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan);
$res = file_get_contents($url);

echo "Respon Telegram: " . $res;
?>