<?php
session_start();
include 'koneksi.php';

function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// Proteksi login
if (!isset($_SESSION['login'])) {
    header("location: login.php"); exit;
}

// Ambil pengaturan
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id = 1");
$stmt_set->execute();
$pengaturan = $stmt_set->get_result()->fetch_assoc();

$nama_sekolah   = xss($pengaturan['nama_sekolah'] ?? 'SISTEM ABSENSI');
$logo_sekolah   = $pengaturan['logo_sekolah'] ?? 'default.png';
$timezone_aktif = $pengaturan['timezone'] ?? 'Asia/Jakarta';
$jam_masuk_ref  = $pengaturan['s1_masuk']  ?? '07:00:00';
$jam_pulang_ref = $pengaturan['s1_pulang'] ?? '15:00:00';

date_default_timezone_set($timezone_aktif);

$label_waktu = ($timezone_aktif == 'Asia/Makassar') ? "WITA" : (($timezone_aktif == 'Asia/Jayapura') ? "WIT" : "WIB");
$tgl_hari_ini = date('Y-m-d');
$jam_sekarang = date('H:i:s');

// Tanggal Indonesia
$daftar_hari  = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$daftar_bulan = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
$tgl_indo = $daftar_hari[date('l')] . ', ' . date('d ') . $daftar_bulan[date('F')] . date(' Y');

// Statistik hari ini
$total_guru = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM guru"))['c'];

$hadir_hari_ini = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as c FROM absensi_guru WHERE DATE(waktu_masuk) = '$tgl_hari_ini'"))['c'];

$terlambat_hari_ini = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as c FROM absensi_guru WHERE DATE(waktu_masuk) = '$tgl_hari_ini' AND status_kehadiran = 'Terlambat'"))['c'];

$belum_hadir = $total_guru - $hadir_hari_ini;

// Data absensi hari ini (lengkap, dengan filter)
$keyword     = $_GET['q'] ?? '';
$filter_status = $_GET['status'] ?? '';
$limit_page  = 40;
$halaman     = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset      = ($halaman - 1) * $limit_page;

$where = "WHERE DATE(ag.waktu_masuk) = '$tgl_hari_ini'";
$params = []; $types = "";

if (!empty($keyword)) {
    $where .= " AND (g.nama LIKE ? OR ag.nip LIKE ? OR g.jabatan LIKE ?)";
    $sk = "%$keyword%"; $params[] = $sk; $params[] = $sk; $params[] = $sk; $types .= "sss";
}
if ($filter_status === 'terlambat') {
    $where .= " AND ag.status_kehadiran = 'Terlambat'";
} elseif ($filter_status === 'tepat') {
    $where .= " AND ag.status_kehadiran = 'Tepat Waktu'";
} elseif ($filter_status === 'pulang') {
    $where .= " AND ag.waktu_pulang IS NOT NULL AND ag.waktu_pulang != '0000-00-00 00:00:00'";
}

$stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM absensi_guru ag JOIN guru g ON ag.nip = g.nip $where");
if ($types) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_halaman = ceil($total_records / $limit_page);

$params_main = $params;
$types_main  = $types;
$params_main[] = $offset; $params_main[] = $limit_page; $types_main .= "ii";

