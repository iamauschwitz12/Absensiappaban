<?php
session_start();
include 'koneksi.php';

// =========================================================
// 1) AMBIL PENGATURAN SEKOLAH
// =========================================================
// Mengambil seluruh konfigurasi dari tabel `pengaturan` (id=1).
// Ini dipakai untuk jam masuk/pulang masing-masing sesi,
// juga mode notifikasi (WA antrian vs real-time) dan format pesan.
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id = 1");
$stmt_set->execute();
$pengaturan = $stmt_set->get_result()->fetch_assoc();

// Sinkronkan timezone PHP dengan timezone yang tersimpan di database.
date_default_timezone_set($pengaturan['timezone'] ?? 'Asia/Jakarta');

// Penanda waktu untuk logika absensi.
$tgl_hari_ini = date('Y-m-d');
$jam_sekarang = date('H:i:s');
$waktu_lengkap = date('Y-m-d H:i:s');

// =========================================================
// 2) LOGIKA OTOMATISASI STATUS “BOLOS”
// =========================================================
// Jika fitur “wajib_pulang” aktif, maka pada saat waktu melewati batas pulang:
// - absensi siswa yang belum punya waktu_pulang (NULL/00:00:00)
// - dan masih berketerangan 'Hadir'
// akan diubah menjadi 'Bolos'.
if($pengaturan['wajib_pulang'] == 1){
    // Sesi 1: jika sekarang > jam pulang sesi 1
    if($jam_sekarang > $pengaturan['s1_pulang']){
        $conn->query("UPDATE absensi a JOIN siswa s ON a.nis = s.nis SET a.keterangan = 'Bolos' 
                      WHERE s.sesi = '1' AND DATE(a.waktu_masuk) = '$tgl_hari_ini' 
                      AND (a.waktu_pulang IS NULL OR a.waktu_pulang = '00:00:00') AND a.keterangan = 'Hadir'");
    }

    // Sesi 2: jika sekarang > jam pulang sesi 2
    if($jam_sekarang > $pengaturan['s2_pulang']){
        $conn->query("UPDATE absensi a JOIN siswa s ON a.nis = s.nis SET a.keterangan = 'Bolos' 
                      WHERE s.sesi = '2' AND DATE(a.waktu_masuk) = '$tgl_hari_ini' 
                      AND (a.waktu_pulang IS NULL OR a.waktu_pulang = '00:00:00') AND a.keterangan = 'Hadir'");
    }
}

// =========================================================
// 3) HANDLER ABSENSI: input POST { nis }
// =========================================================
// Endpoint ini dipanggil dari client scan (RFID/QR/Face) yang mengirim parameter `nis`.
if(isset($_POST['nis'])){
    // Sanitasi sederhana untuk mengurangi whitespace berlebih.
    // (Catatan: perilaku/hasil tetap sama, hanya komentar.)

    $input = trim($_POST['nis']);

    // --- 3. CARI DATA SISWA (NIS atau RFID) ---
    // Query mencari siswa berdasarkan dua kemungkinan:
    // - nis = input
    // - rfid_uid = input
    $stmt_siswa = $conn->prepare("SELECT * FROM siswa WHERE nis = ? OR rfid_uid = ?");
    $stmt_siswa->bind_param("ss", $input, $input);
    $stmt_siswa->execute();
    $res_siswa = $stmt_siswa->get_result();

    if($res_siswa->num_rows > 0){
        $siswa = $res_siswa->fetch_assoc();
        $nis = $siswa['nis'];
        $nama = $siswa['nama'];
        $hp_ortu = $siswa['no_hp_ortu'];

        // Tangkap kolom identitas notifikasi yang mungkin berbeda sesuai skema DB.
        // (Beberapa proyek memakai telegram_chat_id, yang lain id_telegram_ortu.)
        $tele_id = $siswa['telegram_chat_id'] ?? $siswa['id_telegram_ortu'] ?? '';
        $email_ortu = $siswa['email'] ?? $siswa['email_ortu'] ?? '';

        $foto = $siswa['foto'];
        $kelas = $siswa['kelas'];
        $sesi_siswa = $siswa['sesi'];

        // --- 4. HITUNG PATOKAN JAM MASUK & PULANG BERDASARKAN SESI ---
        // Jika siswa berada di sesi 1, gunakan pengaturan s1_*; jika sesi 2 gunakan s2_*.
        $jam_masuk_patokan = ($sesi_siswa == '1') ? $pengaturan['s1_masuk'] : $pengaturan['s2_masuk'];
        $jam_pulang_patokan = ($sesi_siswa == '1') ? $pengaturan['s1_pulang'] : $pengaturan['s2_pulang'];

        // --- 5. CEK DATA ABSENSI HARI INI ---
        // Cek apakah sudah ada record absensi hari ini berdasarkan:
        // - nis siswa
        // - tanggal dari `waktu_masuk` sama dengan $tgl_hari_ini
        $stmt_cek = $conn->prepare("SELECT * FROM absensi WHERE nis = ? AND DATE(waktu_masuk) = ?");
        $stmt_cek->bind_param("ss", $nis, $tgl_hari_ini);
        $stmt_cek->execute();
        $data_absen = $stmt_cek->get_result()->fetch_assoc();

        if(!$data_absen){
            // --- LOGIKA: ABSEN MASUK ---
            // Status telat ditentukan dari perbandingan jam saat ini vs jam masuk patokan.
            $status_telat = ($jam_sekarang > $jam_masuk_patokan) ? 'Terlambat' : 'Tepat Waktu';

            // Insert baris absensi dengan keterangan 'Hadir'.
            $stmt_ins = $conn->prepare("INSERT INTO absensi (nis, waktu_masuk, status_kehadiran, keterangan) VALUES (?, ?, ?, 'Hadir')");
            $stmt_ins->bind_param("sss", $nis, $waktu_lengkap, $status_telat);

            if($stmt_ins->execute()){
                // Buat pesan sesuai template pengaturan lalu kirim notifikasi.
                $pesan = buatPesan($pengaturan['pesan_masuk'], $nama, date('H:i'), $status_telat);
                kirim_notifikasi_multi($hp_ortu, $tele_id, $email_ortu, $pesan, $pengaturan, $nis);

                // Response untuk client.
                echo json_encode(["status" => "success", "nama" => $nama, "kelas" => $kelas, "foto" => $foto, "pesan" => "Absen Masuk Sesi $sesi_siswa Berhasil!"]);
            }
        } else {
            // Record absensi sudah ada => proses pengecekan ABSEN PULANG atau double scan.

            // Jika sekarang sudah melewati waktu pulang patokan => izinkan absen pulang.
            if($jam_sekarang >= $jam_pulang_patokan){
                // Double scan dicek lewat `waktu_pulang`:
                // - kosong/NULL/00:00:00 => boleh update pulang
                // - selain itu => sudah absen pulang sebelumnya (warning)
                if(empty($data_absen['waktu_pulang']) || $data_absen['waktu_pulang'] == '00:00:00'){
                    // Update waktu pulang dan keterangan kembali ke 'Hadir'.
                    $stmt_upd = $conn->prepare("UPDATE absensi SET waktu_pulang = ?, keterangan = 'Hadir' WHERE id = ?");
                    $stmt_upd->bind_param("si", $waktu_lengkap, $data_absen['id']);
                    $stmt_upd->execute();

                    // Pesan pulang tanpa status telat.
                    $pesan = buatPesan($pengaturan['pesan_pulang'], $nama, date('H:i'), '');
                    kirim_notifikasi_multi($hp_ortu, $tele_id, $email_ortu, $pesan, $pengaturan, $nis);

                    echo json_encode(["status" => "success", "nama" => $nama, "kelas" => $kelas, "foto" => $foto, "pesan" => "Absen Pulang Sesi $sesi_siswa Berhasil!"]);
                } else {
                    // Sudah punya waktu_pulang => double scan.
                    echo json_encode(["status" => "warning", "nama" => $nama, "kelas" => $kelas, "foto" => $foto, "pesan" => "Anda sudah absen pulang!"]);
                }
            } else {
                // Belum waktunya pulang.
                echo json_encode(["status" => "warning", "nama" => $nama, "kelas" => $kelas, "foto" => $foto, "pesan" => "Sudah absen masuk Sesi $sesi_siswa!"]);
            }
        }
    } else {
        // input tidak cocok dengan data siswa.
        echo json_encode(["status" => "error", "nama" => "Gagal", "pesan" => "Kartu/NIS Tidak Dikenal!"]);
    }
}

// =========================================================
// 4) FUNGSI PEMFORMAT PESAN
// =========================================================
// Mengganti placeholder pada template:
// - [nama] => nama siswa/orang tua
// - [jam] => waktu (HH:MM)
// - [telat] => '(Terlambat)' jika status telat, selain itu kosong
function buatPesan($template, $nama, $jam, $status_telat) {
    $p = str_replace("[nama]", $nama, $template);
    $p = str_replace("[jam]", $jam, $p);
    $txt_telat = ($status_telat == 'Terlambat') ? ' (Terlambat)' : '';
    $p = str_replace("[telat]", $txt_telat, $p);
    return $p;
}

// =========================================================
// 5) FUNGSI KIRIM NOTIFIKASI MULTI-CHANNEL
// =========================================================
// Mendukung 2 mode berdasarkan `wa_mode` di pengaturan:
// - wa_mode == 1  : semua WA masuk antrian `wa_queue` (worker mengirimnya)
// - wa_mode == 0  : WA/TG dikirim langsung, email tetap lewat antrean worker.
function kirim_notifikasi_multi($hp, $tele_id, $email, $pesan, $p, $nis) {
    global $conn;

    // Mode antrean WA/TG (WA saja benar-benar antrean; TG tetap bisa real-time bila perlu).
    if ($p['wa_mode'] == 1) {
        $stmt_wa = $conn->prepare("INSERT INTO wa_queue (nis, target, message) VALUES (?, ?, ?)");
        $stmt_wa->bind_param("sss", $nis, $hp, $pesan);
        $stmt_wa->execute();
        return; // Berhenti di sini, sisanya dikerjakan worker
    }

    // Mode real-time: WA & TG langsung dipanggil.

    // 1. KIRIM WHATSAPP LANGSUNG
    if (!empty($hp) && !empty($p['wa_token'])) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $p['wa_api_url'],
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_POST => true,
          CURLOPT_POSTFIELDS => array('target' => $hp, 'message' => $pesan),
          CURLOPT_HTTPHEADER => array("Authorization: " . $p['wa_token']),
        ));
        curl_exec($curl);
        curl_close($curl);
    }

    // 2. KIRIM TELEGRAM LANGSUNG
    if (!empty($tele_id) && !empty($p['tg_bot_token'])) {
        $url_tele = "https://api.telegram.org/bot" . $p['tg_bot_token'] . "/sendMessage?chat_id=" . $tele_id . "&text=" . urlencode($pesan) . "&parse_mode=Markdown";
        @file_get_contents($url_tele);
    }

    // 3. KIRIM EMAIL (SELALU DIALIHKAN KE ANTREAN BACKGROUND WORKER)
    // Agar worker tahu bahwa pesan ini hanya untuk email (bukan WA/TG).
    if (!empty($email)) {
        $pesan_email_only = "[EMAIL_ONLY]" . $pesan;
        $stmt_wa = $conn->prepare("INSERT INTO wa_queue (nis, target, message) VALUES (?, ?, ?)");
        $stmt_wa->bind_param("sss", $nis, $hp, $pesan_email_only);
        $stmt_wa->execute();
    }
}
?>

