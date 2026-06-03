<?php
session_start();
include 'koneksi.php';

// --- SECURITY: INISIALISASI CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// 1. Cek Login
if(!isset($_SESSION['login'])){ header("location: login.php"); exit; }

// 2. Ambil ID secara aman
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 3. Ambil Data Guru (Prepared Statement)
$stmt_get = $conn->prepare("SELECT * FROM guru WHERE id = ?");
$stmt_get->bind_param("i", $id);
$stmt_get->execute();
$res_get = $stmt_get->get_result();
$d = $res_get->fetch_assoc();

if(!$d) { header("location: data_guru.php"); exit; }

// --- LOGIKA UPDATE DATA ---
if(isset($_POST['update'])){
    // Validasi CSRF
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Serangan CSRF terdeteksi!");
    }

    $nip     = trim($_POST['nip']);
    $rfid    = trim($_POST['rfid_uid'] ?? '');
    $nama    = trim($_POST['nama']);
    $mapel   = trim($_POST['mata_pelajaran'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $no_hp   = trim($_POST['no_hp'] ?? '');
    $tg_id   = trim($_POST['telegram_id'] ?? '');
    $email   = trim($_POST['email'] ?? '');

    // Cek duplikat NIP (selain diri sendiri)
    $stmt_cek = $conn->prepare("SELECT id FROM guru WHERE nip = ? AND id != ?");
    $stmt_cek->bind_param("si", $nip, $id);
    $stmt_cek->execute();
    if($stmt_cek->get_result()->num_rows > 0) {
        echo "<script>alert('Gagal! NIP sudah digunakan guru lain.'); window.history.back();</script>";
        exit;
    }

    // Cek duplikat RFID (selain diri sendiri, hanya jika diisi)
    if(!empty($rfid)){
        $stmt_rfid = $conn->prepare("SELECT id FROM guru WHERE rfid_uid = ? AND id != ?");
        $stmt_rfid->bind_param("si", $rfid, $id);
        $stmt_rfid->execute();
        if($stmt_rfid->get_result()->num_rows > 0){
            echo "<script>alert('Gagal! Nomor RFID sudah digunakan guru lain.'); window.history.back();</script>";
            exit;
        }
    }

    $foto_final = $d['foto'];
    if(!empty($_FILES['foto']['name'])){
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if(in_array($ext, $allowed)){
            $foto_baru = "guru_" . preg_replace('/[^a-zA-Z0-9_]/', '', $nip) . "_" . time() . ".jpg";
            $target_path = "img/guru/" . $foto_baru;

            // Kompres & resize
            $info = getimagesize($_FILES['foto']['tmp_name']);
            if($info){
                $src_img = null;
                if($info['mime'] == 'image/jpeg') $src_img = imagecreatefromjpeg($_FILES['foto']['tmp_name']);
                elseif($info['mime'] == 'image/png') $src_img = imagecreatefrompng($_FILES['foto']['tmp_name']);
                
                if($src_img){
                    list($w, $h) = $info;
                    $max = 500;
                    if($w > $max || $h > $max){
                        $ratio = $w/$h;
                        $nw = $ratio > 1 ? $max : $max * $ratio;
                        $nh = $ratio > 1 ? $max / $ratio : $max;
                        $dst = imagecreatetruecolor($nw, $nh);
                        imagecopyresampled($dst, $src_img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        imagejpeg($dst, $target_path, 80);
                    } else {
                        imagejpeg($src_img, $target_path, 80);
                    }
                    
                    // Hapus foto lama
                    if(!empty($d['foto']) && file_exists("img/guru/" . $d['foto'])){
                        unlink("img/guru/" . $d['foto']);
                    }
                    $foto_final = $foto_baru;
                }
            }
        }
    }

    $rfid_val = !empty($rfid) ? $rfid : null;

    // Update
    $stmt_upd = $conn->prepare("UPDATE guru SET 
                nip=?, rfid_uid=?, nama=?, mata_pelajaran=?, jabatan=?,
                no_hp=?, telegram_chat_id=?, email=?, foto=? 
                WHERE id=?");
    $stmt_upd->bind_param("sssssssssi", $nip, $rfid_val, $nama, $mapel, $jabatan, $no_hp, $tg_id, $email, $foto_final, $id);

    if($stmt_upd->execute()){
        echo "<script>alert('Data Guru Berhasil Diperbarui!'); window.location='data_guru.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal memperbarui: " . $conn->error . "'); window.history.back();</script>";
        exit;
    }
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Guru - <?= xss($d['nama']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f0f3f9; min-height: 100vh; }
        .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 30px; }
        .form-label { font-weight: 800; font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        .img-preview { width: 140px; height: 140px; object-fit: cover; border-radius: 25px; border: 5px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .btn-guru { background: linear-gradient(135deg, #7c3aed, #a855f7); border: none; color: #fff; }
        .btn-guru:hover { background: linear-gradient(135deg, #6d28d9, #9333ea); color: #fff; }
        .text-guru { color: #7c3aed !important; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex align-items-center mb-4">
                <a href="data_guru.php" class="btn btn-white bg-white shadow-sm rounded-4 px-3 py-2 me-3 text-guru">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
                <h4 class="fw-extrabold m-0" style="font-weight: 800;">Edit Profil <span class="text-guru"><?= xss($d['nama']) ?></span></h4>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="glass-card p-4 p-md-5">
                    <div class="row g-5">
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="img-preview-container mb-4">
                                    <?php 
                                    $path = "img/guru/" . $d['foto'];
                                    if(!empty($d['foto']) && file_exists($path)){
                                        $src = $path;
                                    } else {
                                        $src = "https://ui-avatars.com/api/?name=".urlencode($d['nama'])."&background=7c3aed&color=fff&size=200";
                                    }
                                    ?>
                                    <img src="<?= $src ?>" id="preview-display" class="img-preview">
                                </div>
                                <label class="form-label d-block mb-3">Foto Profil Guru</label>
                                <input type="file" name="foto" id="foto-input" class="form-control form-control-sm" accept="image/*" onchange="previewImage(event)">
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">NIP (ID Guru)</label>
                                    <input type="text" name="nip" class="form-control" value="<?= xss($d['nip']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">RFID / UID Kartu</label>
                                    <input type="text" name="rfid_uid" class="form-control" value="<?= xss($d['rfid_uid']) ?>" placeholder="Tempel kartu...">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Nama Lengkap Guru</label>
                                    <input type="text" name="nama" class="form-control" value="<?= xss($d['nama']) ?>" required>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Mata Pelajaran</label>
                                    <input type="text" name="mata_pelajaran" class="form-control" value="<?= xss($d['mata_pelajaran']) ?>" placeholder="cth: Matematika">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Jabatan</label>
                                    <select name="jabatan" class="form-select">
                                        <option value="">-- Pilih --</option>
                                        <?php
                                        $jabatan_list = ['Guru', 'Wali Kelas', 'Kepala Sekolah', 'Wakil Kepala Sekolah', 'Guru BK', 'Staf TU', 'Lainnya'];
                                        foreach($jabatan_list as $jab){
                                            $sel = ($d['jabatan'] == $jab) ? 'selected' : '';
                                            echo "<option value='".xss($jab)."' $sel>".xss($jab)."</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-5">
                                <div class="col-md-6">
                                    <label class="form-label">No. HP / WhatsApp</label>
                                    <input type="text" name="no_hp" class="form-control" value="<?= xss($d['no_hp']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ID Chat Telegram</label>
                                    <input type="text" name="telegram_id" class="form-control" value="<?= xss($d['telegram_chat_id']) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Email Aktif</label>
                                    <input type="email" name="email" class="form-control" value="<?= xss($d['email']) ?>">
                                </div>
                            </div>

                            <button type="submit" name="update" class="btn btn-guru w-100 py-3 rounded-4 shadow fw-bold">
                                <i class="bi bi-cloud-arrow-up me-2"></i> Perbarui Data Guru
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function previewImage(event) {
        const reader = new FileReader();
        reader.onload = function(){
            const output = document.getElementById('preview-display');
            output.src = reader.result;
        };
        reader.readAsDataURL(event.target.files[0]);
    }
</script>

</body>
</html>
