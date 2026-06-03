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

// PROTEKSI HALAMAN: Hanya ADMIN yang boleh masuk
if(!isset($_SESSION['login']) || $_SESSION['role'] != 'admin'){
    header("location: dashboard.php");
    exit;
}

// Ambil Nama Sekolah
$q_sch = mysqli_query($conn, "SELECT nama_sekolah FROM pengaturan WHERE id=1");
$nama_sekolah = mysqli_fetch_assoc($q_sch)['nama_sekolah'] ?? 'Sistem Absensi';

// Ambil Daftar Kelas untuk Dropdown
$daftar_kelas = [];
$res_kelas = mysqli_query($conn, "SELECT nama_kelas FROM kelas ORDER BY nama_kelas ASC");
while($k = mysqli_fetch_assoc($res_kelas)) $daftar_kelas[] = $k['nama_kelas'];

// Tentukan mode (tambah/edit)
$mode = isset($_GET['aksi']) && $_GET['aksi'] == 'edit' && isset($_GET['id']) ? 'edit' : 'tambah';
$data_edit = null;

if ($mode == 'edit') {
    $id_edit = (int)$_GET['id'];
    $stmt_edit = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_edit->bind_param("i", $id_edit);
    $stmt_edit->execute();
    $data_edit = $stmt_edit->get_result()->fetch_assoc();
    if (!$data_edit) { header("location: data_user.php"); exit; }
}

// --- SECURITY: LOGIKA HAPUS (PREPARED & CSRF CHECK) ---
if(isset($_GET['aksi']) && $_GET['aksi'] == 'hapus' && isset($_GET['id'])){
    if(!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']){
        die("Akses Ilegal (CSRF Terdeteksi)!");
    }

    $id_hapus = (int)$_GET['id'];
    
    // Cegah hapus admin terakhir
    $stmt_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $id_hapus);
    $stmt_check->execute();
    $r_check = $stmt_check->get_result()->fetch_assoc();

    if($r_check['role'] == 'admin'){
        $res_admin = mysqli_query($conn, "SELECT id FROM users WHERE role='admin'");
        if(mysqli_num_rows($res_admin) <= 1){
            echo "<script>alert('Gagal! Admin terakhir tidak boleh dihapus.'); window.location='data_user.php';</script>";
            exit;
        }
    }

    $stmt_del = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt_del->bind_param("i", $id_hapus);
    if($stmt_del->execute()){
        echo "<script>alert('User berhasil dihapus.'); window.location='data_user.php';</script>";
    }
    exit;
}

