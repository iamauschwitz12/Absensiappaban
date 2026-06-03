<?php
session_start();
include 'koneksi.php';

// --- 1. SECURITY ENGINE---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// --- 2. VALIDASI SESI STRICT ---
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
    $stmt_ts = $conn->prepare("SELECT COUNT(*) as total FROM siswa");
    $stmt_ts->execute();
    $total_siswa = $stmt_ts->get_result()->fetch_assoc()['total'];

    $stmt_h = $conn->prepare("SELECT COUNT(*) as total FROM absensi WHERE DATE(waktu_masuk) = ?");
    $stmt_h->bind_param("s", $tgl_hari_ini);
    $stmt_h->execute();
    $total_hadir = $stmt_h->get_result()->fetch_assoc()['total'];

    // LOGIKA TAMBAHAN: HITUNG TELAT
    $stmt_telat = $conn->prepare("SELECT COUNT(*) as total FROM absensi WHERE DATE(waktu_masuk) = ? AND status_kehadiran = 'Terlambat'");
    $stmt_telat->bind_param("s", $tgl_hari_ini);
    $stmt_telat->execute();
    $total_telat = $stmt_telat->get_result()->fetch_assoc()['total'];

    $stmt_count_ba = $conn->prepare("SELECT COUNT(*) as total FROM siswa 
        LEFT JOIN absensi ON siswa.nis = absensi.nis AND DATE(absensi.waktu_masuk) = ? 
        WHERE absensi.nis IS NULL");
    $stmt_count_ba->bind_param("s", $tgl_hari_ini);
} else { 
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

    // LOGIKA TAMBAHAN: HITUNG TELAT WALI KELAS
    $stmt_telat = $conn->prepare("SELECT COUNT(*) as total FROM absensi 
        JOIN siswa ON absensi.nis = siswa.nis 
        WHERE DATE(absensi.waktu_masuk) = ? AND siswa.kelas = ? AND absensi.status_kehadiran = 'Terlambat'");
    $stmt_telat->bind_param("ss", $tgl_hari_ini, $kelas_diampu);
    $stmt_telat->execute();
    $total_telat = $stmt_telat->get_result()->fetch_assoc()['total'];

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
        .glass-card { background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 24px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.6); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .glass-card:hover { box-shadow: 0 15px 45px rgba(0, 0, 0, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.8); }
        
        /* Animasi Masuk */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade { opacity: 0; animation: fadeInUp 0.6s ease-out forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        
        .text-gradient { background: linear-gradient(135deg, #0d6efd, #6610f2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        /* CSS Animasi Menu 3D */
        .menu-btn { background: linear-gradient(145deg, rgba(255, 255, 255, 0.9), rgba(240, 248, 255, 0.6)); backdrop-filter: blur(5px); border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 18px; padding: 18px 10px; text-decoration: none; color: #444; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); height: 100%; box-shadow: 0 4px 15px rgba(13, 110, 253, 0.05); }
        .menu-btn:hover { background: linear-gradient(145deg, rgba(255, 255, 255, 1), rgba(230, 240, 255, 0.9)); transform: translateY(-6px) scale(1.02); border-color: #0d6efd; color: #0d6efd; box-shadow: 0 12px 25px rgba(13, 110, 253, 0.15); }
        .icon-flat { width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; border-radius: 18px; margin-bottom: 12px; background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(102, 16, 242, 0.1)); box-shadow: inset 0 2px 5px rgba(255,255,255,0.8), 0 4px 10px rgba(0,0,0,0.06); border: 1px solid rgba(255,255,255,0.9); transition: all 0.3s ease;}
        .icon-flat img { width: 44px; height: 44px; object-fit: contain; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); filter: drop-shadow(0px 8px 6px rgba(0,0,0,0.15)); }
        .menu-btn:hover .icon-flat img { transform: scale(1.2) translateY(-4px) rotate(-5deg); filter: drop-shadow(0px 12px 10px rgba(0,0,0,0.2)); }
        .menu-text { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; text-align: center; letter-spacing: 0.5px; }
        
        /* CSS Animasi Khusus Icon 3D Statistik */
        @keyframes floatUp {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0px); }
        }
        .icon-3d-stat {
            width: 32px;
            height: 32px;
            object-fit: contain;
            filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.25));
            animation: floatUp 3s ease-in-out infinite;
        }
        .icon-3d-bg {
            position: absolute;
            right: -10px;
            bottom: -20px;
            width: 120px;
            height: 120px;
            opacity: 0.25;
            transform: rotate(-15deg);
            pointer-events: none;
            object-fit: contain;
        }

        .card-stat { border: none; border-radius: 24px; padding: 24px; color: white; position: relative; overflow: hidden; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); border-top: 1px solid rgba(255, 255, 255, 0.5); border-left: 1px solid rgba(255, 255, 255, 0.3); }
        .card-stat:hover { transform: translateY(-10px) scale(1.03); filter: brightness(1.1); }
        .bg-blue { background: linear-gradient(135deg, #2563eb, #3b82f6, #60a5fa); box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3); }
        .bg-green { background: linear-gradient(135deg, #059669, #10b981, #34d399); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3); }
        .bg-purple { background: linear-gradient(135deg, #7c3aed, #8b5cf6, #a78bfa); box-shadow: 0 10px 20px rgba(124, 58, 237, 0.3); } /* UNTUK TELAT */
        .bg-red { background: linear-gradient(135deg, #dc2626, #ef4444, #f87171); box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }
        .bg-orange { background: linear-gradient(135deg, #d97706, #f59e0b, #fbbf24); box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3); }
        #live-clock { background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.6)); backdrop-filter: blur(10px); padding: 8px 20px; border-radius: 12px; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.6); box-shadow: 0 4px 15px rgba(0,0,0,0.05); color: #0d6efd; }
        .item-belum-absen { background: linear-gradient(to right, rgba(239, 68, 68, 0.05), rgba(255, 255, 255, 0.6)); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; border-left: 4px solid #ef4444; transition: all 0.3s ease; }
        .item-belum-absen:hover { background: linear-gradient(to right, rgba(239, 68, 68, 0.1), rgba(255, 255, 255, 0.9)); transform: translateX(5px); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.1); }
        .ba-avatar { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .ba-icon-placeholder { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #fecaca, #fca5a5); color: #b91c1c; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5); }
        .pagination-ba .page-link { padding: 5px 10px; font-size: 0.75rem; border-radius: 8px; margin: 0 2px; font-weight: 700; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="glass-card p-4 mb-4 animate-fade">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h2 class="fw-bold mb-1 text-gradient">Halo, <?= xss($nama_tampil) ?>! ✨</h2>
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

    <div class="row g-3 mb-4 animate-fade delay-1">
        <div class="col-6 col-lg">
            <div class="card-stat bg-blue h-100">
                <img src="https://img.icons8.com/3d-fluency/94/graduation-cap.png" class="icon-3d-bg">
                <div class="d-flex align-items-center mb-2">
                    <img src="https://img.icons8.com/3d-fluency/94/graduation-cap.png" class="icon-3d-stat me-2" style="animation-delay: 0s;">
                    <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Total Siswa</div>
                </div>
                <h1 class="fw-bolder m-0" style="font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);"><?= (int)$total_siswa ?></h1>
            </div>
        </div>
        <!--<div class="col-6 col-lg">-->
            <!--<div class="card-stat bg-green h-100">-->
            <!--    <img src="https://img.icons8.com/3d-fluency/94/ok.png" class="icon-3d-bg">-->
            <!--    <div class="d-flex align-items-center mb-2">-->
            <!--        <img src="https://img.icons8.com/3d-fluency/94/ok.png" class="icon-3d-stat me-2" style="animation-delay: 0.2s;">-->
            <!--        <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Hadir</div>-->
            <!--    </div>-->
            <!--    <h1 class="fw-bolder m-0" style="font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);"><?= (int)$total_hadir ?></h1>-->
            <!--</div>-->
            <div class="col-6 col-lg">
                <a href="grafik_kehadiran.php" style="text-decoration: none; color: inherit; display: block;">
                    <div class="card-stat bg-green h-100" style="cursor: pointer;">
                        <img src="https://img.icons8.com/3d-fluency/94/ok.png" class="icon-3d-bg">
                        <div class="d-flex align-items-center mb-2">
                            <img src="https://img.icons8.com/3d-fluency/94/ok.png" class="icon-3d-stat me-2" style="animation-delay: 0.2s;">
                            <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Hadir</div>
                        </div>
                        <h1 class="fw-bolder m-0" style="font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);"><?= (int)$total_hadir ?></h1>
                    </div>
                </a>
            </div>
        <!--</div>-->
        <div class="col-6 col-lg">
            <div class="card-stat bg-purple h-100">
                <img src="https://img.icons8.com/3d-fluency/94/alarm-clock.png" class="icon-3d-bg">
                <div class="d-flex align-items-center mb-2">
                    <img src="https://img.icons8.com/3d-fluency/94/alarm-clock.png" class="icon-3d-stat me-2" style="animation-delay: 0.3s;">
                    <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Telat</div>
                </div>
                <h1 class="fw-bolder m-0" style="font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);"><?= (int)$total_telat ?></h1>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card-stat bg-red h-100">
                <img src="https://img.icons8.com/3d-fluency/94/delete-sign.png" class="icon-3d-bg">
                <div class="d-flex align-items-center mb-2">
                    <img src="https://img.icons8.com/3d-fluency/94/delete-sign.png" class="icon-3d-stat me-2" style="animation-delay: 0.4s;">
                    <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Belum Absen</div>
                </div>
                <h1 class="fw-bolder m-0" style="font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);"><?= (int)$total_tidak_hadir ?></h1>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card-stat bg-orange h-100">
                <img src="https://img.icons8.com/3d-fluency/94/combo-chart.png" class="icon-3d-bg">
                <!--<div class="d-flex align-items-center mb-2">-->
                <!--    <img src="https://img.icons8.com/3d-fluency/94/combo-chart.png" class="icon-3d-stat me-2" style="animation-delay: 0.6s;">-->
                <!--    <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Persentase</div>-->
                <!--</div>-->
                <a href="monitoring_kelas.php" class="text-decoration-none text-reset">
                    <div class="d-flex align-items-center mb-2" style="cursor: pointer;">
                        <img src="https://img.icons8.com/3d-fluency/94/combo-chart.png" class="icon-3d-stat me-2" style="animation-delay: 0.6s;">
                        <div class="small fw-bold opacity-75 text-uppercase" style="letter-spacing: 0.5px;">Persentase</div>
                    </div>
                </a>
                <h1 class="fw-bolder m-0" style="font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);"><?= (int)$persentase ?>%</h1>
            </div>
        </div>
    </div>

    <div class="row g-4 animate-fade delay-2">
        <div class="col-lg-8">
            <div class="glass-card p-4 h-100">
                <h6 class="fw-bold mb-4 text-muted text-uppercase small" style="letter-spacing: 1px;">Menu Navigasi</h6>
                
                <div class="row g-2 g-md-3">
                    <div class="col-4 col-md-3"><a href="index.php" class="menu-btn"><div class="icon-flat"><img src="https://raw.githubusercontent.com/microsoft/fluentui-emoji/main/assets/Mobile%20phone/3D/mobile_phone_3d.png"></div><span class="menu-text">Scan QR (Siswa)</span></a></div>
                    <div class="col-4 col-md-3"><a href="scan_wajah.php" class="menu-btn"><div class="icon-flat"><img src="https://raw.githubusercontent.com/microsoft/fluentui-emoji/main/assets/Camera/3D/camera_3d.png"></div><span class="menu-text">Scan Wajah (Guru)</span></a></div>
                    <div class="col-4 col-md-3"><a href="laporan.php" class="menu-btn"><div class="icon-flat"><img src="https://raw.githubusercontent.com/microsoft/fluentui-emoji/main/assets/Bar%20chart/3D/bar_chart_3d.png"></div><span class="menu-text">Laporan</span></a></div>
                    <div class="col-4 col-md-3"><a href="input_manual.php" class="menu-btn"><div class="icon-flat"><img src="https://raw.githubusercontent.com/microsoft/fluentui-emoji/main/assets/Memo/3D/memo_3d.png"></div><span class="menu-text">Input Manual</span></a></div>
                    
                    <?php if($role == 'admin' || $role == 'piket'): ?>
                    <div class="col-4 col-md-3"><a href="buat_token.php" class="menu-btn"><div class="icon-flat"><img src="https://raw.githubusercontent.com/microsoft/fluentui-emoji/main/assets/Locked/3D/locked_3d.png"></div><span class="menu-text">Token</span></a></div>
                    <?php endif; ?>
                    
                    <?php if($role == 'admin'): ?>
                    <div class="col-4 col-md-3"><a href="data_siswa.php" class="menu-btn"><div class="icon-flat"><img src="https://img.icons8.com/3d-fluency/94/graduation-cap.png"></div><span class="menu-text">Siswa</span></a></div>
                    <div class="col-4 col-md-3"><a href="data_guru.php" class="menu-btn"><div class="icon-flat"><img src="https://img.icons8.com/color/48/female-teacher.png"></div><span class="menu-text">Guru/Staff</span></a></div>
                    <div class="col-4 col-md-3"><a href="absensi_guru.php" class="menu-btn"><div class="icon-flat"><img src="https://img.icons8.com/fluency/48/checked-user-male.png"></div><span class="menu-text">Absen Guru</span></a></div>
                    <div class="col-4 col-md-3"><a href="data_kelas.php" class="menu-btn"><div class="icon-flat"><img src="https://raw.githubusercontent.com/microsoft/fluentui-emoji/main/assets/School/3D/school_3d.png"></div><span class="menu-text">Kelas</span></a></div>
                    <div class="col-4 col-md-3"><a href="input_manual_guru.php" class="menu-btn"><div class="icon-flat"><img src="https://img.icons8.com/external-flaticons-lineal-color-flat-icons/64/external-report-back-to-work-flaticons-lineal-color-flat-icons-2.png"></div><span class="menu-text">Input Manual Guru</span></a></div>
                    <div class="col-4 col-md-3"><a href="laporan_guru.php" class="menu-btn"><div class="icon-flat"><img src="https://img.icons8.com/arcade/64/edit-pie-chart-report.png"></div><span class="menu-text">Laporan Guru</span></a></div>
                    <div class="col-4 col-md-3"><a href="rekap_bulanan_guru.php" class="menu-btn"><div class="icon-flat"><img src="https://img.icons8.com/bubbles/100/training.png"></div><span class="menu-text">Rekap Guru</span></a></div>
                    <?php endif; ?>
                    
                    <?php if($role == 'admin' || $role == 'walikelas'): ?>
                    <div class="col-4 col-md-3"><a href="rekap_bulanan.php" class="menu-btn"><div class="icon-flat"><img src="https://raw.githubusercontent.com/microsoft/fluentui-emoji/main/assets/Spiral%20calendar/3D/spiral_calendar_3d.png"></div><span class="menu-text">Rekap Siswa</span></a></div>
                    <?php endif; ?>
                    
                    <?php if($role == 'admin'): ?>
                    <div class="col-4 col-md-3">
                        <a href="data_user.php" class="menu-btn">
                            <div class="icon-flat">
                                <img src="https://img.icons8.com/3d-fluency/94/user-shield.png" style="width: 44px; height: 44px; object-fit: contain;">
                            </div>
                            <span class="menu-text">User</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    
                    <?php if($role == 'admin'): ?>
                    <div class="col-4 col-md-3"><a href="pengaturan.php" class="menu-btn"><div class="icon-flat"><img src="https://raw.githubusercontent.com/microsoft/fluentui-emoji/main/assets/Gear/3D/gear_3d.png"></div><span class="menu-text">Setting</span></a></div>
                    <?php endif; ?>

                    <?php if($role == 'admin'): ?>
                    <div class="col-4 col-md-3">
                        <a href="backup_database.php" class="menu-btn">
                            <div class="icon-flat">
                                <img src="https://raw.githubusercontent.com/microsoft/fluentui-emoji/main/assets/Floppy%20disk/3D/floppy_disk_3d.png">
                            </div>
                            <span class="menu-text">Backup</span>
                        </a>
                    </div>
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
                            $adjacents = 1; 
                            if ($total_hal_ba <= 5) {
                                for ($i = 1; $i <= $total_hal_ba; $i++) {
                                    echo '<li class="page-item '.($halaman_ba == $i ? 'active' : '').'"><a class="page-link shadow-sm border-0" href="?p_ba='.$i.'">'.$i.'</a></li>';
                                }
                            } else {
                                if ($halaman_ba <= 3) {
                                    for ($i = 1; $i <= 3; $i++) {
                                        echo '<li class="page-item '.($halaman_ba == $i ? 'active' : '').'"><a class="page-link shadow-sm border-0" href="?p_ba='.$i.'">'.$i.'</a></li>';
                                    }
                                    echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                                    echo '<li class="page-item"><a class="page-link shadow-sm border-0" href="?p_ba='.$total_hal_ba.'">'.$total_hal_ba.'</a></li>';
                                } elseif ($halaman_ba > 3 && $halaman_ba < $total_hal_ba - 2) {
                                    echo '<li class="page-item"><a class="page-link shadow-sm border-0" href="?p_ba=1">1</a></li>';
                                    echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                                    for ($i = $halaman_ba - $adjacents; $i <= $halaman_ba + $adjacents; $i++) {
                                        echo '<li class="page-item '.($halaman_ba == $i ? 'active' : '').'"><a class="page-link shadow-sm border-0" href="?p_ba='.$i.'">'.$i.'</a></li>';
                                    }
                                    echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                                    echo '<li class="page-item"><a class="page-link shadow-sm border-0" href="?p_ba='.$total_hal_ba.'">'.$total_hal_ba.'</a></li>';
                                } else {
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

    <!-- <div class="glass-card mt-4 overflow-hidden mb-5 animate-fade delay-3">
        <div class="p-4 d-flex justify-content-between align-items-center border-bottom border-white border-opacity-20">
            <h6 class="m-0 fw-bold text-success text-uppercase small"><i class="bi bi-whatsapp me-2"></i>Aktivitas WhatsApp</h6>
            <div class="d-flex align-items-center">
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 me-3">
                    <span id="wa-count">0</span> Pesan Antre
                    <i id="wa-loader" class="bi bi-arrow-repeat spin-icon ms-1" style="display: none;"></i>
                </span>
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
    </div> -->
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
            document.getElementById('wa-count').innerText = data.pending + " ";
            if(data.pending > 0) fetch('wa_worker.php');
        });
    }
    setInterval(monitorWA, 20000); monitorWA();
</script>
<script>
// Fungsi untuk menjalankan pengiriman secara berulang (Recursive)
function jalankanPengirimanOtomatis() {
    const waCountEl = document.getElementById('wa-count');
    const waLoader = document.getElementById('wa-loader');

    // 1. Cek jumlah antrian ke server
    fetch('wa_status.php')
        .then(response => response.json())
        .then(data => {
            // Update angka di UI
            waCountEl.innerText = data.pending;

            // 2. Jika ada pesan (misal 109 pesan), jalankan worker
            if (parseInt(data.pending) > 0) {
                // Munculkan animasi loading
                waLoader.style.display = 'inline-block';
                waLoader.classList.add('bi-spin'); // Pastikan kamu punya CSS spin atau pakai class bootstrap jika tersedia

                // Panggil file worker untuk mengirim 5 pesan
                fetch('wa_worker.php')
                    .then(() => {
                        console.log('5 Pesan berhasil diproses...');
                        // Tunggu 3 detik, lalu cek & kirim lagi sampai habis
                        setTimeout(jalankanPengirimanOtomatis, 3000);
                    })
                    .catch(err => {
                        console.error('Gagal memproses wa_worker:', err);
                        waLoader.style.display = 'none';
                    });
            } else {
                // Jika antrian sudah 0, matikan loading & cek lagi 1 menit kemudian
                waLoader.style.display = 'none';
                setTimeout(jalankanPengirimanOtomatis, 60000);
            }
        })
        .catch(err => console.error('Gagal mengambil status:', err));
}

// Tambahkan CSS sederhana agar icon loading bisa berputar
const style = document.createElement('style');
style.innerHTML = `
    @keyframes spin { 100% { transform:rotate(360deg); } }
    .bi-spin { animation: spin 2s linear infinite; display: inline-block; }
`;
document.head.appendChild(style);

// Jalankan fungsi saat halaman dashboard pertama kali dibuka
document.addEventListener('DOMContentLoaded', jalankanPengirimanOtomatis);
</script>
<?php

$modal_dev_asofa = "PGRpdiBjbGFzcz0ibW9kYWwgZmFkZSIgaWQ9ImRldk1vZGFsIiB0YWJpbmRleD0iLTEiIGFyaWEtaGlkZGVuPSJ0cnVlIj48ZGl2IGNsYXNzPSJtb2RhbC1kaWFsb2cgbW9kYWwtZGlhbG9nLWNlbnRlcmVkIj48ZGl2IGNsYXNzPSJtb2RhbC1jb250ZW50IHNoYWRvdy1sZyIgc3R5bGU9ImJvcmRlci1yYWRpdXM6IDI0cHg7IGJvcmRlcjogbm9uZTsgYmFja2dyb3VuZDogcmdiYSgyNTUsMjU1LDI1NSwwLjk1KTsgYmFja2Ryb3AtZmlsdGVyOiBibHVyKDEwcHgpOyI+PGRpdiBjbGFzcz0ibW9kYWwtaGVhZGVyIGJvcmRlci0wIHBiLTAganVzdGlmeS1jb250ZW50LWVuZCBwLTMiPjxidXR0b24gdHlwZT0iYnV0dG9uIiBjbGFzcz0iYnRuLWNsb3NlIiBkYXRhLWJzLWRpc21pc3M9Im1vZGFsIiBhcmlhLWxhYmVsPSJDbG9zZSI+PC9idXR0b24+PC9kaXY+PGRpdiBjbGFzcz0ibW9kYWwtYm9keSB0ZXh0LWNlbnRlciBweC00IHBiLTQgbXQtbjMiPjxkaXYgY2xhc3M9Im1iLTMiPjxpbWcgc3JjPSJodHRwczovL2ltZy5pY29uczguY29tLzNkLWZsdWVuY3kvOTQvYm90LnBuZyIgd2lkdGg9Ijg1IiBzdHlsZT0iZmlsdGVyOiBkcm9wLXNoYWRvdygwIDEwcHggMTBweCByZ2JhKDAsMCwwLDAuMTUpKTsiPjwvZGl2PjxoNCBjbGFzcz0iZnctYm9sZGVyIHRleHQtZGFyayBtYi0xIj5Bc29mYTwvaDQ+PHAgY2xhc3M9InRleHQtbXV0ZWQgc21hbGwgcHgtMyBtYi00Ij5TaXN0ZW0gQWJzZW5zaSBUZXJwYWR1IGRlbmdhbiB0ZWtub2xvZ2kgUVIsIFJGSUQgJiBGYWNlIFNjYW5uZXIuIFRlcmludGVncmFzaSBwZW51aCBkZW5nYW4gbm90aWZpa2FzaSBXaGF0c0FwcCwgVGVsZWdyYW0gZGFuIEVtYWlsIHNlY2FyYSByZWFsLXRpbWUuPC9wPjxkaXYgY2xhc3M9ImQtZ3JpZCBnYXAtMyBweC0zIj48YSBocmVmPSJodHRwczovL3d3dy55b3V0dWJlLmNvbS93YXRjaD92PWVtYVd1U2dZaG0wJmxpc3Q9UExuR19JdjRISXJsWHQxTEZLZVN4S2hkbmdnVFI2T1dkMCIgdGFyZ2V0PSJfYmxhbmsiIGNsYXNzPSJidG4gcm91bmRlZC1waWxsIGZ3LWJvbGQgcHktMiBkLWZsZXggYWxpZ24taXRlbXMtY2VudGVyIGp1c3RpZnktY29udGVudC1jZW50ZXIgdGV4dC13aGl0ZSBzaGFkb3ctc20iIHN0eWxlPSJiYWNrZ3JvdW5kOiBsaW5lYXItZ3JhZGllbnQoMTM1ZGVnLCAjZmYwMDAwLCAjY2MwMDAwKTsgYm9yZGVyOiBub25lOyI+PGkgY2xhc3M9ImJpIGJpLXlvdXR1YmUgZnMtNSBtZS0yIj48L2k+IENoYW5uZWwgWW91VHViZTwvYT48YSBocmVmPSJodHRwczovL3QubWUvc3FfZnJoIiB0YXJnZXQ9Il9ibGFuayIgY2xhc3M9ImJ0biByb3VuZGVkLXBpbGwgZnctYm9sZCBweS0yIGQtZmxleCBhbGlnbi1pdGVtcy1jZW50ZXIganVzdGlmeS1jb250ZW50LWNlbnRlciB0ZXh0LXdoaXRlIHNoYWRvdy1zbSIgc3R5bGU9ImJhY2tncm91bmQ6IGxpbmVhci1ncmFkaWVudCgxMzVkZWcsICMwMDg4Y2MsICMwMDU1ODApOyBib3JkZXI6IG5vbmU7Ij48aSBjbGFzcz0iYmkgYmktdGVsZWdyYW0gZnMtNSBtZS0yIj48L2k+IFRlbGVncmFtPC9hPjxhIGhyZWY9Imh0dHBzOi8vbHluay5pZC9zcS1mcmgiIHRhcmdldD0iX2JsYW5rIiBjbGFzcz0iYnRuIHJvdW5kZWQtcGlsbCBmdy1ib2xkIHB5LTIgZC1mbGV4IGFsaWduLWl0ZW1zLWNlbnRlciBqdXN0aWZ5LWNvbnRlbnQtY2VudGVyIHRleHQtd2hpdGU internships-smkiIHN0eWxlPSJiYWNrZ3JvdW5kOiBsaW5lYXItZ3JhZGllbnQoMTM1ZGVnLCAjMjU2M2ViLCAjMWQ0ZWQ4KTsgYm9yZGVyOiBub25lOyI+PGkgY2xhc3M9ImJpIGJpLWdsb2JlIGZzLTUgbWUtMiI+PC9pPiBBcGxpa2FzaSBMYWlubnlhPC9hPjwvZGl2PjwvZGl2PjxkaXYgY2xhc3M9Im1vZGFsLWZvb3RlciBqdXN0aWZ5LWNvbnRlbnQtY2VudGVyIGJvcmRlci0wIGJnLWxpZ2h0IHB5LTMiPjxzcGFuIGNsYXNzPSJzbWFsbCB0ZXh0LW11dGVkIGZ3LWJvbGQiPiZjb3B5OyBbVEFIVU5dIHwgRGV2ZWxvcGVyIGJ5IEFzb2ZhPC9zcGFuPjwvZGl2PjwvZGl2PjwvZGl2PjwvZGl2Pg==";

$html_final = base64_decode($modal_dev_asofa);
$html_final = str_replace("[TAHUN]", date('Y'), $html_final);

echo $html_final;
?>
</body>
</html>