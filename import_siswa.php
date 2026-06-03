<?php
session_start();
include 'koneksi.php';

// Pastikan Library SimpleXLSX ada
if (!file_exists('libs/SimpleXLSX.php')) {
    die("Library libs/SimpleXLSX.php tidak ditemukan! Pastikan file tersebut ada.");
}

require_once 'libs/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

if(!isset($_SESSION['login']) || $_SESSION['role'] != 'admin'){
    header("location: dashboard.php");
    exit;
}

$report = [];
if(isset($_POST['upload'])){
    $file = $_FILES['file_siswa'];
    
    if($file['error'] !== UPLOAD_ERR_OK){
        $report[] = "<div class='alert alert-danger'>Gagal upload file. Error code: ".$file['error']."</div>";
    } else {
        if ( $xlsx = SimpleXLSX::parse($file['tmp_name']) ) {
            $rows = $xlsx->rows();
            $sukses = 0; $gagal = 0; $pesan_error = "";

            // Loop mulai baris ke-2 (Index 1)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                // Skip jika NIS (Kolom B / Index 1) kosong
                if(empty($row[1])) continue;

                // Mapping Kolom (A=0, B=1, C=2, D=3, E=4, F=5, G=6, H=7)
                $nis    = mysqli_real_escape_string($conn, $row[1]);
                $nama   = mysqli_real_escape_string($conn, $row[2]);
                $kelas  = mysqli_real_escape_string($conn, $row[3]);
                $hp     = mysqli_real_escape_string($conn, $row[4]);
                $tg_id  = mysqli_real_escape_string($conn, $row[5] ?? '');
                $email  = mysqli_real_escape_string($conn, $row[6] ?? '');
                $sesi   = mysqli_real_escape_string($conn, $row[7] ?? '1'); // Kolom H (Index 7), default Sesi 1
                $rfid   = $nis; // RFID default = NIS

                // Validasi Sesi (Hanya boleh 1 atau 2)
                if(!in_array($sesi, ['1', '2'])){
                    $sesi = '1'; 
                }

                // 1. Cek apakah NIS sudah ada?
                $cek = mysqli_query($conn, "SELECT nis FROM siswa WHERE nis = '$nis'");
                if(mysqli_num_rows($cek) > 0){
                    $gagal++;
                    $pesan_error .= "Baris ".($i+1).": NIS $nis sudah terdaftar.<br>";
                    continue;
                }

                // 2. Cek apakah Kelas ada di database?
                $cek_kelas = mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE nama_kelas = '$kelas'");
                if(mysqli_num_rows($cek_kelas) == 0){
                    $gagal++;
                    $pesan_error .= "Baris ".($i+1).": Kelas $kelas tidak ditemukan di data kelas.<br>";
                    continue;
                }

                // 3. Eksekusi Insert (Menyertakan kolom sesi)
                $q = mysqli_query($conn, "INSERT INTO siswa (nis, rfid_uid, nama, kelas, sesi, no_hp_ortu, telegram_chat_id, email, foto) 
                                          VALUES ('$nis', '$rfid', '$nama', '$kelas', '$sesi', '$hp', '$tg_id', '$email', 'default.jpg')");
                
                if($q) $sukses++;
                else {
                    $gagal++;
                    $pesan_error .= "Baris ".($i+1).": Gagal simpan database. ".mysqli_error($conn)."<br>";
                }
            }
            $report[] = "<div class='alert alert-success'>Berhasil: $sukses | Gagal: $gagal</div>";
            if(!empty($pesan_error)) $report[] = "<div class='alert alert-warning small'>$pesan_error</div>";
        } else {
            $report[] = "<div class='alert alert-danger'>Format .xlsx tidak valid! ".SimpleXLSX::parseError()."</div>";
        }
    }
}
include 'header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Import Siswa .XLSX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f7fe; font-family: 'Inter', sans-serif; }
        .card-import { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card card-import p-5">
                <div class="text-center mb-4">
                    <h4 class="fw-bold">Import Siswa (.xlsx)</h4>
                    <p class="text-muted">Gunakan format Excel terbaru untuk hasil maksimal.</p>
                </div>

                <div class="bg-light p-3 rounded-4 mb-4 border text-center">
                    <p class="small mb-2 fw-bold">Wajib gunakan template ini:</p>
                    <p class="small text-muted mb-3">Tambahkan angka 1 atau 2 pada kolom <b>Sesi (Kolom H)</b></p>
                    <a href="download_template.php" class="btn btn-success btn-sm px-4 rounded-pill">
                        <i class="bi bi-download me-2"></i>Download Template XLSX
                    </a>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Upload File Excel (.xlsx)</label>
                        <input type="file" name="file_siswa" class="form-control rounded-3" accept=".xlsx" required>
                    </div>
                    <button type="submit" name="upload" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow">
                        PROSES IMPORT DATA
                    </button>
                </form>

                <div class="mt-4">
                    <?php foreach($report as $msg) echo $msg; ?>
                </div>

                <div class="text-center mt-3">
                    <a href="data_siswa.php" class="text-muted small text-decoration-none">← Kembali</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>