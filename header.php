<?php
// Pastikan session sudah dimulai di file yang memanggil header ini
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Memastikan koneksi tersedia
include_once 'koneksi.php';

// Ambil Role user saat ini
$role = $_SESSION['role'] ?? '';

// Mengambil data pengaturan jika belum ada (agar logo & nama sekolah muncul)
if (!isset($data)) {
    $query_set = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id=1");
    $data = mysqli_fetch_assoc($query_set);
}

// Menentukan halaman aktif untuk menandai menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="img/asofa.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8f9fa;
            padding-top: 80px; 
        }
        .navbar-custom {
            background-color: #0d6efd;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .nav-link {
            font-weight: 500;
            transition: 0.3s;
        }
        .nav-link:hover {
            color: #ffc107 !important;
        }
        .nav-link.active {
            color: #ffc107 !important;
            border-bottom: 2px solid #ffc107;
        }
        .logout-modal .modal-content {
            border-radius: 20px;
            border: none;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <img src="img/<?= htmlspecialchars($data['logo_sekolah'] ?? 'asofa.ico'); ?>" width="35" height="35" class="rounded-circle bg-white shadow-sm" style="object-fit: contain; padding: 2px;">
            <span class="fw-bold"><?= htmlspecialchars($data['nama_sekolah'] ?? 'Sistem Absensi'); ?></span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto gap-2">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>
                
                <?php if ($role == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'data_siswa.php') ? 'active' : ''; ?>" href="data_siswa.php">
                        <i class="bi bi-people me-1"></i> Siswa
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'laporan.php') ? 'active' : ''; ?>" href="laporan.php">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i> Laporan
                    </a>
                </li>

                <?php if ($role == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'pengaturan.php') ? 'active' : ''; ?>" href="pengaturan.php">
                        <i class="bi bi-gear me-1"></i> Pengaturan
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-item ms-lg-3">
                    <a class="nav-link text-warning fw-bold" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                        <i class="bi bi-box-arrow-right me-1"></i> Keluar
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="modal fade logout-modal" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg">
            <div class="modal-body text-center p-4">
                <div class="text-warning mb-3">
                    <i class="bi bi-exclamation-circle" style="font-size: 3rem;"></i>
                </div>
                <h5 class="fw-bold">Yakin ingin keluar?</h5>
                <p class="text-muted small">Anda harus login kembali untuk mengakses sistem.</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-100 rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <a href="logout.php" class="btn btn-danger w-100 rounded-pill fw-bold">Ya, Keluar</a>
                </div>
            </div>
        </div>
    </div>
</div>