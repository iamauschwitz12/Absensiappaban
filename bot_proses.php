<?php
include 'koneksi.php';

// Ambil token dari database
$q_set = mysqli_query($conn, "SELECT tg_bot_token FROM pengaturan WHERE id=1");
$p = mysqli_fetch_assoc($q_set);
$token = $p['tg_bot_token'];

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];

    // Logika jika pengguna mengetik /start
    if ($text == "/start") {
        $balasan = "Halo, <b>$nama</b>!\n\n";
        $balasan .= "Terima kasih telah bergabung dengan sistem absensi.\n";
        $balasan .= "Nomor Chat ID Anda adalah: <code>$chat_id</code>\n\n";
        $balasan .= "<i>Silakan laporkan nomor tersebut ke admin sekolah agar Anda bisa menerima laporan absensi anak Anda.</i>";

        // Fungsi kirim pesan balik
        $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&parse_mode=html&text=" . urlencode($balasan);
        file_get_contents($url);
    }
}
?>