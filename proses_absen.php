<?php
session_start();
include 'koneksi.php';

// --- 1. AMBIL PENGATURAN (PREPARED STATEMENT) ---
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id = 1");
$stmt_set->execute();
$pengaturan = $stmt_set->get_result()->fetch_assoc();

// Set Timezone dari Database agar sinkron
date_default_timezone_set($pengaturan['timezone'] ?? 'Asia/Jakarta');

$tgl_hari_ini = date('Y-m-d');
$jam_sekarang = date('H:i:s');
$waktu_lengkap = date('Y-m-d H:i:s');

// --- 2. LOGIKA OTOMATISASI STATUS BOLOS ---
if($pengaturan['wajib_pulang'] == 1){
    // Sesi 1
    if($jam_sekarang > $pengaturan['s1_pulang']){
        $conn->query("UPDATE absensi a JOIN siswa s ON a.nis = s.nis SET a.keterangan = 'Bolos' 
                      WHERE s.sesi = '1' AND DATE(a.waktu_masuk) = '$tgl_hari_ini' 
                      AND (a.waktu_pulang IS NULL OR a.waktu_pulang = '00:00:00') AND a.keterangan = 'Hadir'");
    }
    // Sesi 2
    if($jam_sekarang > $pengaturan['s2_pulang']){
        $conn->query("UPDATE absensi a JOIN siswa s ON a.nis = s.nis SET a.keterangan = 'Bolos' 
                      WHERE s.sesi = '2' AND DATE(a.waktu_masuk) = '$tgl_hari_ini' 
                      AND (a.waktu_pulang IS NULL OR a.waktu_pulang = '00:00:00') AND a.keterangan = 'Hadir'");
    }
}

if(isset($_POST['nis'])){
    // Sanitasi input tingkat dewa
    $input = trim($_POST['nis']);

    // --- 3. CARI DATA SISWA (NIS atau RFID) ---
    $stmt_siswa = $conn->prepare("SELECT * FROM siswa WHERE nis = ? OR rfid_uid = ?");
    $stmt_siswa->bind_param("ss", $input, $input);
    $stmt_siswa->execute();
    $res_siswa = $stmt_siswa->get_result();
    
    if($res_siswa->num_rows > 0){
        $siswa = $res_siswa->fetch_assoc();
        $nis = $siswa['nis'];
        $nama = $siswa['nama'];
        $hp_ortu = $siswa['no_hp_ortu'];
        
        // Kita tangkap nama kolom apapun yang kamu pakai di database (telegram_chat_id / email)
        $tele_id = $siswa['telegram_chat_id'] ?? $siswa['id_telegram_ortu'] ?? ''; 
        $email_ortu = $siswa['email'] ?? $siswa['email_ortu'] ?? ''; 
        
        $foto = $siswa['foto'];
        $kelas = $siswa['kelas'];
        $sesi_siswa = $siswa['sesi']; 

        // Patokan Jam Sesi
        $jam_masuk_patokan = ($sesi_siswa == '1') ? $pengaturan['s1_masuk'] : $pengaturan['s2_masuk'];
        $jam_pulang_patokan = ($sesi_siswa == '1') ? $pengaturan['s1_pulang'] : $pengaturan['s2_pulang'];
        
        // --- 4. CEK DATA ABSEN HARI INI ---
        $stmt_cek = $conn->prepare("SELECT * FROM absensi WHERE nis = ? AND DATE(waktu_masuk) = ?");
        $stmt_cek->bind_param("ss", $nis, $tgl_hari_ini);
        $stmt_cek->execute();
        $data_absen = $stmt_cek->get_result()->fetch_assoc();

        if(!$data_absen){
            // --- LOGIKA: ABSEN MASUK ---
            $status_telat = ($jam_sekarang > $jam_masuk_patokan) ? 'Terlambat' : 'Tepat Waktu';
            
            $stmt_ins = $conn->prepare("INSERT INTO absensi (nis, waktu_masuk, status_kehadiran, keterangan) VALUES (?, ?, ?, 'Hadir')");
            $stmt_ins->bind_param("sss", $nis, $waktu_lengkap, $status_telat);
            
            if($stmt_ins->execute()){
                $pesan = buatPesan($pengaturan['pesan_masuk'], $nama, date('H:i'), $status_telat);
                kirim_notifikasi_multi($hp_ortu, $tele_id, $email_ortu, $pesan, $pengaturan, $nis);
                
                echo json_encode(["status" => "success", "nama" => $nama, "kelas" => $kelas, "foto" => $foto, "pesan" => "Absen Masuk Sesi $sesi_siswa Berhasil!"]);
            }
        } else {
            // --- LOGIKA: CEK PULANG ATAU DOUBLE SCAN ---
            if($jam_sekarang >= $jam_pulang_patokan){
                if(empty($data_absen['waktu_pulang']) || $data_absen['waktu_pulang'] == '00:00:00'){
                    // Update Absen Pulang
                    $stmt_upd = $conn->prepare("UPDATE absensi SET waktu_pulang = ?, keterangan = 'Hadir' WHERE id = ?");
                    $stmt_upd->bind_param("si", $waktu_lengkap, $data_absen['id']);
                    $stmt_upd->execute();
                    
                    $pesan = buatPesan($pengaturan['pesan_pulang'], $nama, date('H:i'), '');
                    kirim_notifikasi_multi($hp_ortu, $tele_id, $email_ortu, $pesan, $pengaturan, $nis);
                    
                    echo json_encode(["status" => "success", "nama" => $nama, "kelas" => $kelas, "foto" => $foto, "pesan" => "Absen Pulang Sesi $sesi_siswa Berhasil!"]);
                } else {
                    echo json_encode(["status" => "warning", "nama" => $nama, "kelas" => $kelas, "foto" => $foto, "pesan" => "Anda sudah absen pulang!"]);
                }
            } else {
                echo json_encode(["status" => "warning", "nama" => $nama, "kelas" => $kelas, "foto" => $foto, "pesan" => "Sudah absen masuk Sesi $sesi_siswa!"]);
            }
        }
    } else {
        echo json_encode(["status" => "error", "nama" => "Gagal", "pesan" => "Kartu/NIS Tidak Dikenal!"]);
    }
}

// --- FUNGSI FORMAT PESAN ---
function buatPesan($template, $nama, $jam, $status_telat) {
    $p = str_replace("[nama]", $nama, $template);
    $p = str_replace("[jam]", $jam, $p);
    $txt_telat = ($status_telat == 'Terlambat') ? ' (Terlambat)' : '';
    $p = str_replace("[telat]", $txt_telat, $p);
    return $p;
}

// --- FUNGSI KIRIM NOTIFIKASI MULTI-CHANNEL (UPDATE EMAIL SELALU ANTREAN) ---
function kirim_notifikasi_multi($hp, $tele_id, $email, $pesan, $p, $nis) {
    global $conn;
    
    // =========================================================
    // JIKA MODE ANTREAN (wa_mode == 1) -> SEMUA MASUK ANTREAN
    // =========================================================
    if ($p['wa_mode'] == 1) {
        $stmt_wa = $conn->prepare("INSERT INTO wa_queue (nis, target, message) VALUES (?, ?, ?)");
        $stmt_wa->bind_param("sss", $nis, $hp, $pesan);
        $stmt_wa->execute();
        return; // Berhenti di sini, sisanya dikerjakan worker
    }

    // =========================================================
    // JIKA MODE REAL-TIME (wa_mode == 0) -> WA & TG LANGSUNG
    // =========================================================
    
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
    // Kita tempelkan "kode rahasia" [EMAIL_ONLY] agar worker tidak mengirim ulang WA/TG
    if (!empty($email)) {
        $pesan_email_only = "[EMAIL_ONLY]" . $pesan;
        $stmt_wa = $conn->prepare("INSERT INTO wa_queue (nis, target, message) VALUES (?, ?, ?)");
        $stmt_wa->bind_param("sss", $nis, $hp, $pesan_email_only);
        $stmt_wa->execute();
    }
}
?>