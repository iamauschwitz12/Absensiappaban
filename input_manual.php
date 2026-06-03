<?php
session_start();
include 'koneksi.php';

// --- SECURITY: INISIALISASI CSRF TOKEN & XSS HELPER ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function xss($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Cek Login
if(!isset($_SESSION['login'])){ header("location: login.php"); exit; }

$role = $_SESSION['role'];
$kelas_diampu = $_SESSION['kelas_diampu'] ?? '';
$user_login = $_SESSION['nama'];

// --- 1. PROSES SIMPAN DATA (KEAMANAN TINGKAT DEWA) ---
if(isset($_POST['simpan'])){
    // Validasi CSRF
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Serangan CSRF terdeteksi!");
    }

    $tgl = $_POST['tanggal'];
    $kelas_pilih_post = $_POST['kelas_pilih'];
    $nis_array = $_POST['nis']; 
    $ket_array = $_POST['keterangan']; 

    // Prepare Statements untuk efisiensi & keamanan
    $stmt_cek = $conn->prepare("SELECT id FROM absensi WHERE nis = ? AND DATE(waktu_masuk) = ?");
    $stmt_upd = $conn->prepare("UPDATE absensi SET keterangan = ?, input_by = ? WHERE nis = ? AND DATE(waktu_masuk) = ?");
    $stmt_ins = $conn->prepare("INSERT INTO absensi (nis, waktu_masuk, keterangan, input_by) VALUES (?, ?, ?, ?)");

    foreach($nis_array as $key => $nis){
        $ket_baru = $ket_array[$key]; 
        
        $stmt_cek->bind_param("ss", $nis, $tgl);
        $stmt_cek->execute();
        $res_cek = $stmt_cek->get_result();
        
        if($res_cek->num_rows > 0){
            $log_edit = $user_login . " (Edit)";
            $stmt_upd->bind_param("ssss", $ket_baru, $log_edit, $nis, $tgl);
            $stmt_upd->execute();
        } else {
            // Jika bukan Alpha, maka insert (Alpha dianggap sebagai 'belum absen' secara default di laporan)
            if($ket_baru != 'Alpha'){
                $waktu_lengkap = $tgl . " 07:00:00"; 
                $stmt_ins->bind_param("ssss", $nis, $waktu_lengkap, $ket_baru, $user_login);
                $stmt_ins->execute();
            }
        }
    }
    echo "<script>alert('Data absensi berhasil diperbarui!'); window.location='input_manual.php?tanggal=$tgl&kelas=$kelas_pilih_post';</script>";
}

// --- 2. LOGIKA FILTER ---
$tgl_pilih = $_GET['tanggal'] ?? date('Y-m-d');
$kelas_pilih = ($role == 'walikelas') ? $kelas_diampu : ($_GET['kelas'] ?? '');

// Ambil Daftar Kelas (Prepared)
$q_kelas = mysqli_query($conn, "SELECT nama_kelas FROM kelas ORDER BY nama_kelas ASC");

// --- 3. AMBIL DATA SISWA (PREPARED STATEMENT) ---
$data_siswa = [];
if($kelas_pilih != ''){
    $sql_siswa = "SELECT s.nis, s.nama, a.keterangan 
                  FROM siswa s
                  LEFT JOIN absensi a ON s.nis = a.nis AND DATE(a.waktu_masuk) = ?
                  WHERE s.kelas = ? 
                  ORDER BY s.nama ASC";
    $stmt_siswa = $conn->prepare($sql_siswa);
    $stmt_siswa->bind_param("ss", $tgl_pilih, $kelas_pilih);
    $stmt_siswa->execute();
    $res_siswa = $stmt_siswa->get_result();
    while($row = $res_siswa->fetch_assoc()){
        $data_siswa[] = $row;
    }
}

