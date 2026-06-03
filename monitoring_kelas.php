<?php
session_start();
include 'koneksi.php';

// --- 1. SECURITY ENGINE ---
if(!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin'){
    header("location: login.php");
    exit;
}

function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// --- 2. LOGIKA FILTER ---
$mode = $_GET['mode'] ?? 'harian';
$tanggal_pilih = $_GET['tanggal'] ?? date('Y-m-d');
$bulan_pilih = $_GET['bulan'] ?? date('m');
$tahun_pilih = $_GET['tahun'] ?? date('Y');

// --- 3. HITUNG HARI AKTIF (Hanya untuk Mode Bulanan) ---
// Mencari berapa hari ada aktivitas absen di bulan tersebut agar perhitungan Alpha akurat
$hari_aktif = 1; 
if($mode == 'bulanan') {
    $q_hari = mysqli_query($conn, "SELECT COUNT(DISTINCT DATE(waktu_masuk)) as total_hari FROM absensi 
                                   WHERE MONTH(waktu_masuk) = '$bulan_pilih' 
                                   AND YEAR(waktu_masuk) = '$tahun_pilih'");
    $data_hari = mysqli_fetch_assoc($q_hari);
    $hari_aktif = ($data_hari['total_hari'] > 0) ? $data_hari['total_hari'] : 1;
}

// Query Dasar: Ambil semua daftar kelas
$query_kelas = mysqli_query($conn, "SELECT nama_kelas FROM kelas ORDER BY nama_kelas ASC");

include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Kelas - Asofa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f0f3f9; min-height: 100vh; }
        .glass-card { background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(15px); border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.4); box-shadow: 0 8px 32px rgba(31, 38, 135, 0.05); }
        .table thead th { background: rgba(13, 110, 253, 0.05); color: #0d6efd; border: none; padding: 15px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
        .table tbody td { padding: 15px; vertical-align: middle; border-bottom: 1px solid rgba(0,0,0,0.03); font-size: 0.9rem; }
        .badge-percent { padding: 6px 12px; border-radius: 10px; font-weight: 800; font-size: 0.85rem; }
        .filter-box { background: white; border-radius: 15px; padding: 20px; margin-bottom: 25px; border: 1px solid #edf2f7; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="filter-box shadow-sm">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted mb-2">Mode Laporan</label>
                <select name="mode" class="form-select border-0 bg-light rounded-3" onchange="this.form.submit()">
                    <option value="harian" <?= $mode == 'harian' ? 'selected' : '' ?>>📅 Harian</option>
                    <option value="bulanan" <?= $mode == 'bulanan' ? 'selected' : '' ?>>📊 Bulanan</option>
                </select>
            </div>

            <?php if($mode == 'harian'): ?>
            <div class="col-md-3">
                <label class="small fw-bold text-muted mb-2">Pilih Tanggal</label>
                <input type="date" name="tanggal" class="form-select border-0 bg-light rounded-3" value="<?= $tanggal_pilih ?>" onchange="this.form.submit()">
            </div>
            <?php else: ?>
            <div class="col-md-3">
                <label class="small fw-bold text-muted mb-2">Pilih Bulan</label>
                <select name="bulan" class="form-select border-0 bg-light rounded-3">
                    <?php
                    $bulan_arr = [
                        '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
                        '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
                    ];
                    foreach($bulan_arr as $k => $v){
                        $sel = ($k == $bulan_pilih) ? 'selected' : '';
                        echo "<option value='$k' $sel>$v</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted mb-2">Tahun</label>
                <input type="number" name="tahun" class="form-control border-0 bg-light rounded-3" value="<?= $tahun_pilih ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold">Cari</button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="glass-card overflow-hidden">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Nama Kelas</th>
                        <th class="text-center">Total Siswa</th>
                        <th class="text-center">Hadir</th>
                        <th class="text-center">Telat</th>
                        <th class="text-center">Alpha/Tidak Absen</th>
                        <th class="text-center">Persentase (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    while($k = mysqli_fetch_assoc($query_kelas)): 
                        $nama_kelas = $k['nama_kelas'];

                        // 1. Hitung Total Siswa di kelas ini
                        $s_total = mysqli_query($conn, "SELECT COUNT(*) as t FROM siswa WHERE kelas = '$nama_kelas'");
                        $total_siswa = mysqli_fetch_assoc($s_total)['t'];

                        // 2. Logika Hitung (Harian / Bulanan)
                        if($mode == 'harian'){
                            $sql_hadir = "SELECT COUNT(*) as t FROM absensi a JOIN siswa s ON a.nis = s.nis WHERE s.kelas = '$nama_kelas' AND DATE(a.waktu_masuk) = '$tanggal_pilih'";
                            $sql_telat = "SELECT COUNT(*) as t FROM absensi a JOIN siswa s ON a.nis = s.nis WHERE s.kelas = '$nama_kelas' AND DATE(a.waktu_masuk) = '$tanggal_pilih' AND a.status_kehadiran = 'Terlambat'";
                            
                            $res_hadir = mysqli_fetch_assoc(mysqli_query($conn, $sql_hadir))['t'];
                            $res_telat = mysqli_fetch_assoc(mysqli_query($conn, $sql_telat))['t'];
                            
                            $res_alpha = ($total_siswa > $res_hadir) ? ($total_siswa - $res_hadir) : 0;
                            $persen = ($total_siswa > 0) ? round(($res_hadir / $total_siswa) * 100, 1) : 0;

                        } else {
                            // MODE BULANAN
                            $sql_hadir = "SELECT COUNT(*) as t FROM absensi a JOIN siswa s ON a.nis = s.nis WHERE s.kelas = '$nama_kelas' AND MONTH(a.waktu_masuk) = '$bulan_pilih' AND YEAR(a.waktu_masuk) = '$tahun_pilih'";
                            $sql_telat = "SELECT COUNT(*) as t FROM absensi a JOIN siswa s ON a.nis = s.nis WHERE s.kelas = '$nama_kelas' AND MONTH(a.waktu_masuk) = '$bulan_pilih' AND YEAR(a.waktu_masuk) = '$tahun_pilih' AND a.status_kehadiran = 'Terlambat'";
                            
                            $res_hadir = mysqli_fetch_assoc(mysqli_query($conn, $sql_hadir))['t'];
                            $res_telat = mysqli_fetch_assoc(mysqli_query($conn, $sql_telat))['t'];

                            // Alpha Bulanan = (Total Siswa x Hari Sekolah) - Total Hadir
                            $total_potensi_hadir = $total_siswa * $hari_aktif;
                            $res_alpha = ($total_potensi_hadir > $res_hadir) ? ($total_potensi_hadir - $res_hadir) : 0;
                            
                            // Persentase Bulanan
                            $persen = ($total_potensi_hadir > 0) ? round(($res_hadir / $total_potensi_hadir) * 100, 1) : 0;
                        }
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold text-dark"><?= xss($nama_kelas) ?></td>
                        <td class="text-center"><?= $total_siswa ?></td>
                        <td class="text-center text-success fw-bold"><?= $res_hadir ?></td>
                        <td class="text-center text-warning fw-bold"><?= $res_telat ?></td>
                        <td class="text-center text-danger fw-bold"><?= $res_alpha ?></td>
                        <td class="text-center">
                            <span class="badge-percent <?= ($persen >= 80) ? 'bg-success text-white' : (($persen >= 50) ? 'bg-warning text-dark' : 'bg-danger text-white') ?>">
                                <?= $persen ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php
$modal_dev = "PGRpdiBzdHlsZT0idGV4dC1hbGlnbjogY2VudGVyOyBwYWRkaW5nOiAyMHB4OyBjb2xvcjogIzhhOGE4YTsgZm9udC1zaXplOiAwLjhremVtOyI+JmNvcHk7IDIwMjYgfCBEZXZlbG9wZXIgYnkgQXNvZmE8L2Rpdj4=";
echo base64_decode($modal_dev);
?>

</body>
</html>