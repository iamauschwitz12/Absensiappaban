<?php
// Pastikan koneksi dan data tersedia
include_once 'koneksi.php';
if (!isset($data)) {
    $query_set = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id=1");
    $data = mysqli_fetch_assoc($query_set);
}
?>
<footer class="bg-white border-top py-4 mt-auto">
    <div class="container text-center">
        <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
            <img src="img/<?= $data['logo_sekolah']; ?>" width="30" class="opacity-75">
            <span class="fw-bold text-muted small"><?= $data['nama_sekolah']; ?></span>
        </div>
        <p class="text-muted mb-0" style="font-size: 0.75rem;">
            &copy; <?= date('Y'); ?> - Sistem Presensi Digital Terintegrasi WhatsApp
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>