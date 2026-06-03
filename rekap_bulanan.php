<?php
session_start();
include 'koneksi.php';

// --- SECURITY ENGINE: XSS PROTECTION & CSRF HELPER ---
function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

if(!isset($_SESSION['login'])){ header("location: login.php"); exit; }

$role = $_SESSION['role'];
$nama_user = $_SESSION['nama'] ?? 'User';
$kelas_diampu = $_SESSION['kelas_diampu'] ?? '';

// --- 1. AMBIL PENGATURAN ---
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id=1");
$stmt_set->execute();
$res_set = $stmt_set->get_result()->fetch_assoc();
$nama_sekolah = $res_set['nama_sekolah'] ?? "SISTEM ABSENSI";
$libur_pekanan = $res_set['libur_pekanan'] ?? "minggu";

// --- 2. AMBIL DAFTAR LIBUR MANUAL ---
$libur_manual = [];
$q_libur_man = mysqli_query($conn, "SELECT tanggal FROM libur_manual");
while($lm = mysqli_fetch_assoc($q_libur_man)){
    $libur_manual[] = $lm['tanggal'];
}

// --- 3. FILTER & SANITIZATION ---
$bulan_pilih = isset($_GET['bulan']) ? sprintf("%02d", (int)$_GET['bulan']) : date('m');
$tahun_pilih = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
$kelas_pilih = ($role == 'walikelas') ? $kelas_diampu : (isset($_GET['kelas']) ? $_GET['kelas'] : '');

$jumlah_hari = (int)date('t', mktime(0, 0, 0, (int)$bulan_pilih, 1, (int)$tahun_pilih));
$nama_bulan_indo = [
    '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni',
    '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'
];

// --- 4. AMBIL DATA SISWA & ABSENSI ---
$siswa = [];
$data_absen = [];

if(!empty($kelas_pilih)){
    $stmt_siswa = $conn->prepare("SELECT nis, nama FROM siswa WHERE kelas=? ORDER BY nama ASC");
    $stmt_siswa->bind_param("s", $kelas_pilih);
    $stmt_siswa->execute();
    $res_siswa = $stmt_siswa->get_result();
    while($s = $res_siswa->fetch_assoc()){ $siswa[] = $s; }

    $sql_absen = "SELECT nis, DAY(waktu_masuk) as tgl, keterangan FROM absensi 
                  WHERE MONTH(waktu_masuk) = ? 
                  AND YEAR(waktu_masuk) = ? 
                  AND nis IN (SELECT nis FROM siswa WHERE kelas=?)";
    $stmt_absen = $conn->prepare($sql_absen);
    $stmt_absen->bind_param("sss", $bulan_pilih, $tahun_pilih, $kelas_pilih);
    $stmt_absen->execute();
    $res_absen = $stmt_absen->get_result();
    while($row = $res_absen->fetch_assoc()){
        // Ambil inisial depan (H untuk Hadir, B untuk Bolos, S untuk Sakit, I untuk Izin)
        $data_absen[$row['nis']][(int)$row['tgl']] = strtoupper(substr($row['keterangan'], 0, 1));
    }
}

