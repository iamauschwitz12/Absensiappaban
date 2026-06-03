<?php
session_start();
include 'koneksi.php';

// --- SECURITY: INISIALISASI CSRF TOKEN & XSS HELPER ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// --- 1. CEK LOGIN ---
if(!isset($_SESSION['login'])){
    header("location: login.php");
    exit;
}

$role = $_SESSION['role'];
$nama_user = $_SESSION['nama'];
$kelas_diampu = $_SESSION['kelas_diampu'] ?? '';

// --- 2. AMBIL PENGATURAN ---
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id = 1");
$stmt_set->execute();
$sett = $stmt_set->get_result()->fetch_assoc();
$libur_pekanan = $sett['libur_pekanan']; 

// --- 3. KONFIGURASI FILTER ---
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'harian';
$tgl_harian = isset($_GET['tgl_harian']) ? $_GET['tgl_harian'] : date('Y-m-d');
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kelas_filter = ($role == 'walikelas') ? $kelas_diampu : (isset($_GET['kelas']) ? $_GET['kelas'] : '');
$keyword = isset($_GET['q']) ? $_GET['q'] : ''; 

// --- 4. FUNGSI HITUNG HARI KERJA ---
function hitungHariKerja($start, $end, $libur_p, $conn) {
    $count = 0;
    $period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));
    
    $stmt_l = $conn->prepare("SELECT tanggal FROM libur_manual WHERE tanggal BETWEEN ? AND ?");
    $stmt_l->bind_param("ss", $start, $end);
    $stmt_l->execute();
    $res_l = $stmt_l->get_result();
    $libur_manual = [];
    while($l = $res_l->fetch_assoc()) { $libur_manual[] = $l['tanggal']; }

    foreach ($period as $date) {
        $hari = $date->format('N');
        $tgl_str = $date->format('Y-m-d');
        $is_weekend = ($libur_p == 'minggu' && $hari == 7) || ($libur_p == 'sabtu_minggu' && ($hari == 6 || $hari == 7));
        if (!$is_weekend && !in_array($tgl_str, $libur_manual)) { $count++; }
    }
    return $count;
}

// --- 5. LOGIKA QUERY SQL ---
$params = [];
$types = "";

if ($mode == 'harian') {
    $sql = "SELECT s.nis, s.nama, s.kelas, s.sesi, a.waktu_masuk, a.waktu_pulang, a.keterangan, a.status_kehadiran
            FROM siswa s
            LEFT JOIN absensi a ON s.nis = a.nis AND DATE(a.waktu_masuk) = ?
            WHERE 1=1";
    $params[] = $tgl_harian;
    $types .= "s";

    // PENCARIAN NAMA / NIS / STATUS
    if ($keyword != '') {
        $kw_low = strtolower($keyword);
        if ($kw_low == 'alpha') {
            $sql .= " AND a.waktu_masuk IS NULL";
        } else if ($kw_low == 'bolos') {
            $sql .= " AND a.waktu_masuk IS NOT NULL AND (a.waktu_pulang IS NULL OR a.waktu_pulang = '0000-00-00 00:00:00')";
        } else {
            $sql .= " AND (s.nama LIKE ? OR s.nis LIKE ? OR a.keterangan LIKE ? OR a.status_kehadiran LIKE ?)";
            $search = "%$keyword%";
            $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search;
            $types .= "ssss";
        }
    }
} else {
    $hari_efektif = hitungHariKerja($tgl_awal, $tgl_akhir, $libur_pekanan, $conn);
    $sql = "SELECT s.nis, s.nama, s.kelas,
            COUNT(CASE WHEN a.keterangan = 'Hadir' AND a.status_kehadiran = 'Tepat Waktu' THEN 1 END) as jml_hadir,
            COUNT(CASE WHEN a.status_kehadiran = 'Terlambat' THEN 1 END) as jml_telat,
            COUNT(CASE WHEN a.keterangan = 'Izin' THEN 1 END) as jml_izin,
            COUNT(CASE WHEN a.keterangan = 'Sakit' THEN 1 END) as jml_sakit,
            COUNT(CASE WHEN a.keterangan = 'Bolos' THEN 1 END) as jml_bolos
            FROM siswa s
            LEFT JOIN absensi a ON s.nis = a.nis AND DATE(a.waktu_masuk) BETWEEN ? AND ?
            WHERE 1=1";
    $params[] = $tgl_awal; $params[] = $tgl_akhir; $types .= "ss";

    if ($keyword != '') {
        $sql .= " AND (s.nama LIKE ? OR s.nis LIKE ?)";
        $search = "%$keyword%";
        $params[] = $search; $params[] = $search; $types .= "ss";
    }
}

