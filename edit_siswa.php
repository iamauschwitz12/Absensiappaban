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

// 3. Ambil Data Siswa (Prepared Statement)
$stmt_get = $conn->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt_get->bind_param("i", $id);
$stmt_get->execute();
$res_get = $stmt_get->get_result();
$d = $res_get->fetch_assoc();

if(!$d) { header("location: data_siswa.php"); exit; }

// --- LOGIKA UPDATE DATA ---
if(isset($_POST['update'])){
    // Validasi CSRF
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Serangan CSRF terdeteksi!");
    }

    $nis   = $_POST['nis'];
    $rfid  = $_POST['rfid_uid'];
    $nama  = $_POST['nama'];
    $kelas = $_POST['kelas'];
    $sesi  = $_POST['sesi'];
    $hp    = $_POST['hp'];
    $tg_id = $_POST['telegram_id'];
    $email = $_POST['email'];

    // --- ANTI ERROR: CEK DUPLIKAT RFID/NIS (Kunci Perbaikan) ---
    // Cek apakah RFID atau NIS sudah digunakan oleh orang LAIN (id != current_id)
    $stmt_cek = $conn->prepare("SELECT id FROM siswa WHERE (rfid_uid = ? OR nis = ?) AND id != ?");
    $stmt_cek->bind_param("ssi", $rfid, $nis, $id);
    $stmt_cek->execute();
    $res_cek = $stmt_cek->get_result();

    if($res_cek->num_rows > 0) {
        echo "<script>alert('Gagal! NIS atau Nomor RFID sudah digunakan oleh siswa lain.'); window.history.back();</script>";
        exit;
    }

    $foto_final = $d['foto'];
    if(!empty($_FILES['foto']['name'])){
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if(in_array($ext, $allowed)){
            $foto_baru = $nis . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['foto']['tmp_name'], "img/siswa/" . $foto_baru)){
                // Hapus foto lama jika bukan default
                if(!empty($d['foto']) && file_exists("img/siswa/" . $d['foto'])){
                    unlink("img/siswa/" . $d['foto']);
                }
                $foto_final = $foto_baru;
            }
        }
    }

    // Update (Prepared Statement)
    $stmt_upd = $conn->prepare("UPDATE siswa SET 
                nis=?, rfid_uid=?, nama=?, kelas=?, sesi=?,
                no_hp_ortu=?, telegram_chat_id=?, email=?, foto=? 
                WHERE id=?");
    $stmt_upd->bind_param("sssssssssi", $nis, $rfid, $nama, $kelas, $sesi, $hp, $tg_id, $email, $foto_final, $id);

    if($stmt_upd->execute()){
        echo "<script>alert('Data Berhasil Diperbarui!'); window.location='data_siswa.php';</script>";
        exit;
    }
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Siswa - <?= xss($d['nama']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f0f3f9; min-height: 100vh; }
        .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 30px; }
        .form-label { font-weight: 800; font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        .img-preview { width: 140px; height: 140px; object-fit: cover; border-radius: 25px; border: 5px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex align-items-center mb-4">
                <a href="data_siswa.php" class="btn btn-white bg-white shadow-sm rounded-4 px-3 py-2 me-3 text-primary">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
                <h4 class="fw-extrabold m-0" style="font-weight: 800;">Edit Profil <span class="text-primary"><?= xss($d['nama']) ?></span></h4>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="glass-card p-4 p-md-5">
                    <div class="row g-5">
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="img-preview-container mb-4">
                                    <?php 
                                    // LOGIKA FOTO DEFAULT
                                    $path = "img/siswa/" . $d['foto'];
                                    // Cek apakah file ada, jika tidak pakai icon default
                                    if(!empty($d['foto']) && file_exists($path)){
                                        $src = $path;
                                    } else {
                                        // Pakai placeholder jika foto kosong/hilang
                                        $src = "https://ui-avatars.com/api/?name=".urlencode($d['nama'])."&background=random&size=200";
                                    }
                                    ?>
                                    <img src="<?= $src ?>" id="preview-display" class="img-preview">
                                </div>
                                <label class="form-label d-block mb-3">Foto Profil Siswa</label>
                                <input type="file" name="foto" id="foto-input" class="form-control form-control-sm" accept="image/*" onchange="previewImage(event)">
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">NIS (ID Absensi)</label>
                                    <input type="text" name="nis" class="form-control" value="<?= xss($d['nis']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">RFID / UID Kartu</label>
                                    <input type="text" name="rfid_uid" class="form-control" value="<?= xss($d['rfid_uid']) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Nama Lengkap Siswa</label>
                                    <input type="text" name="nama" class="form-control" value="<?= xss($d['nama']) ?>" required>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Penempatan Kelas</label>
                                    <select name="kelas" class="form-select">
                                        <?php 
                                        $qk = mysqli_query($conn, "SELECT nama_kelas FROM kelas ORDER BY nama_kelas ASC");
                                        while($k = mysqli_fetch_assoc($qk)){
                                            $sel = ($d['kelas'] == $k['nama_kelas']) ? 'selected' : '';
                                            echo "<option value='".xss($k['nama_kelas'])."' $sel>".xss($k['nama_kelas'])."</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Sesi Absensi</label>
                                    <select name="sesi" class="form-select">
                                        <option value="1" <?= ($d['sesi'] == '1') ? 'selected' : '' ?>>Sesi 1 (Pagi)</option>
                                        <option value="2" <?= ($d['sesi'] == '2') ? 'selected' : '' ?>>Sesi 2 (Siang)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-5">
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp Orang Tua</label>
                                    <input type="text" name="hp" class="form-control" value="<?= xss($d['no_hp_ortu']) ?>" required>
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

                            <button type="submit" name="update" class="btn btn-primary w-100 py-3 rounded-4 shadow fw-bold">
                                <i class="bi bi-cloud-arrow-up me-2"></i> Perbarui Data Siswa
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