include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Bulanan - <?= xss($kelas_pilih) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; color: #333; }
        .nama-col { position: sticky; left: 0; background: white !important; z-index: 2; min-width: 160px; border-right: 2px solid #dee2e6 !important; }
        .h { background-color: #d1e7dd !important; color: #0f5132 !important; }
        .s { background-color: #fff3cd !important; color: #664d03 !important; }
        .i { background-color: #cff4fc !important; color: #055160 !important; }
        .a { background-color: #f8d7da !important; color: #842029 !important; }
        .l { background-color: #e9ecef !important; color: #6c757d !important; font-style: italic; }
        @media print {
            @page { size: landscape; margin: 0.3cm; }
            nav, header, .navbar, .no-print, .btn { display: none !important; }
            body { background: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding-top: 0 !important; }
            table { width: 100% !important; font-size: 7pt !important; table-layout: fixed; border-collapse: collapse !important; }
            th, td { border: 1px solid #000 !important; padding: 2px 1px !important; }
            .nama-col { position: static !important; width: 120px !important; font-size: 7.5pt !important; border-right: 1px solid #000 !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 10px; border-bottom: 2px solid #000; }
        }
        .print-header { display: none; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="print-header">
        <h5 class="mb-0"><?= strtoupper(xss($nama_sekolah)) ?></h5>
        <h5 class="mb-1">REKAPITULASI ABSENSI SISWA</h5>
        <p>Bulan: <?= xss($nama_bulan_indo[$bulan_pilih]) ?> <?= xss($tahun_pilih) ?> | Kelas: <?= xss($kelas_pilih) ?></p>
    </div>

    <div class="card shadow-sm border-0 p-4 mb-4 no-print">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-primary m-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Rekap Bulanan</h4>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-4 me-2">Kembali</a>
                <button onclick="window.print()" class="btn btn-danger rounded-pill px-4 shadow-sm fw-bold">
                    <i class="bi bi-printer me-2"></i>Cetak PDF
                </button>
            </div>
        </div>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="small fw-bold">Bulan</label>
                <select name="bulan" class="form-select border-0 bg-light">
                    <?php foreach($nama_bulan_indo as $m => $nm): ?>
                    <option value="<?= $m ?>" <?= $bulan_pilih == $m ? 'selected' : '' ?>><?= $nm ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Tahun</label>
                <select name="tahun" class="form-select border-0 bg-light">
                    <?php for($y=date('Y'); $y>=2024; $y--): ?>
                    <option value="<?= $y ?>" <?= $tahun_pilih == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold">Kelas</label>
                <?php if($role == 'admin'): ?>
                <select name="kelas" class="form-select border-0 bg-light" required>
                    <option value="">-- Pilih Kelas --</option>
                    <?php 
                    $q_kls = mysqli_query($conn, "SELECT nama_kelas FROM kelas ORDER BY nama_kelas ASC");
                    while($k = mysqli_fetch_assoc($q_kls)): ?>
                    <option value="<?= xss($k['nama_kelas']) ?>" <?= $kelas_pilih == $k['nama_kelas'] ? 'selected' : '' ?>><?= xss($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
                <?php else: ?>
                <input type="text" class="form-control bg-light border-0" value="<?= xss($kelas_diampu) ?>" readonly>
                <input type="hidden" name="kelas" value="<?= xss($kelas_diampu) ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Tampilkan</button>
            </div>
        </form>
    </div>

    <?php if(!empty($siswa)): ?>
    <div class="card shadow-sm border-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-bordered text-center align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th rowspan="2" width="40">No</th>
                        <th rowspan="2" class="nama-col" style="color: black;">Nama Siswa</th>
                        <th colspan="<?= $jumlah_hari ?>">Tanggal (<?= xss($nama_bulan_indo[$bulan_pilih]) ?>)</th>
                        <th colspan="4">Total</th>
                    </tr>
                    <tr>
                        <?php for($d=1; $d<=$jumlah_hari; $d++): 
                            $dt = "$tahun_pilih-$bulan_pilih-" . sprintf("%02d", $d);
                            $day_n = date('N', strtotime($dt));
                            $is_red = ($day_n == 7 || ($libur_pekanan == 'sabtu_minggu' && $day_n == 6) || in_array($dt, $libur_manual));
                        ?>
                        <th style="font-size: 9px;" class="<?= $is_red ? 'text-danger' : '' ?>"><?= $d ?></th>
                        <?php endfor; ?>
                        <th class="h">H</th><th class="s">S</th><th class="i">I</th><th class="a">A</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no=1; foreach($siswa as $s): 
                        $nis = $s['nis'];
                        $th=0; $ts=0; $ti=0; $ta=0;
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="nama-col fw-bold text-start small"><?= xss($s['nama']) ?></td>
                        <?php for($d=1; $d<=$jumlah_hari; $d++): 
                            $dt = "$tahun_pilih-$bulan_pilih-" . sprintf("%02d", $d);
                            $day_n = date('N', strtotime($dt));
                            
                            $is_libur = ($day_n == 7 || ($libur_pekanan == 'sabtu_minggu' && $day_n == 6) || in_array($dt, $libur_manual));
                            
                            $st = $data_absen[$nis][$d] ?? '';
                            $cls = ""; $char = ".";

                            // MODIFIKASI DISINI: Jika status 'H' (Hadir) atau 'B' (Bolos), tampilkan 'H'
                            if($st == 'H' || $st == 'B'){ 
                                $char='H'; $cls='h'; $th++; 
                            }
                            elseif($st == 'S'){ $char='S'; $cls='s'; $ts++; }
                            elseif($st == 'I'){ $char='I'; $cls='i'; $ti++; }
                            else {
                                if($is_libur){
                                    $char = 'L'; $cls = 'l';
                                } elseif($dt <= date('Y-m-d')){
                                    $char = 'A'; $cls = 'a'; $ta++;
                                }
                            }
                        ?>
                        <td class="<?= $cls ?> small" style="font-size: 10px;"><?= $char ?></td>
                        <?php endfor; ?>
                        <td class="bg-light fw-bold"><?= $th ?></td>
                        <td class="bg-light fw-bold"><?= $ts ?></td>
                        <td class="bg-light fw-bold"><?= $ti ?></td>
                        <td class="bg-light fw-bold text-danger"><?= $ta ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>