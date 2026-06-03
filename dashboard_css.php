<?php
session_start();
include 'koneksi.php';

// --- 1. SECURITY ENGINE (TINGKAT DEWA) ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// --- 2. VALIDASI SESI STRICT (ANTI BYPASS) ---
if(!isset($_SESSION['login']) || empty($_SESSION['id'])){
    session_destroy();
    header("location: login.php");
    exit;
}

$id_user = (int)$_SESSION['id'];
$role = $_SESSION['role'] ?? 'user';
$kelas_diampu = $_SESSION['kelas_diampu'] ?? 'Semua Kelas';

// Ambil data user
$stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->bind_param("i", $id_user);
$stmt_user->execute();
$d_user = $stmt_user->get_result()->fetch_assoc();

// Nama Tampil
if (!empty(trim($d_user['nama'] ?? ''))) {
    $nama_asli = $d_user['nama'];
} elseif (!empty(trim($d_user['username'] ?? ''))) {
    $nama_asli = $d_user['username'];
} else {
    $nama_asli = $_SESSION['nama'] ?? 'Pengguna'; 
}
$nama_tampil = ucwords(strtolower($nama_asli));

// --- 3. STATISTIK UTAMA (Dihitung di awal untuk Pagination) ---
$tgl_hari_ini = date('Y-m-d');