if ($kelas_filter != '') { 
    $sql .= " AND s.kelas = ?"; 
    $params[] = $kelas_filter; $types .= "s";
}

if ($mode == 'rekap') { $sql .= " GROUP BY s.nis"; }
$sql .= " ORDER BY s.nama ASC";

$stmt_main = $conn->prepare($sql);
if($types) $stmt_main->bind_param($types, ...$params);
$stmt_main->execute();
$result = $stmt_main->get_result();

$q_kelas = mysqli_query($conn, "SELECT nama_kelas FROM kelas ORDER BY nama_kelas ASC");

include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi - <?= xss($sett['nama_sekolah']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: #f0f3f9; 
            background-image: radial-gradient(at 0% 0%, rgba(13, 110, 253, 0.08) 0px, transparent 50%);
            min-height: 100vh;
        }

        .navbar-custom { background: linear-gradient(90deg, #0d6efd 0%, #6610f2 100%); border: none; }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
        }

        .form-select, .form-control { 
            border-radius: 15px; 
            border: 1px solid rgba(255, 255, 255, 0.5); 
            background: rgba(255, 255, 255, 0.8);
            padding: 10px 15px;
        }

        .table thead th { 
            background: rgba(13, 110, 253, 0.05); 
            color: #0d6efd; 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            padding: 18px; 
            border: none; 
        }

        .badge-status { 
            width: 90px; padding: 7px; font-size: 0.65rem; border-radius: 10px; font-weight: 800; text-transform: uppercase; 
        }

        .st-hadir { background: #10b981; color: white; }
        .st-telat { background: #f59e0b; color: white; }
        .st-izin { background: #0ea5e9; color: white; }
        .st-alpha { background: #ef4444; color: white; }
        .st-bolos { background: #7f1d1d; color: white; }

        .btn-vibrant { 
            border-radius: 15px; padding: 10px 20px; font-weight: 700; transition: 0.3s; 
        }
        .btn-vibrant:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<!-- <nav class="navbar navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container text-center">
        <a class="navbar-brand fw-800 mx-auto" href="#"><i class="bi bi-file-earmark-bar-graph me-2"></i>LAPORAN PRESENSI</a>
    </div>
</nav> -->

<div class="container py-2">
    <div class="glass-card p-4 mb-4">
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Mode</label>
                    <select name="mode" id="modeSelect" class="form-select fw-bold text-primary" onchange="toggleInputs()">
                        <option value="harian" <?= $mode == 'harian' ? 'selected' : '' ?>>HARIAN</option>
                        <option value="rekap" <?= $mode == 'rekap' ? 'selected' : '' ?>>REKAP</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-0 rounded-start-4"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control border-0 rounded-end-4" placeholder="Nama / NIS / Status..." value="<?= xss($keyword) ?>">
                    </div>
                </div>
                <?php if($role != 'walikelas'): ?>
                <div class="col-md-2">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Kelas</label>
                    <select name="kelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php while($k = mysqli_fetch_assoc($q_kelas)): ?>
                            <option value="<?= xss($k['nama_kelas']) ?>" <?= $kelas_filter == $k['nama_kelas'] ? 'selected' : '' ?>><?= xss($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3" id="inputHarian" style="display: <?= $mode == 'harian' ? 'block' : 'none' ?>;">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Tanggal</label>
                    <input type="date" name="tgl_harian" class="form-control" value="<?= xss($tgl_harian) ?>">
                </div>
                <div class="col-md-2" id="inputAwal" style="display: <?= $mode == 'rekap' ? 'block' : 'none' ?>;">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Dari</label>
                    <input type="date" name="tgl_awal" class="form-control" value="<?= xss($tgl_awal) ?>">
                </div>
                <div class="col-md-2" id="inputAkhir" style="display: <?= $mode == 'rekap' ? 'block' : 'none' ?>;">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Sampai</label>
                    <input type="date" name="tgl_akhir" class="form-control" value="<?= xss($tgl_akhir) ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-vibrant w-100"><i class="bi bi-filter"></i></button>
                </div>
            </div>
        </form>
    </div>

    <div class="glass-card overflow-hidden">
        <div class="p-4 d-flex justify-content-between align-items-center bg-white bg-opacity-40 border-bottom">
            <div>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-800">
                    <i class="bi bi-calendar-event me-1"></i>
                    <?= $mode == 'harian' ? date('d M Y', strtotime($tgl_harian)) : date('d M Y', strtotime($tgl_awal)).' - '.date('d M Y', strtotime($tgl_akhir)) ?>
                </span>
            </div>
            <a href="export_excel.php?mode=<?= $mode ?>&tgl_harian=<?= $tgl_harian ?>&tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>&kelas=<?= $kelas_filter ?>&q=<?= urlencode($keyword) ?>" class="btn btn-success btn-vibrant shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <?php if($mode == 'harian'): ?>
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Identitas Siswa</th>
                        <th class="text-center">Masuk</th>
                        <th class="text-center">Pulang</th>
                        <th class="text-center pe-4">Status</th>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th class="ps-4">Identitas Siswa</th>
                        <th class="text-center text-success">Hadir</th>
                        <th class="text-center text-warning">Telat</th>
                        <th class="text-center text-info">Izin/Skt</th>
                        <th class="text-center text-dark">Bolos</th>
                        <th class="text-center text-danger pe-4">Alpha</th>
                    </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $skrg_jam = date('H:i:s');
                    $skrg_tgl = date('Y-m-d');

                    if($result->num_rows > 0):
                        while($row = $result->fetch_assoc()): 
                            if ($mode == 'harian'):
                                $jam_m = $row['waktu_masuk'] ? date('H:i', strtotime($row['waktu_masuk'])) : '-';
                                $jam_p = $row['waktu_pulang'] ? date('H:i', strtotime($row['waktu_pulang'])) : '-';
                                $jam_pulang_patokan = ($row['sesi'] == '1') ? $sett['s1_pulang'] : $sett['s2_pulang'];

                                if(!$row['waktu_masuk']) {
                                    $st = "ALPHA"; $css = "st-alpha";
                                } else {
                                    $sudah_lewat_pulang = ($tgl_harian < $skrg_tgl) || ($tgl_harian == $skrg_tgl && $skrg_jam > $jam_pulang_patokan);
                                    $belum_absen_pulang = (empty($row['waktu_pulang']) || date('H:i:s', strtotime($row['waktu_pulang'])) == '00:00:00');
                                    
                                    if(($sett['wajib_pulang'] == 1) && $sudah_lewat_pulang && $belum_absen_pulang && $row['keterangan'] != 'Izin' && $row['keterangan'] != 'Sakit'){
                                        $st = "BOLOS"; $css = "st-bolos";
                                    } else {
                                        if($row['keterangan'] == 'Izin' || $row['keterangan'] == 'Sakit'){
                                            $st = strtoupper($row['keterangan']); $css = "st-izin";
                                        } else {
                                            $st = ($row['status_kehadiran'] == 'Terlambat') ? 'TELAT' : 'HADIR';
                                            $css = ($st == 'TELAT') ? 'st-telat' : 'st-hadir';
                                        }
                                    }
                                }
                    ?>
                    <tr>
                        <td class="ps-4 text-muted small"><?= $no++ ?></td>
                        <td>
                            <div class="fw-800 text-dark"><?= xss($row['nama']) ?></div>
                            <div class="small text-muted"><?= xss($row['nis']) ?> • <?= xss($row['kelas']) ?></div>
                        </td>
                        <td class="text-center fw-bold text-primary"><?= $jam_m ?></td>
                        <td class="text-center fw-bold text-primary"><?= $jam_p ?></td>
                        <td class="text-center pe-4"><span class="badge badge-status <?= $css ?>"><?= $st ?></span></td>
                    </tr>
                    <?php else: 
                            $total_in = $row['jml_hadir'] + $row['jml_telat'] + $row['jml_izin'] + $row['jml_sakit'] + $row['jml_bolos'];
                            $jml_alpha = max(0, $hari_efektif - $total_in);
                    ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="fw-800 text-dark"><?= xss($row['nama']) ?></div>
                            <div class="text-muted small"><?= xss($row['nis']) ?> • <?= xss($row['kelas']) ?></div>
                        </td>
                        <td class="text-center fw-800 text-success"><?= $row['jml_hadir'] ?></td>
                        <td class="text-center fw-800 text-warning"><?= $row['jml_telat'] ?></td>
                        <td class="text-center fw-800 text-info"><?= $row['jml_izin'] + $row['jml_sakit'] ?></td>
                        <td class="text-center fw-800" style="color: #7f1d1d;"><?= $row['jml_bolos'] ?></td>
                        <td class="text-center fw-800 text-danger pe-4"><?= $jml_alpha ?></td>
                    </tr>
                    <?php endif; endwhile; 
                    else: ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">Data tidak ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function toggleInputs() {
        var mode = document.getElementById("modeSelect").value;
        document.getElementById("inputHarian").style.display = (mode === "harian") ? "block" : "none";
        document.getElementById("inputAwal").style.display = (mode === "rekap") ? "block" : "none";
        document.getElementById("inputAkhir").style.display = (mode === "rekap") ? "block" : "none";
    }
</script>
</body>
</html>