// --- SECURITY: LOGIKA SIMPAN (PREPARED & CSRF CHECK) ---
if(isset($_POST['simpan'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Token Keamanan Kadaluarsa!");
    }

    $id = isset($_POST['id_user']) ? (int)$_POST['id_user'] : null;
    $username = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $role = $_POST['role'];
    $kelas_diampu = ($role == 'walikelas') ? $_POST['kelas_diampu'] : null;
    $password_baru = $_POST['password'];

    if(empty($username) || empty($role)){
        echo "<script>alert('Username dan Role wajib diisi!'); window.location='data_user.php';</script>";
        exit;
    }

    if ($id) {
        // --- PROSES EDIT ---
        $stmt_cek = $conn->prepare("SELECT id FROM users WHERE username=? AND id != ?");
        $stmt_cek->bind_param("si", $username, $id);
        $stmt_cek->execute();
        if($stmt_cek->get_result()->num_rows > 0){
             echo "<script>alert('Gagal! Username sudah dipakai.');</script>";
        } else {
            if(!empty($password_baru)){
                $pass_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $stmt_upd = $conn->prepare("UPDATE users SET username=?, nama_lengkap=?, role=?, kelas_diampu=?, password=? WHERE id=?");
                $stmt_upd->bind_param("sssssi", $username, $nama_lengkap, $role, $kelas_diampu, $pass_hash, $id);
            } else {
                $stmt_upd = $conn->prepare("UPDATE users SET username=?, nama_lengkap=?, role=?, kelas_diampu=? WHERE id=?");
                $stmt_upd->bind_param("ssssi", $username, $nama_lengkap, $role, $kelas_diampu, $id);
            }
            if($stmt_upd->execute()) echo "<script>alert('User diupdate!'); window.location='data_user.php';</script>";
        }
    } else {
        // --- PROSES TAMBAH ---
        if(empty($password_baru)){ echo "<script>alert('Password wajib diisi!');</script>"; }
        else {
            $stmt_cek = $conn->prepare("SELECT id FROM users WHERE username=?");
            $stmt_cek->bind_param("s", $username);
            $stmt_cek->execute();
            if($stmt_cek->get_result()->num_rows > 0){
                echo "<script>alert('Username sudah ada!');</script>";
            } else {
                $pass_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $stmt_ins = $conn->prepare("INSERT INTO users (username, password, role, kelas_diampu, nama_lengkap) VALUES (?, ?, ?, ?, ?)");
                $stmt_ins->bind_param("sssss", $username, $pass_hash, $role, $kelas_diampu, $nama_lengkap);
                if($stmt_ins->execute()) echo "<script>alert('User ditambahkan!'); window.location='data_user.php';</script>";
            }
        }
    }
}

include 'header.php'; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen User - <?= xss($nama_sekolah) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { 
            background: #f0f3f9; 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-image: radial-gradient(at 0% 0%, rgba(13, 110, 253, 0.05) 0px, transparent 50%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(15px);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }
        .table thead th { 
            background: rgba(13, 110, 253, 0.05); 
            color: #0d6efd; border: none; padding: 15px;
            font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;
        }
        .form-control, .form-select {
            border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); padding: 10px 15px;
        }
        .btn-vibrant { border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-vibrant:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .badge-role { font-size: 0.7rem; font-weight: 800; padding: 5px 12px; border-radius: 8px; }
    </style>

    <script>
        function toggleKelas() {
            const role = document.getElementById('role').value;
            document.getElementById('kelas_div').style.display = (role === 'walikelas') ? 'block' : 'none';
        }
    </script>
</head>
<body>

<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="glass-card p-4 border-top border-4 border-primary">
                <h5 class="fw-800 text-primary mb-4">
                    <i class="bi bi-person-lock me-2"></i><?= $mode == 'edit' ? 'Edit User' : 'Tambah User' ?>
                </h5>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <?php if ($mode == 'edit'): ?>
                        <input type="hidden" name="id_user" value="<?= $data_edit['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">USERNAME</label>
                        <input type="text" name="username" class="form-control" value="<?= xss($data_edit['username'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">NAMA LENGKAP</label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= xss($data_edit['nama_lengkap'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">PASSWORD</label>
                        <input type="password" name="password" class="form-control" placeholder="<?= $mode == 'edit' ? 'Kosongkan jika tidak diubah' : 'Minimal 6 karakter' ?>" <?= $mode == 'tambah' ? 'required' : '' ?>>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">HAK AKSES (ROLE)</label>
                        <select id="role" name="role" class="form-select" onchange="toggleKelas()" required>
                            <option value="" disabled selected>-- Pilih Role --</option>
                            <option value="admin" <?= ($data_edit['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Administrator</option>
                            <option value="walikelas" <?= ($data_edit['role'] ?? '') == 'walikelas' ? 'selected' : '' ?>>Wali Kelas</option>
                            <option value="piket" <?= ($data_edit['role'] ?? '') == 'piket' ? 'selected' : '' ?>>Guru Piket</option>
                        </select>
                    </div>
                    
                    <div class="mb-4" id="kelas_div" style="display: <?= ($data_edit['role'] ?? '') == 'walikelas' ? 'block' : 'none' ?>;">
                        <label class="small fw-bold text-primary">KELAS YANG DIAMPU</label>
                        <select name="kelas_diampu" class="form-select">
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach($daftar_kelas as $k): ?>
                                <option value="<?= $k ?>" <?= ($data_edit['kelas_diampu'] ?? '') == $k ? 'selected' : '' ?>><?= $k ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" name="simpan" class="btn btn-primary w-100 btn-vibrant py-2">
                        <i class="bi bi-shield-check me-2"></i><?= strtoupper($mode) ?> DATA USER
                    </button>
                    <?php if($mode == 'edit'): ?>
                        <a href="data_user.php" class="btn btn-light w-100 btn-vibrant mt-2">BATAL</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-800 m-0"><i class="bi bi-people me-2 text-primary"></i>Daftar Pengguna Sistem</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr class="text-center">
                                <th width="5%">#</th>
                                <th class="text-start">USER / NAMA</th>
                                <th>ROLE</th>
                                <th>AKSES KELAS</th>
                                <th>OPSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $q_tampil = mysqli_query($conn, "SELECT * FROM users ORDER BY role ASC, username ASC");
                            while($user = mysqli_fetch_assoc($q_tampil)): 
                                $b_color = 'bg-secondary';
                                if($user['role'] == 'admin') $b_color = 'bg-danger';
                                elseif($user['role'] == 'walikelas') $b_color = 'bg-primary';
                                elseif($user['role'] == 'piket') $b_color = 'bg-info text-dark';
                            ?>
                            <tr class="border-bottom border-white">
                                <td class="text-center small text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-800 text-dark" style="font-size: 0.9rem;"><?= xss($user['username']) ?></div>
                                    <div class="small text-muted"><?= xss($user['nama_lengkap'] ?: '-') ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge-role <?= $b_color ?>"><?= strtoupper($user['role']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if($user['kelas_diampu']): ?>
                                        <span class="badge bg-white text-primary border border-primary border-opacity-25 rounded-pill px-3"><?= $user['kelas_diampu'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group gap-1">
                                        <a href="data_user.php?aksi=edit&id=<?= $user['id'] ?>" class="btn btn-sm btn-light border text-primary rounded-3"><i class="bi bi-pencil-square"></i></a>
                                        <a href="data_user.php?aksi=hapus&id=<?= $user['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-light border text-danger rounded-3" onclick="return confirm('Hapus user <?= $user['username'] ?>?')"><i class="bi bi-trash3"></i></a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>