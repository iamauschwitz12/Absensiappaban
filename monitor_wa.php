<?php
session_start();
include 'koneksi.php';

// Cek keamanan (hanya admin yang boleh lihat)
if(!isset($_SESSION['login']) || $_SESSION['role'] != 'admin'){
    header("location: index.php");
    exit;
}

// Ambil data antrean (Gabungkan dengan tabel siswa untuk mendapatkan Nama)
$query = "SELECT q.*, s.nama as nama_siswa 
          FROM wa_queue q 
          LEFT JOIN siswa s ON q.target = s.no_hp_ortu 
          ORDER BY q.created_at DESC LIMIT 100";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Monitoring Antrean WhatsApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-sent { background-color: #d1e7dd; color: #0f5132; }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold"><i class="bi bi-whatsapp text-success me-2"></i> Log Pengiriman WhatsApp</h4>
            <p class="text-muted mb-0">Menampilkan 100 riwayat pesan terbaru</p>
        </div>
        <a href="pengaturan.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Waktu</th>
                            <th>Nama Siswa / Target</th>
                            <th>Pesan</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="ps-4 small text-muted">
                                    <?= date('d/m H:i:s', strtotime($row['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= $row['nama_siswa'] ?? 'Umum/Luar' ?></div>
                                    <div class="small text-muted"><?= $row['target'] ?></div>
                                </td>
                                <td class="small" style="max-width: 300px;">
                                    <?= htmlspecialchars($row['message']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if($row['status'] == 'pending'): ?>
                                        <span class="badge status-pending border border-warning px-3 py-2">
                                            <i class="bi bi-clock-history me-1"></i> Antre
                                        </span>
                                    <?php else: ?>
                                        <span class="badge status-sent border border-success px-3 py-2">
                                            <i class="bi bi-check2-all me-1"></i> Terkirim
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">Belum ada riwayat pengiriman.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4 text-muted small">
        Halaman ini akan otomatis diperbarui jika Anda melakukan refresh browser.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>