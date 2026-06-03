<?php
session_start();
include 'koneksi.php';

// --- SECURITY: INISIALISASI CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fungsi Keamanan XSS
function xss($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Cek Admin
if(!isset($_SESSION['login']) || $_SESSION['role'] != 'admin'){
    header("location: dashboard.php");
    exit;
}

// --- LOGIKA TAMBAH KELAS (PREPARED STATEMENT) ---
if(isset($_POST['tambah'])){
    if($_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Token keamanan tidak valid.");
    
    $nama_kelas = strtoupper(trim($_POST['nama_kelas']));
    
    $stmt_cek = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ?");
    $stmt_cek->bind_param("s", $nama_kelas);
    $stmt_cek->execute();
    if($stmt_cek->get_result()->num_rows > 0){
        echo "<script>alert('Gagal! Nama kelas sudah ada.');</script>";
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO kelas (nama_kelas) VALUES (?)");
        $stmt_ins->bind_param("s", $nama_kelas);
        $stmt_ins->execute();
        echo "<script>alert('Berhasil menambah kelas!'); window.location='data_kelas.php';</script>";
    }
}

// --- LOGIKA EDIT KELAS (PREPARED STATEMENT) ---
if(isset($_POST['edit'])){
    if($_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Token keamanan tidak valid.");

    $id = (int)$_POST['id_kelas'];
    $nama_baru = strtoupper(trim($_POST['nama_kelas']));

    // Ambil nama lama untuk update relasi di tabel siswa
    $stmt_old = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
    $stmt_old->bind_param("i", $id);
    $stmt_old->execute();
    $nama_lama = $stmt_old->get_result()->fetch_assoc()['nama_kelas'];

    // Update Kelas
    $stmt_upd = $conn->prepare("UPDATE kelas SET nama_kelas = ? WHERE id = ?");
    $stmt_upd->bind_param("si", $nama_baru, $id);
    
    if($stmt_upd->execute()){
        // Update data siswa yang kelasnya berubah
        $stmt_upd_s = $conn->prepare("UPDATE siswa SET kelas = ? WHERE kelas = ?");
        $stmt_upd_s->bind_param("ss", $nama_baru, $nama_lama);
        $stmt_upd_s->execute();
        echo "<script>alert('Berhasil diperbarui!'); window.location='data_kelas.php';</script>";
    }
}

// --- LOGIKA HAPUS MASAL (PREPARED STATEMENT) ---
if(isset($_POST['proses_hapus'])){
    if($_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Token keamanan tidak valid.");

    $id_hapus = (int)$_POST['id_kelas_hapus'];

    $stmt_kelas = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
    $stmt_kelas->bind_param("i", $id_hapus);
    $stmt_kelas->execute();
    $d_kelas = $stmt_kelas->get_result()->fetch_assoc();
    
    if($d_kelas){
        $nama_kelas = $d_kelas['nama_kelas'];

        // Hapus foto fisik siswa
        $stmt_siswa = $conn->prepare("SELECT foto FROM siswa WHERE kelas = ?");
        $stmt_siswa->bind_param("s", $nama_kelas);
        $stmt_siswa->execute();
        $res_siswa = $stmt_siswa->get_result();
        while($s = $res_siswa->fetch_assoc()){
            if(!empty($s['foto']) && !in_array($s['foto'], ['default.jpg', 'default.png'])){
                $path = "img/siswa/" . $s['foto'];
                if(file_exists($path)) unlink($path); 
            }
        }

        // Hapus data siswa & kelas
        $stmt_del_s = $conn->prepare("DELETE FROM siswa WHERE kelas = ?");
        $stmt_del_s->bind_param("s", $nama_kelas);
        $stmt_del_s->execute();

        $stmt_del_k = $conn->prepare("DELETE FROM kelas WHERE id = ?");
        $stmt_del_k->bind_param("i", $id_hapus);
        $stmt_del_k->execute();

        echo "<script>alert('Sukses! Kelas dan data siswa telah dihapus.'); window.location='data_kelas.php';</script>";
    }
}

$query_kelas = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas ASC");

include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Kelas - Vibrant Glass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f0f3f9;
            background-image: radial-gradient(at 0% 0%, rgba(13, 110, 253, 0.08) 0px, transparent 50%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            padding: 12px 15px;
        }

        .form-control:focus {
            background: #fff;
            border-color: #0d6efd;
            box-shadow: none;
        }

        .table thead th {
            background: rgba(13, 110, 253, 0.05);
            color: #0d6efd;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
            padding: 15px;
        }

        .btn-vibrant {
            border-radius: 15px;
            padding: 10px 20px;
            font-weight: 700;
            transition: 0.3s;
        }

        .btn-action {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            transition: 0.2s;
        }

        /* Modal Glass */
        .modal-content {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 25px;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row g-4">
        <div class="col-md-5">
            <div class="glass-card p-4 border-top border-4 border-primary">
                <h5 class="fw-bold mb-4 text-primary"><i class="bi bi-plus-circle-dotted me-2"></i>Tambah Kelas Baru</h5>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">NAMA KELAS</label>
                        <input type="text" name="nama_kelas" class="form-control" placeholder="Misal: 10 TKJ A" required autofocus>
                    </div>
                    <button type="submit" name="tambah" class="btn btn-primary btn-vibrant w-100">
                        <i class="bi bi-cloud-plus me-2"></i>SIMPAN KELAS
                    </button>
                </form>
            </div>
        </div>

        <div class="col-md-7">
            <div class="glass-card p-0 overflow-hidden">
                <div class="p-4 border-bottom bg-white bg-opacity-30">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-building me-2"></i>Daftar Kelas Aktif</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="15%" class="text-center">No</th>
                                <th>Nama Kelas</th>
                                <th width="30%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no=1; while($row = mysqli_fetch_assoc($query_kelas)): ?>
                            <tr class="border-bottom border-white border-opacity-30">
                                <td class="text-center text-muted"><?= $no++ ?></td>
                                <td class="fw-extrabold text-dark"><?= xss($row['nama_kelas']) ?></td>
                                <td class="text-center">
                                    <div class="btn-group gap-2">
                                        <button class="btn btn-light border text-warning btn-action" 
                                                data-bs-toggle="modal" data-bs-target="#modalEdit" 
                                                data-id="<?= $row['id'] ?>" 
                                                data-nama="<?= xss($row['nama_kelas']) ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-light border text-danger btn-action" 
                                                data-bs-toggle="modal" data-bs-target="#modalHapus" 
                                                data-id="<?= $row['id'] ?>" 
                                                data-nama="<?= xss($row['nama_kelas']) ?>">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-primary">Edit Nama Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_kelas" id="edit-id">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">NAMA KELAS BARU</label>
                        <input type="text" name="nama_kelas" id="edit-nama" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light btn-vibrant" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit" class="btn btn-primary btn-vibrant">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Hapus Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <input type="hidden" name="id_kelas_hapus" id="hapus-id">
                    <p class="mb-1 text-muted">Apakah Anda yakin ingin menghapus kelas</p>
                    <h3 id="hapus-nama-display" class="fw-bold text-danger mb-4"></h3>
                    <div class="bg-danger bg-opacity-10 p-3 rounded-4">
                        <p class="small text-danger fw-bold mb-0">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            PERINGATAN: Seluruh data siswa dan file foto di dalam kelas ini akan terhapus secara permanen!
                        </p>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light btn-vibrant" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="proses_hapus" class="btn btn-danger btn-vibrant px-4">Ya, Hapus Masal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Script Modal Edit
    document.getElementById('modalEdit').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('edit-id').value = btn.getAttribute('data-id');
        document.getElementById('edit-nama').value = btn.getAttribute('data-nama');
    });

    // Script Modal Hapus
    document.getElementById('modalHapus').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('hapus-id').value = btn.getAttribute('data-id');
        document.getElementById('hapus-nama-display').innerText = btn.getAttribute('data-nama');
    });
</script>
</body>
</html>