include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Manual - Vibrant Glass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f0f3f9;
            background-image: radial-gradient(at 0% 0%, rgba(13, 110, 253, 0.05) 0px, transparent 50%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }

        /* Status Pills */
        .status-pill { padding: 4px 12px; border-radius: 50px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .pill-hadir { background: #dcfce7; color: #166534; }
        .pill-sakit { background: #fef9c3; color: #854d0e; }
        .pill-izin { background: #e0f2fe; color: #075985; }
        .pill-alpha { background: #fee2e2; color: #991b1b; }
        .pill-bolos { background: #450a0a; color: #ffffff; }

        /* Custom Radio Buttons */
        .btn-check:checked + .btn-outline-success { background: #10b981; color: white; border-color: #10b981; }
        .btn-check:checked + .btn-outline-warning { background: #f59e0b; color: white; border-color: #f59e0b; }
        .btn-check:checked + .btn-outline-info { background: #0ea5e9; color: white; border-color: #0ea5e9; }
        .btn-check:checked + .btn-outline-danger { background: #ef4444; color: white; border-color: #ef4444; }
        .btn-check:checked + .btn-outline-dark { background: #7f1d1d; color: white; border-color: #7f1d1d; }

        .form-select, .form-control {
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.05);
            background: rgba(255,255,255,0.8);
        }

        .table thead th { 
            background: rgba(13, 110, 253, 0.05); 
            color: #0d6efd; 
            font-size: 0.75rem; 
            letter-spacing: 1px; 
            border: none;
            padding: 15px;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="glass-card p-4 mb-4 border-start border-4 border-primary">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="fw-bold text-dark mb-1">Input Absensi Manual</h4>
                <p class="text-muted mb-0 small">Kelola data kehadiran, izin, sakit, atau <span class="text-danger fw-bold">Bolos</span> siswa.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <h5 class="text-primary fw-bold mb-0" id="live-clock">00:00:00</h5>
                <small class="fw-bold opacity-50"><?= xss(date('l, d F Y')) ?></small>
            </div>
        </div>
    </div>

    <div class="glass-card p-4 mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted mb-2">TANGGAL</label>
                <input type="date" name="tanggal" class="form-control" value="<?= xss($tgl_pilih) ?>" required>
            </div>
            
            <?php if($role == 'admin' || $role == 'piket'): ?>
            <div class="col-md-4">
                <label class="small fw-bold text-muted mb-2">PILIH KELAS</label>
                <select name="kelas" class="form-select">
                    <option value="">-- Pilih Kelas --</option>
                    <?php while($k = mysqli_fetch_assoc($q_kelas)): ?>
                        <option value="<?= xss($k['nama_kelas']) ?>" <?= $kelas_pilih == $k['nama_kelas'] ? 'selected' : '' ?>><?= xss($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">
                    <i class="bi bi-search me-2"></i>CARI
                </button>
            </div>
        </form>
    </div>

    <?php if($kelas_pilih != ''): ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="tanggal" value="<?= xss($tgl_pilih) ?>">
        <input type="hidden" name="kelas_pilih" value="<?= xss($kelas_pilih) ?>">
        
        <div class="glass-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">No</th>
                            <th>Nama Siswa</th>
                            <th class="text-center">Status Sekarang</th>
                            <th class="text-center">Ubah Ke</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($data_siswa) > 0): ?>
                            <?php foreach($data_siswa as $idx => $s): 
                                $ket = $s['keterangan'] ? $s['keterangan'] : 'Alpha';
                                $pill_class = "pill-" . strtolower($ket);
                            ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= $idx + 1 ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= xss($s['nama']) ?></div>
                                    <code class="small text-muted"><?= xss($s['nis']) ?></code>
                                    <input type="hidden" name="nis[]" value="<?= xss($s['nis']) ?>">
                                </td>
                                <td class="text-center">
                                    <span class="status-pill <?= $pill_class ?>"><?= $ket ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm w-100" role="group">
                                        <input type="radio" class="btn-check" name="keterangan[<?= $idx ?>]" id="H<?= $idx ?>" value="Hadir" <?= $ket=='Hadir'?'checked':'' ?>>
                                        <label class="btn btn-outline-success" for="H<?= $idx ?>">Hadir</label>

                                        <input type="radio" class="btn-check" name="keterangan[<?= $idx ?>]" id="S<?= $idx ?>" value="Sakit" <?= $ket=='Sakit'?'checked':'' ?>>
                                        <label class="btn btn-outline-warning" for="S<?= $idx ?>">Sakit</label>

                                        <input type="radio" class="btn-check" name="keterangan[<?= $idx ?>]" id="I<?= $idx ?>" value="Izin" <?= $ket=='Izin'?'checked':'' ?>>
                                        <label class="btn btn-outline-info" for="I<?= $idx ?>">Izin</label>

                                        <input type="radio" class="btn-check" name="keterangan[<?= $idx ?>]" id="B<?= $idx ?>" value="Bolos" <?= $ket=='Bolos'?'checked':'' ?>>
                                        <label class="btn btn-outline-dark" for="B<?= $idx ?>">Bolos</label>

                                        <input type="radio" class="btn-check" name="keterangan[<?= $idx ?>]" id="A<?= $idx ?>" value="Alpha" <?= $ket=='Alpha'?'checked':'' ?>>
                                        <label class="btn btn-outline-danger" for="A<?= $idx ?>">Alpha</label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted small">Data tidak ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if(count($data_siswa) > 0): ?>
            <div class="sticky-bottom bg-white bg-opacity-80 backdrop-blur p-3 mt-4 rounded-4 shadow-lg border border-white">
                <button type="submit" name="simpan" class="btn btn-primary w-100 py-3 rounded-pill fw-bold" onclick="return confirm('Simpan perubahan data absensi?')">
                    <i class="bi bi-cloud-arrow-up me-2"></i> SIMPAN PERUBAHAN DATA
                </button>
            </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>

</div>

<script>
    function updateClock() {
        const now = new Date();
        const timeString = now.getHours().toString().padStart(2, '0') + ':' + 
                           now.getMinutes().toString().padStart(2, '0') + ':' + 
                           now.getSeconds().toString().padStart(2, '0');
        document.getElementById('live-clock').textContent = timeString;
    }
    setInterval(updateClock, 1000); updateClock();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>