$stmt_main = $conn->prepare("SELECT ag.*, g.nama, g.jabatan, g.foto, g.nip AS guru_nip
    FROM absensi_guru ag
    JOIN guru g ON ag.nip = g.nip
    $where
    ORDER BY ag.waktu_masuk DESC
    LIMIT ?, ?");
$stmt_main->bind_param($types_main, ...$params_main);
$stmt_main->execute();
$data_absensi = $stmt_main->get_result();

include 'header.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="img/<?= xss($logo_sekolah) ?>" type="image/x-icon">
    <title>Absensi Guru - <?= $nama_sekolah ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f0f3f9;
            background-image:
                radial-gradient(at 0% 0%, rgba(124,58,237,0.06) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(168,85,247,0.04) 0px, transparent 50%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255,255,255,0.65);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 24px;
        }

        /* Stat Cards */
        .stat-card {
            border-radius: 20px;
            padding: 20px 22px;
            color: #fff;
            position: relative;
            overflow: hidden;
            border: none;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            right: -20px; top: -20px;
            width: 100px; height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.12);
        }
        .stat-card .stat-num  { font-size: 2.5rem; font-weight: 800; line-height: 1; }
        .stat-card .stat-label{ font-size: 0.72rem; font-weight: 700; opacity: 0.85; text-transform: uppercase; letter-spacing: .5px; }
        .stat-card .stat-icon { font-size: 2rem; opacity: 0.25; position: absolute; right: 18px; bottom: 14px; }

        /* Table */
        .table thead th {
            background: rgba(124,58,237,0.06);
            color: #7c3aed;
            font-size: 0.72rem;
            text-transform: uppercase;
            border: none;
            padding: 14px 16px;
        }
        .table tbody tr { transition: background 0.2s; }
        .table tbody tr:hover { background: rgba(124,58,237,0.03); }

        .avatar-sm {
            width: 44px; height: 44px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #e9d5ff;
        }
        .avatar-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg,#ede9fe,#f3e8ff);
            display: flex; align-items: center; justify-content: center;
            color: #7c3aed; font-size: 1.2rem;
        }

        .badge-status {
            font-size: 0.65rem; font-weight: 800;
            padding: 4px 10px; border-radius: 8px;
            text-transform: uppercase;
        }

        #live-clock {
            background: rgba(255,255,255,0.5);
            padding: 4px 14px; border-radius: 10px;
            font-weight: 800;
        }

        /* Sidebar list */
        .attendance-item {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 16px;
            padding: 10px 14px;
            margin-bottom: 10px;
            display: flex; align-items: center; gap: 10px;
            transition: transform .2s;
        }
        .attendance-item:hover { transform: scale(1.01); }
        .avatar-img  { width: 44px; height: 44px; border-radius: 10px; object-fit: cover; }
        .avatar-icon-css { width: 44px; height: 44px; border-radius: 10px;
            background: #ede9fe; display: flex; align-items: center;
            justify-content: center; font-size: 1.2rem; color: #7c3aed; }
        .student-name { font-weight: 800; font-size: 0.82rem; line-height: 1.2;
            display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .student-class { font-size: 0.68rem; color: #64748b; font-weight: 600; }
        .attendance-time { font-size: 0.8rem; font-weight: 800; }

        .text-guru { color: #7c3aed !important; }
        .btn-guru { background: linear-gradient(135deg,#7c3aed,#a855f7); border: none; color: #fff; border-radius: 14px; }
        .btn-guru:hover { background: linear-gradient(135deg,#6d28d9,#9333ea); color: #fff; transform: translateY(-1px); }

        .form-control, .form-select { border-radius: 12px; border: 1px solid rgba(255,255,255,0.5); background: rgba(255,255,255,0.7); }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">

    <!-- HEADER -->
    <div class="glass-card p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center"
                         style="width:52px;height:52px;background:linear-gradient(135deg,#7c3aed,#a855f7);">
                        <i class="bi bi-person-badge-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-guru">Monitoring Absensi Guru</h4>
                        <p class="text-muted mb-0 small"><?= $tgl_indo ?> | <span id="live-clock">--:--:--</span> <?= $label_waktu ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end flex-wrap">
                <a href="data_guru.php" class="btn btn-outline-secondary rounded-3 px-3 py-2 fw-bold small">
                    <i class="bi bi-people me-1"></i> Data Guru
                </a>
                <a href="dashboard.php" class="btn btn-guru px-3 py-2 fw-bold small">
                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#7c3aed,#a855f7);">
                <div class="stat-label">Total Guru</div>
                <div class="stat-num"><?= $total_guru ?></div>
                <i class="bi bi-people-fill stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#10b981,#34d399);">
                <div class="stat-label">Hadir Hari Ini</div>
                <div class="stat-num"><?= $hadir_hari_ini ?></div>
                <i class="bi bi-check-circle-fill stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#f59e0b,#fbbf24);">
                <div class="stat-label">Terlambat</div>
                <div class="stat-num"><?= $terlambat_hari_ini ?></div>
                <i class="bi bi-clock-fill stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#ef4444,#f87171);">
                <div class="stat-label">Belum Hadir</div>
                <div class="stat-num"><?= $belum_hadir < 0 ? 0 : $belum_hadir ?></div>
                <i class="bi bi-person-x-fill stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- TABEL ABSENSI -->
        <div class="col-lg-8">
            <div class="glass-card p-4">
                <div class="row g-3 mb-4 align-items-center">
                    <div class="col-md-6">
                        <h6 class="fw-bold m-0 text-dark">
                            Riwayat Absensi Hari Ini
                            <span class="badge rounded-pill ms-2 text-guru" style="background:rgba(124,58,237,0.1);font-size:.7rem;"><?= $total_records ?> catatan</span>
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <form method="GET" class="d-flex gap-2">
                            <select name="status" class="form-select form-select-sm w-auto border-0 shadow-sm">
                                <option value="">Semua Status</option>
                                <option value="tepat"     <?= $filter_status === 'tepat'     ? 'selected' : '' ?>>Tepat Waktu</option>
                                <option value="terlambat" <?= $filter_status === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                                <option value="pulang"    <?= $filter_status === 'pulang'    ? 'selected' : '' ?>>Sudah Pulang</option>
                            </select>
                            <div class="input-group input-group-sm shadow-sm">
                                <input type="text" name="q" class="form-control border-0"
                                       placeholder="Cari nama / NIP..." value="<?= xss($keyword) ?>">
                                <button class="btn bg-white border-0" type="submit">
                                    <i class="bi bi-search text-guru"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="5%">FOTO</th>
                                <th>NAMA & NIP</th>
                                <th class="text-center">JABATAN</th>
                                <th class="text-center">MASUK</th>
                                <th class="text-center">PULANG</th>
                                <th class="text-center">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($data_absensi->num_rows > 0): ?>
                                <?php while($row = $data_absensi->fetch_assoc()): ?>
                                <?php
                                    // Tentukan badge status berdasarkan keterangan atau absensi
                                    $ket = strtoupper($row['keterangan'] ?? '');
                                    if (in_array($ket, ['SAKIT', 'IZIN', 'BOLOS', 'ALPHA'])) {
                                        if ($ket == 'SAKIT') {
                                            $badge = '<span class="badge-status bg-warning-subtle text-warning border border-warning-subtle">SAKIT</span>';
                                        } elseif ($ket == 'IZIN') {
                                            $badge = '<span class="badge-status bg-info-subtle text-info border border-info-subtle">IZIN</span>';
                                        } elseif ($ket == 'BOLOS') {
                                            $badge = '<span class="badge-status text-white border" style="background:#450a0a; border-color:#450a0a;">BOLOS</span>';
                                        } else {
                                            $badge = '<span class="badge-status bg-danger-subtle text-danger border border-danger-subtle">ALPHA</span>';
                                        }
                                    } elseif(!empty($row['waktu_pulang']) && $row['waktu_pulang'] != '0000-00-00 00:00:00'){
                                        $badge = '<span class="badge-status bg-danger-subtle text-danger border border-danger-subtle">PULANG</span>';
                                    } elseif($row['status_kehadiran'] == 'Terlambat'){
                                        $badge = '<span class="badge-status bg-warning-subtle text-warning border border-warning-subtle">TERLAMBAT</span>';
                                    } else {
                                        $badge = '<span class="badge-status bg-success-subtle text-success border border-success-subtle">TEPAT WAKTU</span>';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <?php
                                        $fp = "img/guru/" . $row['foto'];
                                        if(!empty($row['foto']) && file_exists($fp)):
                                        ?>
                                        <img src="<?= $fp ?>" class="avatar-sm" alt="">
                                        <?php else: ?>
                                        <div class="avatar-icon"><i class="bi bi-person-badge-fill"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark small"><?= xss($row['nama']) ?></div>
                                        <code class="text-muted" style="font-size:.72rem;">NIP: <?= xss($row['guru_nip']) ?></code>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill px-3 text-guru" style="background:rgba(124,58,237,0.1);font-size:.7rem;">
                                            <?= xss($row['jabatan'] ?? 'Guru') ?>
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold small">
                                        <?= !empty($row['waktu_masuk']) ? date('H:i', strtotime($row['waktu_masuk'])) : '-' ?>
                                    </td>
                                    <td class="text-center fw-bold small">
                                        <?php if(!empty($row['waktu_pulang']) && $row['waktu_pulang'] != '0000-00-00 00:00:00'): ?>
                                            <span class="text-danger"><?= date('H:i', strtotime($row['waktu_pulang'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $badge ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-person-badge fs-1 opacity-25 d-block mb-2"></i>
                                        Belum ada data absensi guru hari ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($total_halaman > 1): ?>
                <nav class="mt-4"><ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= ($halaman <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link border-0 shadow-sm mx-1 rounded-circle"
                           href="?p=<?= $halaman-1 ?>&q=<?= xss($keyword) ?>&status=<?= xss($filter_status) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <li class="page-item active">
                        <span class="page-link border-0 shadow-sm mx-1 rounded-circle px-3"><?= $halaman ?></span>
                    </li>
                    <li class="page-item <?= ($halaman >= $total_halaman) ? 'disabled' : '' ?>">
                        <a class="page-link border-0 shadow-sm mx-1 rounded-circle"
                           href="?p=<?= $halaman+1 ?>&q=<?= xss($keyword) ?>&status=<?= xss($filter_status) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul></nav>
                <?php endif; ?>
            </div>
        </div>

        <!-- SIDEBAR: LOG LIVE -->
        <div class="col-lg-4">
            <div class="glass-card p-4 h-100">
                <h6 class="fw-bold mb-4 text-muted text-uppercase small" style="letter-spacing:.5px;">
                    <i class="bi bi-clock-history me-2 text-guru"></i>Aktivitas Terkini
                </h6>
                <div id="live-log-container" style="overflow-y:auto; max-height:500px; scrollbar-width:none;"></div>

                <!-- Jam patokan -->
                <div class="mt-4 p-3 rounded-3" style="background:rgba(124,58,237,0.05); border:1px solid rgba(124,58,237,0.1);">
                    <div class="small fw-bold text-muted mb-2 text-uppercase" style="font-size:.65rem; letter-spacing:.5px;">Patokan Waktu</div>
                    <div class="d-flex justify-content-between">
                        <div class="text-center">
                            <div style="font-size:.65rem;" class="text-muted">Batas Masuk</div>
                            <div class="fw-bold text-guru"><?= substr($jam_masuk_ref, 0, 5) ?></div>
                        </div>
                        <div class="text-center">
                            <div style="font-size:.65rem;" class="text-muted">Waktu Pulang</div>
                            <div class="fw-bold text-danger"><?= substr($jam_pulang_ref, 0, 5) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Guru belum absen -->
                <div class="mt-3">
                    <div class="small fw-bold text-muted mb-2 text-uppercase" style="font-size:.65rem; letter-spacing:.5px;">
                        <i class="bi bi-exclamation-circle me-1 text-danger"></i>Guru Belum Absen
                    </div>
                    <div id="belum-absen-container" style="max-height:200px; overflow-y:auto; scrollbar-width:none;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Jam live
    function updateClock() {
        const opt = { timeZone: '<?= $timezone_aktif ?>', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
        document.getElementById('live-clock').textContent = new Intl.DateTimeFormat('id-ID', opt).format(new Date());
    }
    setInterval(updateClock, 1000); updateClock();

    // Refresh log aktivitas terkini
    function refreshLog() {
        $.get('get_last_absensi_guru.php', data => $('#live-log-container').html(data));
    }

    // Guru belum absen hari ini
    function refreshBelumAbsen() {
        $.get('get_guru_belum_absen.php', data => $('#belum-absen-container').html(data));
    }

    refreshLog();
    refreshBelumAbsen();
    setInterval(refreshLog,         15000); // refresh setiap 15 detik
    setInterval(refreshBelumAbsen,  30000); // refresh setiap 30 detik
</script>
</body>
</html>
