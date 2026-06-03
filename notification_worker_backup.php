<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'libs/PHPMailer/src/Exception.php';
require 'libs/PHPMailer/src/PHPMailer.php';
require 'libs/PHPMailer/src/SMTP.php';
include 'koneksi.php';
include 'email_template.php'; // Template HTML yang kita buat sebelumnya

// 1. Ambil Pengaturan SMTP & API
$q_set = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id=1");
$p = mysqli_fetch_assoc($q_set);

// 2. Ambil Antrean yang Statusnya 'pending'
// Kita JOIN dengan tabel siswa agar bisa mendapatkan alamat email siswa tersebut
$query = mysqli_query($conn, "SELECT q.*, s.email, s.nama 
                             FROM wa_queue q 
                             JOIN siswa s ON q.nis = s.nis 
                             WHERE q.status = 'pending' 
                             ORDER BY q.id ASC LIMIT 5");

while($row = mysqli_fetch_assoc($query)) {
    $id = $row['id'];
    $pesan = $row['message'];
    $target_email = $row['email'];

    // --- PROSES KIRIM WHATSAPP ---
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $p['wa_api_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => array('target' => $row['target'], 'message' => $pesan),
        CURLOPT_HTTPHEADER => array("Authorization: " . $p['wa_token']),
    ));
    curl_exec($curl);
    curl_close($curl);

    // --- PROSES KIRIM EMAIL (Penyebab Masalah Anda Ada Di Sini) ---
    if (!empty($target_email)) {
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
            
            // Menggunakan template HTML agar terlihat profesional
            $mail->Body = get_email_template($row['nama'], "TERCATAT", date('H:i:s'), $p['nama_sekolah']);

            $mail->send();
        } catch (Exception $e) {
            // Jika gagal kirim email, biarkan proses WA tetap jalan
        }
    }

    // 3. Update Status Antrean Menjadi 'sent'
    mysqli_query($conn, "UPDATE wa_queue SET status = 'sent' WHERE id = $id");

    // Jeda agar server tidak overload
    sleep(2);
}
?>