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

// --- 2. AMBIL PENGATURAN ---
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id = 1");
$stmt_set->execute();
$sett = $stmt_set->get_result()->fetch_assoc();
$libur_pekanan = $sett['libur_pekanan'] ?? 'minggu';
$jam_masuk_ref = $sett['s1_masuk'] ?? '07:00:00';

// --- 3. KONFIGURASI FILTER ---
$mode       = $_GET['mode']       ?? 'harian';
$tgl_harian = $_GET['tgl_harian'] ?? date('Y-m-d');
$tgl_awal   = $_GET['tgl_awal']   ?? date('Y-m-01');
$tgl_akhir  = $_GET['tgl_akhir']  ?? date('Y-m-d');
$keyword    = $_GET['q']          ?? '';

// --- 4. FUNGSI HITUNG HARI KERJA ---
function hitungHariKerjaGuru($start, $end, $libur_p, $conn) {
    $count = 0;
    $period = new DatePeriod(
        new DateTime($start),
        new DateInterval('P1D'),
        (new DateTime($end))->modify('+1 day')
    );
    $stmt_l = $conn->prepare("SELECT tanggal FROM libur_manual WHERE tanggal BETWEEN ? AND ?");
    $stmt_l->bind_param("ss", $start, $end);
    $stmt_l->execute();
    $res_l = $stmt_l->get_result();
    $libur_manual = [];
    while($l = $res_l->fetch_assoc()) { $libur_manual[] = $l['tanggal']; }

    foreach ($period as $date) {
        $hari    = $date->format('N');
        $tgl_str = $date->format('Y-m-d');
        $is_weekend = ($libur_p == 'minggu' && $hari == 7)
                   || ($libur_p == 'sabtu_minggu' && ($hari == 6 || $hari == 7));
        if (!$is_weekend && !in_array($tgl_str, $libur_manual)) { $count++; }
    }
    return $count;
}

// --- 5. LOGIKA QUERY SQL ---
$params = [];
$types  = "";

if ($mode == 'harian') {
    $sql = "SELECT g.nip, g.nama, g.jabatan,
                   ag.waktu_masuk, ag.waktu_pulang,
                   ag.keterangan, ag.status_kehadiran
            FROM guru g
            LEFT JOIN absensi_guru ag ON g.nip = ag.nip AND DATE(ag.waktu_masuk) = ?
            WHERE 1=1";
    $params[] = $tgl_harian;
    $types   .= "s";

    // Pencarian nama / NIP / status / keterangan
    if ($keyword != '') {
        $kw_low = strtolower($keyword);
        if ($kw_low == 'alpha') {
            $sql .= " AND ag.waktu_masuk IS NULL";
        } elseif ($kw_low == 'bolos') {
            $sql .= " AND ag.keterangan = 'Bolos'";
        } else {
            $sql .= " AND (g.nama LIKE ? OR g.nip LIKE ? OR ag.keterangan LIKE ? OR ag.status_kehadiran LIKE ? OR g.jabatan LIKE ?)";
            $search = "%$keyword%";
            $params[] = $search; $params[] = $search; $params[] = $search;
            $params[] = $search; $params[] = $search;
            $types   .= "sssss";
        }
    }

} else {
    // Mode REKAP
    $hari_efektif = hitungHariKerjaGuru($tgl_awal, $tgl_akhir, $libur_pekanan, $conn);
    $sql = "SELECT g.nip, g.nama, g.jabatan,
                   COUNT(CASE WHEN ag.status_kehadiran = 'Tepat Waktu' AND ag.keterangan NOT IN ('Izin','Sakit','Bolos') THEN 1 END) as jml_hadir,
                   COUNT(CASE WHEN ag.status_kehadiran = 'Terlambat'   THEN 1 END) as jml_telat,
                   COUNT(CASE WHEN ag.keterangan = 'Izin'   THEN 1 END) as jml_izin,
                   COUNT(CASE WHEN ag.keterangan = 'Sakit'  THEN 1 END) as jml_sakit,
                   COUNT(CASE WHEN ag.keterangan = 'Bolos'  THEN 1 END) as jml_bolos
            FROM guru g
            LEFT JOIN absensi_guru ag ON g.nip = ag.nip AND DATE(ag.waktu_masuk) BETWEEN ? AND ?
            WHERE 1=1";
    $params[] = $tgl_awal; $params[] = $tgl_akhir; $types .= "ss";

    if ($keyword != '') {
        $sql .= " AND (g.nama LIKE ? OR g.nip LIKE ? OR g.jabatan LIKE ?)";
        $search = "%$keyword%";
        $params[] = $search; $params[] = $search; $params[] = $search;
        $types   .= "sss";
    }
}

if ($mode == 'rekap') { $sql .= " GROUP BY g.nip"; }
$sql .= " ORDER BY g.nama ASC";

$stmt_main = $conn->prepare($sql);
if ($types) $stmt_main->bind_param($types, ...$params);
$stmt_main->execute();
$result = $stmt_main->get_result();

