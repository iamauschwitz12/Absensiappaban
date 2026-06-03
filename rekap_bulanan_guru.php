<?php
session_start();
include 'koneksi.php';

// --- SECURITY ENGINE: XSS PROTECTION ---
function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

if(!isset($_SESSION['login'])){ header("location: login.php"); exit; }

$role = $_SESSION['role'];
$nama_user = $_SESSION['nama'] ?? 'User';

// Hanya admin yang boleh akses
if($role != 'admin'){
    header("location: dashboard.php");
    exit;
}

// --- 1. AMBIL PENGATURAN ---
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id=1");
$stmt_set->execute();
$res_set = $stmt_set->get_result()->fetch_assoc();
$nama_sekolah  = $res_set['nama_sekolah'] ?? "SISTEM ABSENSI";
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

$jumlah_hari = (int)date('t', mktime(0, 0, 0, (int)$bulan_pilih, 1, (int)$tahun_pilih));
$nama_bulan_indo = [
    '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni',
    '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'
];

// --- 4. AMBIL DATA GURU & ABSENSI ---
$guru = [];
$data_absen = [];

$stmt_guru = $conn->prepare("SELECT nip, nama FROM guru ORDER BY nama ASC");
$stmt_guru->execute();
$res_guru = $stmt_guru->get_result();
while($g = $res_guru->fetch_assoc()){ $guru[] = $g; }

if(!empty($guru)){
    $sql_absen = "SELECT nip, DAY(waktu_masuk) as tgl, keterangan, status_kehadiran
                  FROM absensi_guru
                  WHERE MONTH(waktu_masuk) = ?
                  AND YEAR(waktu_masuk) = ?";
    $stmt_absen = $conn->prepare($sql_absen);
    $stmt_absen->bind_param("ss", $bulan_pilih, $tahun_pilih);
    $stmt_absen->execute();
    $res_absen = $stmt_absen->get_result();
    while($row = $res_absen->fetch_assoc()){
        $ket = $row['keterangan'] ?? '';
        $tgl = (int)$row['tgl'];

        // Tentukan kode satu huruf berdasarkan keterangan & status
        if($ket == 'Sakit'){
            $kode = 'S';
        } elseif($ket == 'Izin'){
            $kode = 'I';
        } elseif($ket == 'Bolos'){
            $kode = 'B';
        } else {
            // Hadir (Tepat Waktu atau Terlambat)
            $kode = ($row['status_kehadiran'] == 'Terlambat') ? 'T' : 'H';
        }
        $data_absen[$row['nip']][$tgl] = $kode;
    }
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Bulanan Guru - <?= xss($nama_sekolah) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; color: #333; }
        .nama-col { position: sticky; left: 0; background: white !important; z-index: 2; min-width: 160px; border-right: 2px solid #dee2e6 !important; }
        /* Status colors */
        .h  { background-color: #d1e7dd !important; color: #0f5132 !important; }   /* Hadir */
        .t  { background-color: #fff3cd !important; color: #664d03 !important; }   /* Telat */
        .s  { background-color: #fde8d8 !important; color: #7c3510 !important; }   /* Sakit */
        .i  { background-color: #cff4fc !important; color: #055160 !important; }   /* Izin */
        .b  { background-color: #3b0764 !important; color: #ffffff !important; }   /* Bolos */
        .a  { background-color: #f8d7da !important; color: #842029 !important; }   /* Alpha */
        .l  { background-color: #e9ecef !important; color: #6c757d !important; font-style: italic; }  /* Libur */
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
        <h5 class="mb-1">REKAPITULASI ABSENSI GURU</h5>
        <p>Bulan: <?= xss($nama_bulan_indo[$bulan_pilih]) ?> <?= xss($tahun_pilih) ?></p>
    </div>

    <!-- PANEL FILTER -->
    <div class="card shadow-sm border-0 p-4 mb-4 no-print">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-primary m-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Rekap Bulanan Guru</h4>
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
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Tampilkan</button>
            </div>
        </form>
    </div>

    <!-- LEGENDA -->
    <div class="d-flex flex-wrap gap-2 mb-3 no-print">
        <span class="badge px-3 py-2 h" style="font-size:.78rem;">H = Hadir</span>
        <span class="badge px-3 py-2 t" style="font-size:.78rem;">T = Terlambat</span>
        <span class="badge px-3 py-2 s" style="font-size:.78rem;">S = Sakit</span>
        <span class="badge px-3 py-2 i" style="font-size:.78rem;">I = Izin</span>
        <span class="badge px-3 py-2 b" style="font-size:.78rem;">B = Bolos</span>
        <span class="badge px-3 py-2 a" style="font-size:.78rem;">A = Alpha</span>
        <span class="badge px-3 py-2 l" style="font-size:.78rem;">L = Libur</span>
    </div>

    <?php if(!empty($guru)): ?>
    <div class="card shadow-sm border-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-bordered text-center align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th rowspan="2" width="40">No</th>
                        <th rowspan="2" class="nama-col" style="color: black;">Nama Guru</th>
                        <th colspan="<?= $jumlah_hari ?>">Tanggal (<?= xss($nama_bulan_indo[$bulan_pilih]) ?>)</th>
                        <th colspan="6">Total</th>
                    </tr>
                    <tr>
                        <?php for($d=1; $d<=$jumlah_hari; $d++):
                            $dt     = "$tahun_pilih-$bulan_pilih-" . sprintf("%02d", $d);
                            $day_n  = date('N', strtotime($dt));
                            $is_red = ($day_n == 7 || ($libur_pekanan == 'sabtu_minggu' && $day_n == 6) || in_array($dt, $libur_manual));
                        ?>
                        <th style="font-size: 9px;" class="<?= $is_red ? 'text-danger' : '' ?>"><?= $d ?></th>
                        <?php endfor; ?>
                        <th class="h">H</th>
                        <th class="t">T</th>
                        <th class="s">S</th>
                        <th class="i">I</th>
                        <th class="b">B</th>
                        <th class="a">A</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no=1; foreach($guru as $g):
                        $nip = $g['nip'];
                        $th=0; $tt=0; $ts=0; $ti=0; $tb=0; $ta=0;
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="nama-col fw-bold text-start small"><?= xss($g['nama']) ?></td>
                        <?php for($d=1; $d<=$jumlah_hari; $d++):
                            $dt       = "$tahun_pilih-$bulan_pilih-" . sprintf("%02d", $d);
                            $day_n    = date('N', strtotime($dt));
                            $is_libur = ($day_n == 7 || ($libur_pekanan == 'sabtu_minggu' && $day_n == 6) || in_array($dt, $libur_manual));

                            $st   = $data_absen[$nip][$d] ?? '';
                            $cls  = "";
                            $char = ".";

                            if($st == 'H'){
                                $char='H'; $cls='h'; $th++;
                            } elseif($st == 'T'){
                                $char='T'; $cls='t'; $tt++;
                            } elseif($st == 'S'){
                                $char='S'; $cls='s'; $ts++;
                            } elseif($st == 'I'){
                                $char='I'; $cls='i'; $ti++;
                            } elseif($st == 'B'){
                                $char='B'; $cls='b'; $tb++;
                            } else {
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
                        <td class="bg-light fw-bold"><?= $tt ?></td>
                        <td class="bg-light fw-bold"><?= $ts ?></td>
                        <td class="bg-light fw-bold"><?= $ti ?></td>
                        <td class="bg-light fw-bold"><?= $tb ?></td>
                        <td class="bg-light fw-bold text-danger"><?= $ta ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info text-center">Tidak ada data guru.</div>
    <?php endif; ?>
</div>
</body>
</html>
