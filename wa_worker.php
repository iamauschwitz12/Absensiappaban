<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'libs/PHPMailer/src/Exception.php';
require 'libs/PHPMailer/src/PHPMailer.php';
require 'libs/PHPMailer/src/SMTP.php';
include 'koneksi.php';

// 1. AMBIL PENGATURAN GLOBAL
$q_set = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id=1");
$p = mysqli_fetch_assoc($q_set);

// 2. AMBIL ANTREAN 
// Pastikan nama kolom s.email dan s.telegram_chat_id sesuai dengan struktur tabel siswa milikmu
$query = mysqli_query($conn, "SELECT q.*, s.email, s.nama, s.telegram_chat_id 
                              FROM wa_queue q 
                              JOIN siswa s ON q.nis = s.nis 
                              WHERE q.status = 'pending' 
                              ORDER BY q.id ASC LIMIT 5");

while($row = mysqli_fetch_assoc($query)) {
    $id = $row['id'];
    $pesan_mentah = $row['message'];
    $no_hp = $row['target'];
    $chat_id = $row['telegram_chat_id'];
    $target_email = $row['email'];

    // UPDATE STATUS KE 'sent' SEGERA (Agar tidak dieksekusi dobel)
    mysqli_query($conn, "UPDATE wa_queue SET status = 'sent' WHERE id = $id");

    // --- LOGIKA KODE RAHASIA EMAIL ONLY ---
    $is_email_only = false;
    if (strpos($pesan_mentah, '[EMAIL_ONLY]') === 0) {
        $is_email_only = true;
        // Hapus teks '[EMAIL_ONLY]' dari pesan agar tidak ikut terkirim/terbaca oleh wali murid
        $pesan_fix = str_replace('[EMAIL_ONLY]', '', $pesan_mentah);
    } else {
        $pesan_fix = $pesan_mentah;
    }

    // --- A. JALUR WHATSAPP (Abaikan jika is_email_only aktif) ---
    if (!$is_email_only && !empty($no_hp) && !empty($p['wa_token'])) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $p['wa_api_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => array('target' => $no_hp, 'message' => $pesan_fix),
            CURLOPT_HTTPHEADER => array("Authorization: " . $p['wa_token']),
        ));
        curl_exec($curl);
        curl_close($curl);
    }

    // --- B. JALUR TELEGRAM (Abaikan jika is_email_only aktif) ---
    if (!$is_email_only && !empty($chat_id) && !empty($p['tg_bot_token'])) {
        $url_tg = "https://api.telegram.org/bot" . $p['tg_bot_token'] . "/sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($pesan_fix);
        @file_get_contents($url_tg);
    }

    // --- C. JALUR EMAIL (Selalu dieksekusi jika siswa punya email) ---
    if (!empty($target_email) && !empty($p['smtp_user'])) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $p['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $p['smtp_user'];
            $mail->Password   = $p['smtp_pass'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $p['smtp_port'];

            $mail->setFrom($p['smtp_user'], $p['nama_sekolah']);
            $mail->addAddress($target_email);
            $mail->isHTML(true);
            $mail->Subject = "Laporan Presensi: " . $row['nama'];
            
            // Format HTML Email yang lebih rapi
            $mail->Body    = "<div style='font-family:sans-serif; padding:20px; border:1px solid #eee; border-radius:10px;'>
                                <h3 style='color:#0d6efd;'>Notifikasi Kehadiran</h3>
                                <p>" . nl2br($pesan_fix) . "</p>
                                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                                <small style='color: #888;'>Sistem Absensi Otomatis <b>" . $p['nama_sekolah'] . "</b></small>
                              </div>";
            $mail->send();
        } catch (Exception $e) { }
    }

    // Jeda antar pengiriman (Sangat Penting untuk Email dan WA agar tidak kena Banned)
    sleep(2);
}
?>