include 'header.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi Guru – <?= xss($sett['nama_sekolah'] ?? 'SISTEM ABSENSI') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f0f3f9;
            background-image:
                radial-gradient(at 0% 0%,   rgba(124,58,237,0.07) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(168,85,247,0.04) 0px, transparent 50%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255,255,255,0.65);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            border: 1px solid rgba(255,255,255,0.4);
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
        }

        .form-select, .form-control {
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.5);
            background: rgba(255,255,255,0.8);
            padding: 10px 15px;
        }

        .table thead th {
            background: rgba(124,58,237,0.06);
            color: #7c3aed;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 18px;
            border: none;
        }

        .badge-status {
            width: 90px; padding: 7px; font-size: 0.65rem;
            border-radius: 10px; font-weight: 800;
            text-transform: uppercase; display: inline-block;
            text-align: center;
        }

        /* Status colors */
        .st-hadir { background: #10b981; color: white; }
        .st-telat { background: #f59e0b; color: white; }
        .st-izin  { background: #0ea5e9; color: white; }
        .st-sakit { background: #f59e0b; color: white; }
        .st-alpha { background: #ef4444; color: white; }
        .st-bolos { background: #7f1d1d; color: white; }

        .btn-vibrant {
            border-radius: 15px; padding: 10px 20px;
            font-weight: 700; transition: 0.3s;
            background: linear-gradient(135deg,#7c3aed,#a855f7);
            border: none; color: #fff;
        }
        .btn-vibrant:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(124,58,237,0.3);
            color: #fff;
        }

        .text-guru { color: #7c3aed !important; }

        .btn-export {
            border-radius: 15px; padding: 10px 20px;
            font-weight: 700; transition: 0.3s;
        }
        .btn-export:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="container py-3">

    <!-- HEADER -->
    <div class="glass-card p-4 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 d-flex align-items-center justify-content-center"
                 style="width:48px;height:48px;background:linear-gradient(135deg,#7c3aed,#a855f7);">
                <i class="bi bi-file-earmark-bar-graph-fill text-white fs-5"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-0 text-guru">Laporan Absensi Guru</h5>
                <small class="text-muted"><?= xss($sett['nama_sekolah'] ?? '') ?></small>
            </div>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary rounded-3 px-3 py-2 fw-bold small">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </div>

    <!-- FILTER -->
    <div class="glass-card p-4 mb-4">
        <form method="GET">
            <div class="row g-3 align-items-end">
                <!-- Mode -->
                <div class="col-md-2">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Mode</label>
                    <select name="mode" id="modeSelect" class="form-select fw-bold text-guru" onchange="toggleInputs()">
                        <option value="harian" <?= $mode == 'harian' ? 'selected' : '' ?>>HARIAN</option>
                        <option value="rekap"  <?= $mode == 'rekap'  ? 'selected' : '' ?>>REKAP</option>
                    </select>
                </div>

                <!-- Pencarian -->
                <div class="col-md-3">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-0 rounded-start-4">
                            <i class="bi bi-search text-guru"></i>
                        </span>
                        <input type="text" name="q" class="form-control border-0 rounded-end-4"
                               placeholder="Nama / NIP / Jabatan..." value="<?= xss($keyword) ?>">
                    </div>
                </div>

                <!-- Harian: Tanggal -->
                <div class="col-md-3" id="inputHarian" style="display:<?= $mode == 'harian' ? 'block' : 'none' ?>;">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Tanggal</label>
                    <input type="date" name="tgl_harian" class="form-control" value="<?= xss($tgl_harian) ?>">
                </div>

                <!-- Rekap: Dari -->
                <div class="col-md-2" id="inputAwal" style="display:<?= $mode == 'rekap' ? 'block' : 'none' ?>;">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Dari</label>
                    <input type="date" name="tgl_awal" class="form-control" value="<?= xss($tgl_awal) ?>">
                </div>

                <!-- Rekap: Sampai -->
                <div class="col-md-2" id="inputAkhir" style="display:<?= $mode == 'rekap' ? 'block' : 'none' ?>;">
                    <label class="small fw-800 text-muted mb-2 text-uppercase">Sampai</label>
                    <input type="date" name="tgl_akhir" class="form-control" value="<?= xss($tgl_akhir) ?>">
                </div>

                <div class="col-md-1">
                    <button type="submit" class="btn btn-vibrant w-100">
                        <i class="bi bi-filter"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- TABEL -->
    <div class="glass-card overflow-hidden">
        <!-- Sub-header: tanggal & export -->
        <div class="p-4 d-flex justify-content-between align-items-center bg-white bg-opacity-40 border-bottom flex-wrap gap-2">
            <div>
                <span class="badge px-3 py-2 fw-800 rounded-pill" style="background:rgba(124,58,237,0.1);color:#7c3aed;font-size:.75rem;">
                    <i class="bi bi-calendar-event me-1"></i>
                    <?php if ($mode == 'harian'): ?>
                        <?= date('d M Y', strtotime($tgl_harian)) ?>
                    <?php else: ?>
                        <?= date('d M Y', strtotime($tgl_awal)) ?> – <?= date('d M Y', strtotime($tgl_akhir)) ?>
                        &nbsp;|&nbsp; <?= $hari_efektif ?? 0 ?> Hari Efektif
                    <?php endif; ?>
                </span>
            </div>
            <a href="export_excel_guru.php?mode=<?= $mode ?>&tgl_harian=<?= $tgl_harian ?>&tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>&q=<?= urlencode($keyword) ?>"
               class="btn btn-success btn-export shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <?php if($mode == 'harian'): ?>
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Identitas Guru</th>
                        <th class="text-center">Jabatan</th>
                        <th class="text-center">Masuk</th>
                        <th class="text-center">Pulang</th>
                        <th class="text-center pe-4">Status</th>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th class="ps-4">Identitas Guru</th>
                        <th class="text-center">Jabatan</th>
                        <th class="text-center text-success">Hadir</th>
                        <th class="text-center text-warning">Telat</th>
                        <th class="text-center text-info">Izin</th>
                        <th class="text-center text-warning">Sakit</th>
                        <th class="text-center" style="color:#7f1d1d;">Bolos</th>
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
                                $jam_m = $row['waktu_masuk']  ? date('H:i', strtotime($row['waktu_masuk']))  : '-';
                                $jam_p = $row['waktu_pulang'] ? date('H:i', strtotime($row['waktu_pulang'])) : '-';
                                $ket   = $row['keterangan'] ?? '';

                                if (!$row['waktu_masuk'] && empty($ket)) {
                                    $st = "ALPHA"; $css = "st-alpha";
                                } elseif (!$row['waktu_masuk'] && $ket == 'Alpha') {
                                    $st = "ALPHA"; $css = "st-alpha";
                                } elseif ($ket == 'Sakit') {
                                    $st = "SAKIT"; $css = "st-sakit";
                                } elseif ($ket == 'Izin') {
                                    $st = "IZIN"; $css = "st-izin";
                                } elseif ($ket == 'Bolos') {
                                    $st = "BOLOS"; $css = "st-bolos";
                                } else {
                                    // Hadir — cek terlambat
                                    $st  = ($row['status_kehadiran'] == 'Terlambat') ? 'TELAT' : 'HADIR';
                                    $css = ($st == 'TELAT') ? 'st-telat' : 'st-hadir';
                                }
                    ?>
                    <tr>
                        <td class="ps-4 text-muted small"><?= $no++ ?></td>
                        <td>
                            <div class="fw-800 text-dark"><?= xss($row['nama']) ?></div>
                            <div class="small text-muted"><?= xss($row['nip']) ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge rounded-pill px-3" style="background:rgba(124,58,237,0.1);color:#7c3aed;font-size:.7rem;">
                                <?= xss($row['jabatan'] ?? '-') ?>
                            </span>
                        </td>
                        <td class="text-center fw-bold" style="color:#7c3aed;"><?= $jam_m ?></td>
                        <td class="text-center fw-bold" style="color:#7c3aed;"><?= $jam_p ?></td>
                        <td class="text-center pe-4">
                            <span class="badge-status <?= $css ?>"><?= $st ?></span>
                        </td>
                    </tr>
                    <?php
                            else:
                                // Mode REKAP
                                $total_in  = $row['jml_hadir'] + $row['jml_telat'] + $row['jml_izin'] + $row['jml_sakit'] + $row['jml_bolos'];
                                $jml_alpha = max(0, $hari_efektif - $total_in);
                    ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="fw-800 text-dark"><?= xss($row['nama']) ?></div>
                            <div class="text-muted small"><?= xss($row['nip']) ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge rounded-pill px-3" style="background:rgba(124,58,237,0.1);color:#7c3aed;font-size:.7rem;">
                                <?= xss($row['jabatan'] ?? '-') ?>
                            </span>
                        </td>
                        <td class="text-center fw-800 text-success"><?= $row['jml_hadir'] ?></td>
                        <td class="text-center fw-800 text-warning"><?= $row['jml_telat'] ?></td>
                        <td class="text-center fw-800 text-info"><?= $row['jml_izin'] ?></td>
                        <td class="text-center fw-800 text-warning"><?= $row['jml_sakit'] ?></td>
                        <td class="text-center fw-800" style="color:#7f1d1d;"><?= $row['jml_bolos'] ?></td>
                        <td class="text-center fw-800 text-danger pe-4"><?= $jml_alpha ?></td>
                    </tr>
                    <?php
                            endif;
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-person-badge fs-1 opacity-25 d-block mb-2"></i>
                                Data tidak ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    function toggleInputs() {
        const mode = document.getElementById("modeSelect").value;
        document.getElementById("inputHarian").style.display = (mode === "harian") ? "block" : "none";
        document.getElementById("inputAwal").style.display   = (mode === "rekap")  ? "block" : "none";
        document.getElementById("inputAkhir").style.display  = (mode === "rekap")  ? "block" : "none";
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
