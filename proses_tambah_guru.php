<?php
session_start();
include 'koneksi.php';

// Proteksi halaman
if(!isset($_SESSION['login'])){ header("location: login.php"); exit; }

// Validasi CSRF
if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
    die("Serangan CSRF terdeteksi!");
}

/**
 * FUNGSI KOMPRESI & RESIZE GAMBAR
 */
function compressAndResize($source, $destination, $quality) {
    $info = getimagesize($source);
    
    if ($info['mime'] == 'image/jpeg') $image = imagecreatefromjpeg($source);
    elseif ($info['mime'] == 'image/gif') $image = imagecreatefromgif($source);
    elseif ($info['mime'] == 'image/png') $image = imagecreatefrompng($source);
    else return false;

    list($width, $height) = $info;
    $max_dim = 500;
    if ($width > $max_dim || $height > $max_dim) {
        $ratio = $width / $height;
        if ($ratio > 1) {
            $new_width = $max_dim;
            $new_height = $max_dim / $ratio;
        } else {
            $new_width = $max_dim * $ratio;
            $new_height = $max_dim;
        }
        $target = imagecreatetruecolor($new_width, $new_height);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        $image = $target;
    }

    return imagejpeg($image, $destination, $quality);
}

if(isset($_POST['tambah'])){
    // Tangkap data
    $nip     = trim($_POST['nip']);
    $rfid    = trim($_POST['rfid_uid'] ?? '');
    $nama    = trim($_POST['nama']);
    $mapel   = trim($_POST['mata_pelajaran'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $no_hp   = trim($_POST['no_hp'] ?? '');
    $tg_id   = trim($_POST['telegram_id'] ?? '');
    $email   = trim($_POST['email'] ?? '');

    // --- 1. PENCEGAT DUPLIKAT NIP ---
    $stmt_cek = $conn->prepare("SELECT id FROM guru WHERE nip = ?");
    $stmt_cek->bind_param("s", $nip);
    $stmt_cek->execute();
    $res_cek = $stmt_cek->get_result();

    if($res_cek->num_rows > 0) {
        echo "<script>alert('Gagal! NIP sudah terdaftar.'); window.history.back();</script>";
        exit;
    }

    // Cek duplikat RFID (hanya jika diisi)
    if(!empty($rfid)){
        $stmt_rfid = $conn->prepare("SELECT id FROM guru WHERE rfid_uid = ?");
        $stmt_rfid->bind_param("s", $rfid);
        $stmt_rfid->execute();
        if($stmt_rfid->get_result()->num_rows > 0){
            echo "<script>alert('Gagal! Nomor RFID sudah terdaftar.'); window.history.back();</script>";
            exit;
        }
    }

    // --- 2. PENANGANAN FOTO ---
    $foto_name = null; 
    if (!empty($_FILES['foto']['name'])) {
        $foto_name = "guru_" . preg_replace('/[^a-zA-Z0-9_]/', '', $nip) . "_" . time() . ".jpg";
        $target_path = "img/guru/" . $foto_name;
        
        $upload = compressAndResize($_FILES['foto']['tmp_name'], $target_path, 80);
        
        if(!$upload) {
            echo "<script>alert('Gagal memproses gambar!'); window.history.back();</script>";
            exit;
        }
    }

    // Nilai RFID null jika kosong (agar UNIQUE tidak konflik)
    $rfid_val = !empty($rfid) ? $rfid : null;

    // --- 3. SIMPAN DATA ---
    $stmt_ins = $conn->prepare("INSERT INTO guru (nip, rfid_uid, nama, mata_pelajaran, jabatan, no_hp, telegram_chat_id, email, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_ins->bind_param("sssssssss", $nip, $rfid_val, $nama, $mapel, $jabatan, $no_hp, $tg_id, $email, $foto_name);

    if($stmt_ins->execute()){
        echo "<script>alert('Data Guru Berhasil Disimpan!'); window.location='data_guru.php';</script>";
    } else {
        echo "<script>alert('Gagal simpan ke database: " . $conn->error . "'); window.history.back();</script>";
    }
}
?>
