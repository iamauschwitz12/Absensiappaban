<?php
session_start();
include 'koneksi.php';

// Proteksi Admin
if($_SESSION['role'] !== 'admin'){
    header("location: dashboard.php"); exit;
}

include 'header.php'; // Menggunakan header yang sudah ada di proyekmu
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-5">
                    <h4 class="fw-bold mb-4"><i class="bi bi-database-fill-gear me-2 text-primary"></i> Database Management</h4>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-4 border rounded-4 text-center bg-light">
                                <i class="bi bi-cloud-arrow-down-fill fs-1 text-success"></i>
                                <h5 class="mt-3 fw-bold">Backup Data</h5>
                                <p class="small text-muted">Unduh seluruh database</p>
                                <a href="proses_backup.php" class="btn btn-success w-100 rounded-3">
                                    <i class="bi bi-download me-2"></i>Mulai Backup
                                </a>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-4 border rounded-4 text-center bg-light">
                                <i class="bi bi-cloud-arrow-up-fill fs-1 text-danger"></i>
                                <h5 class="mt-3 fw-bold">Restore Data</h5>
                                <!-- <p class="small text-muted">Upload database untuk mengembalikan data</p> -->
                                <form action="proses_restore.php" method="POST" enctype="multipart/form-data">
                                    <input type="file" name="backup_file" class="form-control form-control-sm mb-2" accept=".sql" required>
                                    <button type="submit" class="btn btn-danger w-100 rounded-3" onclick="return confirm('PERINGATAN: Data saat ini akan ditimpa. Lanjutkan?')">
                                        <i class="bi bi-upload me-2"></i>Mulai Restore
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 alert alert-warning rounded-4 small">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Pastikan Anda melakukan backup secara rutin sebelum melakukan perubahan besar pada sistem.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>