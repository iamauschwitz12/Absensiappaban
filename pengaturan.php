<?php
session_start();
include 'koneksi.php';

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- SECURITY ENGINE: XSS PROTECTION & CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// --- 1. CEK KEAMANAN (ADMIN ONLY) ---
// Ditambah pengecekan ID sesi untuk mencegah Session Hijacking dasar
if(!isset($_SESSION['login']) || $_SESSION['role'] != 'admin' || !isset($_SESSION['id'])){
    header("location: dashboard.php");
    exit;
}

// --- LOGIKA LIBUR MANUAL (GOD-TIER SECURITY) ---
if(isset($_POST['tambah_libur'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Akses Ilegal!");
    
    $tgl = $_POST['tgl_libur'];
    $ket = trim($_POST['ket_libur']);
    
    $stmt = $conn->prepare("INSERT INTO libur_manual (tanggal, keterangan) VALUES (?, ?)");
    $stmt->bind_param("ss", $tgl, $ket);
    $stmt->execute();
    header("location: pengaturan.php");
    exit; 
}

if(isset($_GET['hapus_libur'])){
    if(!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) die("Token Keamanan Tidak Valid!");
    
    $id = (int)$_GET['hapus_libur'];
    $stmt = $conn->prepare("DELETE FROM libur_manual WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("location: pengaturan.php");
    exit; 
}

// --- 2. AMBIL DATA SAAT INI ---
$stmt_data = $conn->prepare("SELECT * FROM pengaturan WHERE id=1");
$stmt_data->execute();
$data = $stmt_data->get_result()->fetch_assoc();

// --- 3. LOGIKA UJI COBA KIRIM WA ---
if(isset($_POST['test_wa'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Akses Ilegal!");
    
    $token  = $_POST['wa_token'];
    $url    = $_POST['wa_api_url'];
    $target = preg_replace('/[^0-9]/', '', $_POST['test_nomor']); // Hanya angka
    $pesan  = "Tes Koneksi WhatsApp dari " . $data['nama_sekolah'] . " BERHASIL.\nToken dan URL API sudah valid.";

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => array('target' => $target, 'message' => $pesan),
      CURLOPT_HTTPHEADER => array("Authorization: $token"),
    ));
    $res = curl_exec($curl);
    curl_close($curl);
    
    $result = json_decode($res, true);
    $user_msg = (isset($result['status']) && $result['status'] == true) ? "Pesan WA Terkirim!" : "Pesan WA Gagal! Periksa Konfigurasi.";
    echo "<script>alert('$user_msg'); window.location='pengaturan.php';</script>";
}

// --- 4. LOGIKA UJI COBA KIRIM TELEGRAM ---
if(isset($_POST['test_tg'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Akses Ilegal!");
    
    $token   = $data['tg_bot_token'];
    $chat_id = preg_replace('/[^0-9-]/', '', $_POST['test_chat_id']);
    $pesan   = "🔔 *TES KONEKSI TELEGRAM*\nBot berhasil terhubung dengan sistem " . $data['nama_sekolah'] . ".";

    $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($pesan) . "&parse_mode=Markdown";
    $res = @file_get_contents($url);
    $res_arr = json_decode($res, true);

    $user_msg = (isset($res_arr['ok']) && $res_arr['ok'] == true) ? "Telegram Berhasil!" : "Telegram Gagal!";
    echo "<script>alert('$user_msg'); window.location='pengaturan.php';</script>";
}

// --- 5. LOGIKA UJI COBA KIRIM EMAIL ---
if(isset($_POST['test_email'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Akses Ilegal!");
    
    require 'libs/PHPMailer/src/Exception.php';
    require 'libs/PHPMailer/src/PHPMailer.php';
    require 'libs/PHPMailer/src/SMTP.php';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $data['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $data['smtp_user'];
        $mail->Password   = $data['smtp_pass']; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$data['smtp_port'];

        $mail->setFrom($data['smtp_user'], $data['nama_sekolah']);
        $mail->addAddress(filter_var($_POST['test_email_addr'], FILTER_SANITIZE_EMAIL));
        $mail->isHTML(true);
        $mail->Subject = 'Uji Coba Sistem Email Absensi';
        $mail->Body    = "<h3>Koneksi Berhasil!</h3><p>Sistem <b>" . $data['nama_sekolah'] . "</b> kini siap mengirim laporan.</p>";

        $mail->send();
        echo "<script>alert('Email Berhasil Terkirim!'); window.location='pengaturan.php';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Email Gagal! SMTP Error'); window.location='pengaturan.php';</script>";
    }
}

// --- 6. LOGIKA UPDATE PENGATURAN ---
if(isset($_POST['simpan_pengaturan'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Akses Ilegal!");

    $nama = trim($_POST['nama_sekolah']);
    $timezone = $_POST['timezone'];
    $libur_p = $_POST['libur_pekanan'];
    $mode_p = isset($_POST['mode_absen_pulang']) ? 1 : 0;
    $s1_m = $_POST['s1_masuk']; $s1_p = $_POST['s1_pulang'];
    $s2_m = $_POST['s2_masuk']; $s2_p = $_POST['s2_pulang'];
    $wajib_p = isset($_POST['wajib_pulang']) ? 1 : 0;
    $wa_mode = (int)$_POST['wa_mode'];
    $wa_token = trim($_POST['wa_token']);
    $wa_url = trim($_POST['wa_api_url']);
    $tg_token = trim($_POST['tg_bot_token']); 
    $smtp_host = trim($_POST['smtp_host']);
    $smtp_port = (int)$_POST['smtp_port'];
    $smtp_user = trim($_POST['smtp_user']);
    $smtp_pass = trim($_POST['smtp_pass']);
    $p_masuk = $_POST['pesan_masuk'];
    $p_pulang = $_POST['pesan_pulang'];

    // --- SECURITY UPLOAD LOGO ---
    $logo_final = $data['logo_sekolah'];
    if(!empty($_FILES['logo']['name'])){
        $allowed = ['image/jpeg', 'image/png', 'image/x-icon', 'image/jpg'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
        
        if(in_array($mime, $allowed) && $_FILES['logo']['size'] < 2097152){ // Maks 2MB
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $nama_logo = "logo_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo']['tmp_name'], "img/" . $nama_logo)){
                $logo_final = $nama_logo;
            }
        } else {
            echo "<script>alert('File tidak valid atau terlalu besar (Maks 2MB)');</script>";
        }
    }

    $sql = "UPDATE pengaturan SET 
              nama_sekolah=?, timezone=?, libur_pekanan=?, mode_absen_pulang=?,
              s1_masuk=?, s1_pulang=?, s2_masuk=?, s2_pulang=?, wajib_pulang=?,
              wa_mode=?, wa_token=?, wa_api_url=?, tg_bot_token=?, 
              smtp_host=?, smtp_port=?, smtp_user=?, smtp_pass=?,
              pesan_masuk=?, pesan_pulang=?, logo_sekolah=? 
              WHERE id=1";
    
    $stmt_upd = $conn->prepare($sql);
    
    // PERBAIKAN FINAL: Susunan huruf sudah disesuaikan sempurna dengan 20 variabel di bawahnya
    $stmt_upd->bind_param("sssissssiissssisssss", 
        $nama, $timezone, $libur_p, $mode_p, $s1_m, $s1_p, $s2_m, $s2_p, $wajib_p,
        $wa_mode, $wa_token, $wa_url, $tg_token, $smtp_host, $smtp_port, $smtp_user, $smtp_pass,
        $p_masuk, $p_pulang, $logo_final
    );
    
    if($stmt_upd->execute()){
        echo "<script>alert('Semua Pengaturan Berhasil Disimpan!'); window.location='pengaturan.php';</script>";
    }
}

// --- 7. LOGIKA HAPUS DATA MASSAL ---
if(isset($_POST['hapus_data_massal'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Akses Ilegal!");

    if(!empty($_POST['pilihan_hapus'])){
        foreach($_POST['pilihan_hapus'] as $item){

            // Kosongkan riwayat absensi siswa DAN guru sekaligus
            if($item == 'absensi') {
                $conn->query("DELETE FROM absensi");
                $conn->query("DELETE FROM absensi_guru");
            }

            // Kosongkan antrean WA
            if($item == 'wa') $conn->query("DELETE FROM wa_queue");

            // Hapus data siswa + absensinya + foto
            if($item == 'siswa') {
                $conn->query("DELETE FROM absensi");
                // Hapus file foto siswa dari server
                $res_foto = mysqli_query($conn, "SELECT foto FROM siswa WHERE foto IS NOT NULL AND foto != ''");
                while($f = mysqli_fetch_assoc($res_foto)){
                    $path = "img/siswa/" . $f['foto'];
                    if(file_exists($path)) unlink($path);
                }
                $conn->query("DELETE FROM siswa");
            }

            // Hapus data guru + absensi guru + foto guru
            if($item == 'guru') {
                $conn->query("DELETE FROM absensi_guru");
                // Hapus file foto guru dari server
                $res_foto_g = mysqli_query($conn, "SELECT foto FROM guru WHERE foto IS NOT NULL AND foto != ''");
                while($fg = mysqli_fetch_assoc($res_foto_g)){
                    $path_g = "img/guru/" . $fg['foto'];
                    if(file_exists($path_g)) unlink($path_g);
                }
                $conn->query("DELETE FROM guru");
            }

            // Hapus data kelas
            if($item == 'kelas') $conn->query("DELETE FROM kelas");
        }
        echo "<script>alert('Data yang dipilih telah dibersihkan!'); window.location='pengaturan.php';</script>";
    }
}

include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; color: #334155; }
        .card { border: none; border-radius: 1.25rem; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; color: #475569; font-size: 0.85rem; }
        .form-control, .form-select { padding: 0.7rem 1rem; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.9rem; }
        .section-title { font-size: 1rem; font-weight: 700; color: #0d6efd; margin-bottom: 20px; display: flex; align-items: center; }
        .section-title i { margin-right: 10px; }
        .btn-save { padding: 12px; border-radius: 15px; font-weight: 700; transition: 0.3s; }
        .logo-preview { width: 80px; height: 80px; object-fit: contain; border-radius: 12px; background: white; border: 2px dashed #e2e8f0; padding: 5px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <!-- Hidden inputs untuk field yang tidak tampil di form (agar nilai lama tetap tersimpan) -->
                <input type="hidden" name="mode_absen_pulang" value="<?= (int)$data['mode_absen_pulang'] ?>">
                <input type="hidden" name="wa_mode" value="<?= (int)$data['wa_mode'] ?>">
                <input type="hidden" name="wa_token" value="<?= xss($data['wa_token']) ?>">
                <input type="hidden" name="wa_api_url" value="<?= xss($data['wa_api_url']) ?>">
                <input type="hidden" name="tg_bot_token" value="<?= xss($data['tg_bot_token']) ?>">
                <input type="hidden" name="smtp_host" value="<?= xss($data['smtp_host']) ?>">
                <input type="hidden" name="smtp_port" value="<?= (int)$data['smtp_port'] ?>">
                <input type="hidden" name="smtp_user" value="<?= xss($data['smtp_user']) ?>">
                <input type="hidden" name="smtp_pass" value="<?= xss($data['smtp_pass']) ?>">
                <input type="hidden" name="pesan_masuk" value="<?= xss($data['pesan_masuk']) ?>">
                <input type="hidden" name="pesan_pulang" value="<?= xss($data['pesan_pulang']) ?>">
                <div class="card p-4 mb-4">
                    <span class="section-title"><i class="bi bi-building"></i> Identitas & Konfigurasi Umum</span>
                    <div class="row align-items-center mb-4">
                        <div class="col-auto">
                            <img src="img/<?= xss($data['logo_sekolah']) ?>" class="logo-preview">
                        </div>
                        <div class="col">
                            <label class="form-label">Ganti Logo Sekolah</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Sekolah / Instansi</label>
                        <input type="text" name="nama_sekolah" class="form-control" value="<?= xss($data['nama_sekolah']) ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Zona Waktu (Timezone)</label>
                            <select name="timezone" class="form-select">
                                <option value="Asia/Jakarta" <?= $data['timezone'] == 'Asia/Jakarta' ? 'selected' : '' ?>>WIB (Jakarta)</option>
                                <option value="Asia/Makassar" <?= $data['timezone'] == 'Asia/Makassar' ? 'selected' : '' ?>>WITA (Bali)</option>
                                <option value="Asia/Jayapura" <?= $data['timezone'] == 'Asia/Jayapura' ? 'selected' : '' ?>>WIT (Papua)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Libur Rutin Pekanan</label>
                            <select name="libur_pekanan" class="form-select">
                                <option value="minggu" <?= $data['libur_pekanan'] == 'minggu' ? 'selected' : '' ?>>Hanya Minggu</option>
                                <option value="sabtu_minggu" <?= $data['libur_pekanan'] == 'sabtu_minggu' ? 'selected' : '' ?>>Sabtu & Minggu</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-3 bg-light rounded-4 border">
                        <span class="fw-bold d-block mb-3 text-dark"><i class="bi bi-clock-history me-2"></i>Pengaturan Sesi & Shift</span>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="small fw-bold text-primary">Sesi 1: Masuk</label>
                                <input type="time" name="s1_masuk" class="form-control form-control-sm" value="<?= xss($data['s1_masuk']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-primary">Sesi 1: Balik</label>
                                <input type="time" name="s1_pulang" class="form-control form-control-sm" value="<?= xss($data['s1_pulang']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-info">Sesi 2: Masuk</label>
                                <input type="time" name="s2_masuk" class="form-control form-control-sm" value="<?= xss($data['s2_masuk']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-info">Sesi 2: Balik</label>
                                <input type="time" name="s2_pulang" class="form-control form-control-sm" value="<?= xss($data['s2_pulang']) ?>">
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold d-block small">Wajib Absen Balik?</span>
                                <small class="text-muted">Jika Aktif, siswa tidak absen balik = <b>BOLOS</b>.</small>
                            </div>
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" name="wajib_pulang" <?= $data['wajib_pulang'] == 1 ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="mt-4 p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-bold d-block small">Gunakan Absen Pulang Umum?</span>
                            <small class="text-muted">Gunakan saklar ini jika hanya ada 1 sesi umum.</small>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input" type="checkbox" name="mode_absen_pulang" <?= $data['mode_absen_pulang'] == 1 ? 'checked' : '' ?>>
                        </div>
                    </div> -->
                </div>

                <div class="card p-4 mb-4">
                    <!-- <span class="section-title text-success"><i class="bi bi-whatsapp"></i> Konfigurasi WhatsApp API</span>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Mode Pengiriman</label>
                            <select name="wa_mode" class="form-select">
                                <option value="0" <?= $data['wa_mode'] == 0 ? 'selected' : '' ?>>Real-time (Langsung)</option>
                                <option value="1" <?= $data['wa_mode'] == 1 ? 'selected' : '' ?>>Antrean (Jeda Random)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fonnte API Token</label>
                            <input type="password" name="wa_token" class="form-control" value="<?= xss($data['wa_token']) ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">API Endpoint URL</label>
                        <input type="text" name="wa_api_url" class="form-control" value="<?= xss($data['wa_api_url']) ?>">
                    </div>

                    <span class="section-title text-info mt-2"><i class="bi bi-telegram"></i> Konfigurasi Telegram API</span>
                    <div class="mb-4">
                        <label class="form-label">Telegram Bot Token</label>
                        <input type="password" name="tg_bot_token" class="form-control" value="<?= xss($data['tg_bot_token']) ?>" placeholder="Masukkan Token dari @BotFather">
                    </div>

                    <span class="section-title text-warning"><i class="bi bi-envelope-at"></i> Konfigurasi Email (SMTP)</span>
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?= xss($data['smtp_host']) ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?= xss($data['smtp_port']) ?>" placeholder="587">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMTP User (Email)</label>
                            <input type="email" name="smtp_user" class="form-control" value="<?= xss($data['smtp_user']) ?>" placeholder="email@gmail.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMTP Pass (App Password)</label>
                            <input type="password" name="smtp_pass" class="form-control" value="<?= xss($data['smtp_pass']) ?>" placeholder="Sandi Aplikasi 16 Digit">
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label text-primary">Template Pesan MASUK</label>
                        <textarea name="pesan_masuk" class="form-control" rows="3"><?= xss($data['pesan_masuk']) ?></textarea>
                        <small class="text-muted">Variabel: <b>[nama], [jam], [telat]</b></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-primary">Template Pesan PULANG</label>
                        <textarea name="pesan_pulang" class="form-control" rows="3"><?= xss($data['pesan_pulang']) ?></textarea>
                    </div> -->

                    <button type="submit" name="simpan_pengaturan" class="btn btn-primary w-100 btn-save mt-3 shadow-sm">
                        SIMPAN SEMUA PERUBAHAN
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <!-- <div class="card p-4 mb-4 border-start border-success border-4 shadow-sm">
                <span class="section-title text-success small mb-2"><i class="bi bi-send-check"></i> Uji Coba Kirim WA</span>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="wa_token" value="<?= xss($data['wa_token']) ?>">
                    <input type="hidden" name="wa_api_url" value="<?= xss($data['wa_api_url']) ?>">
                    <div class="input-group">
                        <input type="text" name="test_nomor" class="form-control" placeholder="628..." required>
                        <button type="submit" name="test_wa" class="btn btn-success px-3">Tes WA</button>
                    </div>
                </form>
            </div> -->

            <!-- <div class="card p-4 mb-4 border-start border-info border-4 shadow-sm">
                <span class="section-title text-info small mb-2"><i class="bi bi-telegram"></i> Uji Coba Kirim Telegram</span>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="input-group">
                        <input type="text" name="test_chat_id" class="form-control" placeholder="Chat ID Telegram" required>
                        <button type="submit" name="test_tg" class="btn btn-info text-white px-3">Tes TG</button>
                    </div>
                    <small class="text-muted mt-1" style="font-size: 0.7rem;">Gunakan ID Anda (Cek via @userinfobot)</small>
                </form>
            </div> -->

            <!-- <div class="card p-4 mb-4 border-start border-warning border-4 shadow-sm">
                <span class="section-title text-warning small mb-2"><i class="bi bi-envelope-check"></i> Uji Coba Kirim Email</span>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="input-group">
                        <input type="email" name="test_email_addr" class="form-control" placeholder="Email Tujuan" required>
                        <button type="submit" name="test_email" class="btn btn-warning px-3">Tes Email</button>
                    </div>
                </form>
            </div> -->

            <div class="card p-4 mb-4">
                <span class="section-title text-danger"><i class="bi bi-calendar-x"></i> Libur Manual (Tanggal Merah)</span>
                <form method="POST" class="row g-2 mb-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="col-7">
                        <input type="date" name="tgl_libur" class="form-control" required>
                    </div>
                    <div class="col-5">
                        <button type="submit" name="tambah_libur" class="btn btn-danger w-100 fw-bold">Tambah</button>
                    </div>
                    <div class="col-12 mt-2">
                        <input type="text" name="ket_libur" class="form-control" placeholder="Keterangan Libur" required>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" style="font-size: 0.8rem;">
                        <thead class="table-light">
                            <tr><th>Tanggal</th><th>Ket.</th><th class="text-end">Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $q_lib = mysqli_query($conn, "SELECT * FROM libur_manual ORDER BY tanggal DESC");
                            while($l = mysqli_fetch_assoc($q_lib)): ?>
                            <tr>
                                <td><?= date('d/m/y', strtotime($l['tanggal'])) ?></td>
                                <td class="text-muted"><?= xss($l['keterangan']) ?></td>
                                <td class="text-end"><a href="?hapus_libur=<?= $l['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>" class="text-danger"><i class="bi bi-trash"></i></a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card p-4 border-start border-danger border-4">
                <span class="section-title text-danger"><i class="bi bi-exclamation-triangle"></i> Reset / Pembersihan Data</span>
                <p class="text-muted small mb-4">Pilih data yang ingin dihapus permanen.</p>
                <form method="POST" onsubmit="return confirm('PERHATIAN! Data akan dihapus selamanya. Apakah Anda sangat yakin?')">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="pilihan_hapus[]" value="absensi" id="h1">
                        <label class="form-check-label small fw-bold" for="h1">Kosongkan Riwayat Absensi <span class="text-muted fw-normal">(Siswa &amp; Guru)</span></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="pilihan_hapus[]" value="wa" id="h2">
                        <label class="form-check-label small fw-bold" for="h2">Kosongkan Riwayat WA</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="pilihan_hapus[]" value="siswa" id="h3">
                        <label class="form-check-label small fw-bold text-danger" for="h3">Hapus Data Siswa &amp; Foto</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="pilihan_hapus[]" value="guru" id="h4">
                        <label class="form-check-label small fw-bold text-danger" for="h4">Hapus Data Guru &amp; Foto</label>
                    </div>
                    <button type="submit" name="hapus_data_massal" class="btn btn-danger btn-sm w-100 rounded-pill py-2 fw-bold mt-3">
                        EKSEKUSI PENGHAPUSAN
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>