if($role == 'admin' || $role == 'piket'){
    // Total Siswa
    $stmt_ts = $conn->prepare("SELECT COUNT(*) as total FROM siswa");
    $stmt_ts->execute();
    $total_siswa = $stmt_ts->get_result()->fetch_assoc()['total'];

    // Hadir
    $stmt_h = $conn->prepare("SELECT COUNT(*) as total FROM absensi WHERE DATE(waktu_masuk) = ?");
    $stmt_h->bind_param("s", $tgl_hari_ini);
    $stmt_h->execute();
    $total_hadir = $stmt_h->get_result()->fetch_assoc()['total'];

    // Hitung Total Belum Absen (Untuk Pagination)
    $stmt_count_ba = $conn->prepare("SELECT COUNT(*) as total FROM siswa 
        LEFT JOIN absensi ON siswa.nis = absensi.nis AND DATE(absensi.waktu_masuk) = ? 
        WHERE absensi.nis IS NULL");
    $stmt_count_ba->bind_param("s", $tgl_hari_ini);
} else { 
    // KHUSUS WALI KELAS
    $stmt_ts = $conn->prepare("SELECT COUNT(*) as total FROM siswa WHERE kelas = ?");
    $stmt_ts->bind_param("s", $kelas_diampu);
    $stmt_ts->execute();
    $total_siswa = $stmt_ts->get_result()->fetch_assoc()['total'];

    $stmt_h = $conn->prepare("SELECT COUNT(*) as total FROM absensi 
        JOIN siswa ON absensi.nis = siswa.nis 
        WHERE DATE(absensi.waktu_masuk) = ? AND siswa.kelas = ?");
    $stmt_h->bind_param("ss", $tgl_hari_ini, $kelas_diampu);
    $stmt_h->execute();
    $total_hadir = $stmt_h->get_result()->fetch_assoc()['total'];

    $stmt_count_ba = $conn->prepare("SELECT COUNT(*) as total FROM siswa 
        LEFT JOIN absensi ON siswa.nis = absensi.nis AND DATE(absensi.waktu_masuk) = ? 
        WHERE absensi.nis IS NULL AND siswa.kelas = ?");
    $stmt_count_ba->bind_param("ss", $tgl_hari_ini, $kelas_diampu);
}

$stmt_count_ba->execute();
$total_tidak_hadir = $stmt_count_ba->get_result()->fetch_assoc()['total'];
$persentase = $total_siswa > 0 ? round(($total_hadir / $total_siswa) * 100) : 0;

// --- 4. LOGIKA PAGINATION BELUM ABSEN ---
$limit_ba = 5; 
$halaman_ba = isset($_GET['p_ba']) ? (int)$_GET['p_ba'] : 1;
$offset_ba = ($halaman_ba - 1) * $limit_ba;
$total_hal_ba = ($total_tidak_hadir > 0) ? ceil($total_tidak_hadir / $limit_ba) : 1;

if($role == 'admin' || $role == 'piket'){
    $stmt_ba = $conn->prepare("SELECT siswa.nama, siswa.kelas, siswa.foto FROM siswa 
        LEFT JOIN absensi ON siswa.nis = absensi.nis AND DATE(absensi.waktu_masuk) = ? 
        WHERE absensi.nis IS NULL ORDER BY siswa.kelas ASC, siswa.nama ASC LIMIT ? OFFSET ?");
    $stmt_ba->bind_param("sii", $tgl_hari_ini, $limit_ba, $offset_ba);
} else { 
    $stmt_ba = $conn->prepare("SELECT siswa.nama, siswa.kelas, siswa.foto FROM siswa 
        LEFT JOIN absensi ON siswa.nis = absensi.nis AND DATE(absensi.waktu_masuk) = ? 
        WHERE absensi.nis IS NULL AND siswa.kelas = ? ORDER BY siswa.nama ASC LIMIT ? OFFSET ?");
    $stmt_ba->bind_param("ssii", $tgl_hari_ini, $kelas_diampu, $limit_ba, $offset_ba);
}
$stmt_ba->execute();
$q_ba_paginated = $stmt_ba->get_result();

// --- 5. LOGIKA PAGINATION WA QUEUE ---
$limit_wa = 10; 
$halaman_wa = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$offset_wa = ($halaman_wa - 1) * $limit_wa;

$total_wa_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM wa_queue");
$total_wa_data = mysqli_fetch_assoc($total_wa_query)['total'];
$total_wa_hal = ($total_wa_data > 0) ? ceil($total_wa_data / $limit_wa) : 1;

$stmt_wa = $conn->prepare("SELECT q.*, s.nama as nama_siswa 
            FROM wa_queue q 
            LEFT JOIN siswa s ON q.target = s.no_hp_ortu 
            ORDER BY q.created_at DESC LIMIT ? OFFSET ?");
$stmt_wa->bind_param("ii", $limit_wa, $offset_wa);
$stmt_wa->execute();
$query_wa = $stmt_wa->get_result();

// --- 6. PENGATURAN TAMBAHAN ---
if(isset($_POST['bersihkan_wa'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Akses Ilegal!");
    }
    $stmt_del = $conn->prepare("DELETE FROM wa_queue WHERE status = 'sent'");
    $stmt_del->execute();
    echo "<script>alert('Riwayat dibersihkan!'); window.location='dashboard.php';</script>";
    exit;
}

$querySetting = mysqli_query($conn, "SELECT timezone FROM pengaturan WHERE id=1");
$sett = mysqli_fetch_assoc($querySetting);
$timezone_aktif = $sett['timezone'] ?? 'Asia/Jakarta';

$daftar_hari = array('Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu');
$daftar_bulan = array('January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember');
$tgl_indo = $daftar_hari[date('l')] . ', ' . date('d ') . $daftar_bulan[date('F')] . date(' Y');

include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f0f3f9; background-image: radial-gradient(at 10% 10%, rgba(13, 110, 253, 0.15) 0px, transparent 50%), radial-gradient(at 90% 10%, rgba(102, 16, 242, 0.1) 0px, transparent 50%), radial-gradient(at 50% 90%, rgba(220, 53, 69, 0.08) 0px, transparent 50%); min-height: 100vh; }
        .glass-card { background: rgba(255, 255, 255, 0.5); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 20px; box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07); }
        .menu-btn { background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(5px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 18px; padding: 18px 10px; text-decoration: none; color: #444; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s ease; height: 100%; }
        .menu-btn:hover { background: rgba(255, 255, 255, 0.9); transform: translateY(-3px); border-color: rgba(13, 110, 253, 0.5); color: #0d6efd; }
        .icon-flat { width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; border-radius: 14px; margin-bottom: 12px; font-size: 1.5rem; }
        .menu-text { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; text-align: center; letter-spacing: 0.5px; }
        .card-stat { border: none; border-radius: 24px; padding: 24px; color: white; position: relative; overflow: hidden; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); border-top: 1px solid rgba(255, 255, 255, 0.4); border-left: 1px solid rgba(255, 255, 255, 0.2); }
        .card-stat:hover { transform: translateY(-8px) scale(1.02); }
        .stat-icon-bg { position: absolute; right: -10px; bottom: -20px; font-size: 6rem; opacity: 0.15; transform: rotate(-15deg); pointer-events: none; }
        .bg-blue { background: linear-gradient(135deg, #2563eb, #3b82f6, #60a5fa); box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3); }
        .bg-green { background: linear-gradient(135deg, #059669, #10b981, #34d399); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3); }
        .bg-red { background: linear-gradient(135deg, #dc2626, #ef4444, #f87171); box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }
        .bg-orange { background: linear-gradient(135deg, #d97706, #f59e0b, #fbbf24); box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3); }
        #live-clock { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(5px); padding: 6px 16px; border-radius: 10px; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.3); }
        .item-belum-absen { background: rgba(255, 255, 255, 0.6); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 12px; border-left: 4px solid #ef4444; transition: 0.2s; }
        .item-belum-absen:hover { background: rgba(255, 255, 255, 0.9); }
        .ba-avatar { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .ba-icon-placeholder { width: 40px; height: 40px; border-radius: 10px; background: #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .pagination-ba .page-link { padding: 5px 10px; font-size: 0.75rem; border-radius: 8px; margin: 0 2px; font-weight: 700; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="glass-card p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h2 class="fw-bold mb-1 text-primary">Halo, <?= xss($nama_tampil) ?>! ✨</h2>
                <p class="text-muted mb-0 small">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 rounded-pill px-3 py-2">
                        <?= xss(strtoupper($d_user['nama_lengkap'] ?? $role)) ?>
                    </span> 
                    <?php if($role == 'walikelas'): ?> | Kelas: <strong class="text-dark"><?= xss($kelas_diampu) ?></strong><?php endif; ?>
                </p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="text-primary fs-3 d-inline-block" id="live-clock">--:--:--</div>
                <div class="small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;"><?= xss($tgl_indo) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card-stat bg-blue h-100">
                <i class="bi bi-people-fill stat-icon-bg"></i>
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-person-lines-fill fs-5 me-2 opacity-75"></i>
                    <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Total Siswa</div>
                </div>
                <h1 class="fw-bolder m-0" style="font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);"><?= (int)$total_siswa ?></h1>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card-stat bg-green h-100">
                <i class="bi bi-check-circle-fill stat-icon-bg"></i>
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-fingerprint fs-5 me-2 opacity-75"></i>
                    <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Hadir</div>
                </div>
                <h1 class="fw-bolder m-0" style="font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);"><?= (int)$total_hadir ?></h1>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card-stat bg-red h-100">
                <i class="bi bi-x-octagon-fill stat-icon-bg"></i>
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-person-x-fill fs-5 me-2 opacity-75"></i>
                    <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Belum Absen</div>
                </div>
                <h1 class="fw-bolder m-0" style="font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);"><?= (int)$total_tidak_hadir ?></h1>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card-stat bg-orange h-100">
                <i class="bi bi-pie-chart-fill stat-icon-bg"></i>
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-graph-up-arrow fs-5 me-2 opacity-75"></i>
                    <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Persentase</div>
                </div>
                <h1 class="fw-bolder m-0" style="font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);"><?= (int)$persentase ?>%</h1>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-card p-4 h-100">
                <h6 class="fw-bold mb-4 text-muted text-uppercase small" style="letter-spacing: 1px;">Menu Navigasi</h6>
                <div class="row g-2 g-md-3">
                    <div class="col-4 col-md-3"><a href="index.php" class="menu-btn"><div class="icon-flat bg-primary bg-opacity-10 text-primary"><i class="bi bi-qr-code-scan"></i></div><span class="menu-text">Scan Absen</span></a></div>
                    <div class="col-4 col-md-3"><a href="scan_wajah.php" class="menu-btn"><div class="icon-flat bg-info bg-opacity-10 text-info"><i class="bi bi-person-bounding-box"></i></div><span class="menu-text">Scan Wajah</span></a></div>
                    <div class="col-4 col-md-3"><a href="scan_rfid.php" class="menu-btn"><div class="icon-flat bg-primary bg-opacity-10 text-primary"><i class="bi bi-broadcast"></i></div><span class="menu-text">Scan RFID</span></a></div>
                    <div class="col-4 col-md-3"><a href="laporan.php" class="menu-btn"><div class="icon-flat bg-success bg-opacity-10 text-success"><i class="bi bi-file-earmark-bar-graph"></i></div><span class="menu-text">Laporan</span></a></div>
                    <div class="col-4 col-md-3"><a href="input_manual.php" class="menu-btn"><div class="icon-flat bg-warning bg-opacity-10 text-warning"><i class="bi bi-pencil-square"></i></div><span class="menu-text">Input Manual</span></a></div>
                    <?php if($role == 'admin' || $role == 'piket'): ?>
                    <div class="col-4 col-md-3"><a href="buat_token.php" class="menu-btn"><div class="icon-flat bg-danger bg-opacity-10 text-danger"><i class="bi bi-shield-lock"></i></div><span class="menu-text">Token</span></a></div>
                    <?php endif; ?>
                    <?php if($role == 'admin'): ?>
                    <div class="col-4 col-md-3"><a href="data_siswa.php" class="menu-btn"><div class="icon-flat bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-people"></i></div><span class="menu-text">Siswa</span></a></div>
                    <div class="col-4 col-md-3"><a href="data_kelas.php" class="menu-btn"><div class="icon-flat bg-primary bg-opacity-10 text-primary"><i class="bi bi-building"></i></div><span class="menu-text">Kelas</span></a></div>
                    <?php endif; ?>
                    <?php if($role == 'admin' || $role == 'walikelas'): ?>
                    <div class="col-4 col-md-3"><a href="rekap_bulanan.php" class="menu-btn"><div class="icon-flat bg-info bg-opacity-10 text-info"><i class="bi bi-calendar3"></i></div><span class="menu-text">Rekap</span></a></div>
                    <?php endif; ?>
                    <?php if($role == 'admin'): ?>
                    <div class="col-4 col-md-3"><a href="pengaturan.php" class="menu-btn"><div class="icon-flat bg-dark bg-opacity-10 text-dark"><i class="bi bi-sliders"></i></div><span class="menu-text">Setting</span></a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card p-4 h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0 text-danger text-uppercase small" style="letter-spacing: 1px;">Belum Absen</h6>
                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-1 small"><?= $total_tidak_hadir ?></span>
                </div>

                <div class="list-tidak-hadir flex-grow-1">
                    <?php if($q_ba_paginated->num_rows > 0): ?>
                        <?php while($siswa = $q_ba_paginated->fetch_assoc()): ?>
                            <div class="p-2 mb-2 item-belum-absen d-flex align-items-center">
                                <div class="me-3">
                                    <?php 
                                    $path_ba = "img/siswa/" . $siswa['foto'];
                                    if(!empty($siswa['foto']) && file_exists($path_ba)): 
                                    ?>
                                        <img src="<?= $path_ba ?>" class="ba-avatar">
                                    <?php else: ?>
                                        <div class="ba-icon-placeholder"><i class="bi bi-person-x"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small fw-800 text-dark lh-sm"><?= xss($siswa['nama']) ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;"><?= xss($siswa['kelas']) ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check2-circle text-success fs-1"></i>
                            <p class="text-muted small fw-bold mt-2">Semua Hadir!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if($total_hal_ba > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination pagination-ba justify-content-center m-0">
                            
                            <li class="page-item <?= ($halaman_ba <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link shadow-sm border-0" href="?p_ba=<?= $halaman_ba - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                            </li>

                            <?php
                            $adjacents = 1; // Jumlah angka di sekitar halaman aktif

                            if ($total_hal_ba <= 5) {
                                // Jika total halaman sedikit, tampilkan semua
                                for ($i = 1; $i <= $total_hal_ba; $i++) {
                                    echo '<li class="page-item '.($halaman_ba == $i ? 'active' : '').'"><a class="page-link shadow-sm border-0" href="?p_ba='.$i.'">'.$i.'</a></li>';
                                }
                            } else {
                                // Jika halaman sangat banyak (Logika Ellipsis)
                                if ($halaman_ba <= 3) {
                                    // Dekat awal: 1 2 3 ... 230
                                    for ($i = 1; $i <= 3; $i++) {
                                        echo '<li class="page-item '.($halaman_ba == $i ? 'active' : '').'"><a class="page-link shadow-sm border-0" href="?p_ba='.$i.'">'.$i.'</a></li>';
                                    }
                                    echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                                    echo '<li class="page-item"><a class="page-link shadow-sm border-0" href="?p_ba='.$total_hal_ba.'">'.$total_hal_ba.'</a></li>';
                                } elseif ($halaman_ba > 3 && $halaman_ba < $total_hal_ba - 2) {
                                    // Di tengah: 1 ... 5 6 ... 230
                                    echo '<li class="page-item"><a class="page-link shadow-sm border-0" href="?p_ba=1">1</a></li>';
                                    echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                                    for ($i = $halaman_ba - $adjacents; $i <= $halaman_ba + $adjacents; $i++) {
                                        echo '<li class="page-item '.($halaman_ba == $i ? 'active' : '').'"><a class="page-link shadow-sm border-0" href="?p_ba='.$i.'">'.$i.'</a></li>';
                                    }
                                    echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                                    echo '<li class="page-item"><a class="page-link shadow-sm border-0" href="?p_ba='.$total_hal_ba.'">'.$total_hal_ba.'</a></li>';
                                } else {
                                    // Dekat akhir: 1 ... 228 229 230
                                    echo '<li class="page-item"><a class="page-link shadow-sm border-0" href="?p_ba=1">1</a></li>';
                                    echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                                    for ($i = $total_hal_ba - 2; $i <= $total_hal_ba; $i++) {
                                        echo '<li class="page-item '.($halaman_ba == $i ? 'active' : '').'"><a class="page-link shadow-sm border-0" href="?p_ba='.$i.'">'.$i.'</a></li>';
                                    }
                                }
                            }
                            ?>

                            <li class="page-item <?= ($halaman_ba >= $total_hal_ba) ? 'disabled' : '' ?>">
                                <a class="page-link shadow-sm border-0" href="?p_ba=<?= $halaman_ba + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="glass-card mt-4 overflow-hidden">
        <div class="p-4 d-flex justify-content-between align-items-center border-bottom border-white border-opacity-20">
            <h6 class="m-0 fw-bold text-success text-uppercase small"><i class="bi bi-whatsapp me-2"></i>Aktivitas WhatsApp</h6>
            <div class="d-flex align-items-center">
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 me-3" id="wa-count">0 Pesan Antre</span>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" name="bersihkan_wa" class="btn btn-sm btn-outline-danger rounded-pill px-3">Bersihkan</button>
                </form>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size: 0.85rem; background: transparent;">
                <thead class="table-light bg-opacity-50">
                    <tr><th class="ps-4">Waktu</th><th>Siswa</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query_wa) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($query_wa)): ?>
                        <tr style="background: rgba(255,255,255,0.2);">
                            <td class="ps-4 text-muted"><?= xss(date('H:i', strtotime($row['created_at']))) ?></td>
                            <td class="fw-bold text-dark"><?= xss($row['nama_siswa'] ?? $row['target']) ?></td>
                            <td><span class="badge rounded-pill px-3 py-1 <?= ($row['status'] == 'pending') ? 'bg-warning text-dark' : 'bg-success' ?>"><?= xss(strtoupper($row['status'])) ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted small">Tidak ada antrean pesan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function updateClock() {
        const options = { timeZone: '<?= $timezone_aktif ?>', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
        const now = new Intl.DateTimeFormat('id-ID', options).format(new Date());
        document.getElementById('live-clock').textContent = now;
    }
    setInterval(updateClock, 1000); updateClock();

    function monitorWA() {
        fetch('wa_status.php').then(res => res.json()).then(data => {
            document.getElementById('wa-count').innerText = data.pending + " Pesan Antre";
            if(data.pending > 0) fetch('wa_worker.php');
        });
    }
    setInterval(monitorWA, 20000); monitorWA();
</script>
</body>
</html>