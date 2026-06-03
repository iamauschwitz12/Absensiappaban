<?php
session_start();
include 'koneksi.php';

// 1. PROTEKSI KEAMANAN
if(!isset($_SESSION['login']) || $_SESSION['role'] != 'admin'){
    header("location: dashboard.php");
    exit;
}

// 2. LOGIKA PROSES UPDATE MASSAL
if(isset($_POST['proses_bulk'])){
    $kelas_target = mysqli_real_escape_string($conn, $_POST['kelas']);
    $sesi_baru = mysqli_real_escape_string($conn, $_POST['sesi_baru']);

    if(!empty($kelas_target) && !empty($sesi_baru)){
        $query = mysqli_query($conn, "UPDATE siswa SET sesi = '$sesi_baru' WHERE kelas = '$kelas_target'");
        
        if($query){
            $jumlah = mysqli_affected_rows($conn);
            echo "<script>alert('Sukses! $jumlah siswa di kelas $kelas_target berhasil dipindah ke Sesi $sesi_baru.'); window.location='data_siswa.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui data!');</script>";
        }
    }
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Update Sesi Massal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        .bulk-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .info-box { background: #fff4e5; border-left: 5px solid #ff9800; border-radius: 10px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="d-flex align-items-center mb-4">
                <a href="data_siswa.php" class="btn btn-white shadow-sm rounded-circle me-3"><i class="bi bi-arrow-left"></i></a>
                <h4 class="fw-bold m-0">Pindah Sesi Per Kelas</h4>
            </div>

            <div class="card bulk-card p-4 p-md-5">
                <div class="info-box p-3 mb-4">
                    <p class="small m-0 text-dark">
                        <i class="bi bi-info-circle-fill me-2 text-warning"></i>
                        Fitur ini akan merubah <strong>SEMUA SISWA</strong> dalam satu kelas ke sesi yang dipilih secara sekaligus. Cocok digunakan saat pergantian tahun ajaran atau perubahan jadwal shift.
                    </p>
                </div>

                <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin merubah sesi untuk SELURUH SISWA di kelas ini?')">
                    <div class="mb-4">
                        <label class="form-label fw-bold small">1. PILIH KELAS</label>
                        <select name="kelas" class="form-select form-select-lg" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php 
                            $q_k = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas ASC");
                            while($k = mysqli_fetch_assoc($q_k)) {
                                echo "<option value='".$k['nama_kelas']."'>".$k['nama_kelas']."</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small">2. PINDAHKAN KE SESI</label>
                        <select name="sesi_baru" class="form-select form-select-lg" required>
                            <option value="">-- Pilih Sesi Baru --</option>
                            <option value="1">Sesi 1 (Pagi)</option>
                            <option value="2">Sesi 2 (Siang)</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2 mt-5">
                        <button type="submit" name="proses_bulk" class="btn btn-primary py-3 rounded-pill fw-bold shadow">
                            <i class="bi bi- lightning-charge-fill me-2"></i>EKSEKUSI PERUBAHAN MASSAL
                        </button>
                        <a href="data_siswa.php" class="btn btn-light py-2 rounded-pill">Batal</a>
                    </div>
                </form>
            </div>
            
            <p class="text-center text-muted mt-4 small">Sistem Absensi &copy; <?= date('Y') ?></p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>