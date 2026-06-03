<?php
session_start();
include 'koneksi.php';

// --- SECURITY: INISIALISASI CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fungsi Keamanan XSS
function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// 1. Cek Login
if(!isset($_SESSION['login'])){ 
    header("location: login.php"); 
    exit; 
}

$role = $_SESSION['role'];
$kelas_diampu = $_SESSION['kelas_diampu'] ?? '';

// --- SECURITY: LOGIKA HAPUS DATA ---
if(isset($_GET['hapus_id']) && isset($_GET['token'])){
    if($_GET['token'] !== $_SESSION['csrf_token']){
        die("Terdeteksi upaya ilegal (CSRF)!");
    }

    $id_hapus = (int)$_GET['hapus_id'];
    
    $stmt_foto = $conn->prepare("SELECT foto FROM siswa WHERE id = ?");
    $stmt_foto->bind_param("i", $id_hapus);
    $stmt_foto->execute();
    $data_lama = $stmt_foto->get_result()->fetch_assoc();

    if($data_lama && !empty($data_lama['foto'])){
        $path_foto = "img/siswa/" . $data_lama['foto'];
        if(file_exists($path_foto)) unlink($path_foto);
    }

    $stmt_del = $conn->prepare("DELETE FROM siswa WHERE id = ?");
    $stmt_del->bind_param("i", $id_hapus);
    
    if($stmt_del->execute()){
        echo "<script>alert('Data berhasil dihapus!'); window.location='data_siswa.php';</script>";
    }
    exit;
}

// Ambil Nama Sekolah & Timezone
$query_set = mysqli_query($conn, "SELECT nama_sekolah FROM pengaturan WHERE id=1");
$set_sch = mysqli_fetch_assoc($query_set);
$nama_sekolah = $set_sch['nama_sekolah'] ?? 'Sistem Absensi';

$querySetting = mysqli_query($conn, "SELECT timezone FROM pengaturan WHERE id=1");
$sett = mysqli_fetch_assoc($querySetting);
$timezone_aktif = $sett['timezone'] ?? 'Asia/Jakarta';

// --- LOGIKA TANGGAL INDONESIA ---
$daftar_hari = array('Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu');
$daftar_bulan = array('January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember');
$tgl_indo = $daftar_hari[date('l')] . ', ' . date('d ') . $daftar_bulan[date('F')] . date(' Y');

// --- LOGIKA PAGINATION ---
$limit = 40; 
$halaman = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($halaman - 1) * $limit;

// --- PENCARIAN & FILTER ---
$keyword = $_GET['q'] ?? '';
$kelas_filter = $_GET['kelas'] ?? '';
$where = ($role == 'walikelas') ? "WHERE kelas = ?" : "WHERE 1=1";
$params = [];
$types = "";

if($role == 'walikelas') { $params[] = $kelas_diampu; $types .= "s"; }
if(!empty($keyword)) {
    $where .= " AND (nama LIKE ? OR nis LIKE ?)";
    $search_key = "%$keyword%"; $params[] = $search_key; $params[] = $search_key; $types .= "ss";
}
if(!empty($kelas_filter)) { $where .= " AND kelas = ?"; $params[] = $kelas_filter; $types .= "s"; }

$stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM siswa $where");
if($types) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_data = $stmt_count->get_result()->fetch_assoc()['total'];
$total_halaman = ceil($total_data / $limit);

$final_query = "SELECT * FROM siswa $where ORDER BY nama ASC LIMIT ?, ?";
$params[] = $offset; $params[] = $limit; $types .= "ii";
$stmt_main = $conn->prepare($final_query);
$stmt_main->bind_param($types, ...$params);
$stmt_main->execute();
$data_siswa = $stmt_main->get_result();

include 'header.php'; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Database Siswa - <?= xss($nama_sekolah) ?></title>
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
        }

        .btn-action {
            border-radius: 18px;
            font-weight: 700;
            transition: 0.3s;
            border: 1px solid rgba(255,255,255,0.4);
            backdrop-filter: blur(5px);
        }

        .btn-action:hover {
            transform: translateY(-3px);
            background-color: rgba(255,255,255,0.9);
        }

        #formTambah { display: none; }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 12px;
        }

        .photo-circle { 
            width: 48px; height: 48px; 
            border-radius: 12px; 
            background: #fff; 
            overflow: hidden; 
            border: 1px solid #e2e8f0;
        }
        .photo-circle img { width: 100%; height: 100%; object-fit: cover; }

        .table thead th { 
            background: rgba(13, 110, 253, 0.05); 
            color: #0d6efd;
            font-size: 0.75rem;
            text-transform: uppercase;
            border: none;
            padding: 15px;
        }

        #live-clock {
            background: rgba(255, 255, 255, 0.5);
            padding: 5px 15px;
            border-radius: 10px;
            font-weight: 800;
        }

        /* Dropdown Styling */
        .dropdown-menu { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .dropdown-item { font-weight: 600; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="glass-card p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h3 class="fw-bold mb-1 text-primary">Manajemen Data Siswa</h3>
                <p class="text-muted mb-0 small"><?= $tgl_indo ?> | <span id="live-clock">--:--:--</span></p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <button onclick="toggleForm()" class="btn btn-primary btn-action px-4 py-2">
                    <i class="bi bi-person-plus-fill me-2"></i> Tambah Siswa
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="import_siswa.php" class="btn btn-success w-100 btn-action py-3 bg-opacity-75">
                <div class="small opacity-75">Data Masal</div>
                <i class="bi bi-file-earmark-excel me-1"></i> Import Siswa
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="bulk_update_sesi.php" class="btn btn-warning w-100 btn-action py-3 text-white bg-opacity-75">
                <div class="small opacity-75">Pengaturan</div>
                <i class="bi bi-clock-history me-1"></i> Atur Sesi
            </a>
        </div>
        <div class="col-6 col-md-3">
            <button data-bs-toggle="modal" data-bs-target="#modalCetak" class="btn btn-info w-100 btn-action py-3 text-white bg-opacity-75">
                <div class="small opacity-75">Kartu ID</div>
                <i class="bi bi-printer me-1"></i> Cetak Kartu
            </button>
        </div>
        
        <!-- <div class="col-6 col-md-3">
            <a href="data_kelas.php" class="btn btn-secondary w-100 btn-action py-3 bg-opacity-75">
                <div class="small opacity-75">Manajemen</div>
                <i class="bi bi-building me-1"></i> Data Kelas
            </a>
        </div> -->

        <div class="col-6 col-md-3">
            <a href="export_siswa_excel.php?kelas=<?= xss($kelas_filter) ?>&q=<?= xss($keyword) ?>" class="btn btn-primary w-100 btn-action py-3 bg-opacity-75" style="background-color: #1d6f42 !important;">
                <div class="small opacity-75">Download</div>
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Data Siswa
            </a>
        </div>
    </div>

    <div id="formTambah" class="glass-card p-4 mb-4 border-primary border-top border-4 border-opacity-25">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold m-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Input Siswa Baru</h5>
            <button onclick="toggleForm()" class="btn-close"></button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="proses_tambah_siswa.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="row g-3">
                <div class="col-md-2 text-center">
                    <div class="mx-auto rounded-circle bg-light border d-flex align-items-center justify-content-center" style="width: 110px; height: 110px; overflow: hidden;">
                        <img id="img-preview" src="" style="display:none; width: 100%; height: 100%; object-fit: cover;">
                        <i id="icon-placeholder" class="bi bi-person-bounding-box fs-1 text-muted"></i>
                    </div>
                    <label for="foto-input" class="btn btn-sm btn-outline-primary mt-3 rounded-pill">Upload Foto</label>
                    <input type="file" name="foto" id="foto-input" class="d-none" accept="image/*" onchange="previewImage(event)">
                </div>
                
                <div class="col-md-10">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="small fw-bold">NIS / NIP</label>
                            <input type="text" name="nis" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">RFID UID</label>
                            <input type="text" name="rfid_uid" class="form-control" placeholder="Tempel kartu..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="small fw-bold">Kelas</label>
                            <select name="kelas" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php 
                                $q_k = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas ASC");
                                while($k = mysqli_fetch_assoc($q_k)) echo "<option value='".xss($k['nama_kelas'])."'>".xss($k['nama_kelas'])."</option>";
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small fw-bold">Sesi</label>
                            <select name="sesi" class="form-select">
                                <option value="1">Sesi 1</option>
                                <option value="2">Sesi 2</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">WA Ortu</label>
                            <input type="text" name="hp" class="form-control" placeholder="628..." required>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">ID Telegram</label>
                            <input type="text" name="telegram_id" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="small fw-bold">Alamat Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="tambah" class="btn btn-primary w-100 fw-bold py-2 rounded-3">SIMPAN DATA</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="glass-card p-4">
        <div class="row g-3 mb-4 align-items-center">
            <div class="col-md-6">
                <h5 class="fw-bold m-0 text-dark">Daftar Siswa <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill ms-2"><?= $total_data ?></span></h5>
            </div>
            <div class="col-md-6">
                <form method="GET" class="d-flex gap-2">
                    <select name="kelas" class="form-select form-select-sm w-auto border-0 shadow-sm">
                        <option value="">Semua Kelas</option>
                        <?php 
                        $q_k3 = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas ASC");
                        while($k3 = mysqli_fetch_assoc($q_k3)): ?>
                            <option value="<?= xss($k3['nama_kelas']) ?>" <?= ($kelas_filter == $k3['nama_kelas']) ? 'selected' : '' ?>><?= xss($k3['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="input-group input-group-sm shadow-sm">
                        <input type="text" name="q" class="form-control border-0" placeholder="Cari..." value="<?= xss($keyword) ?>">
                        <button class="btn btn-white bg-white border-0" type="submit"><i class="bi bi-search text-primary"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th class="text-center" width="5%">FOTO</th>
                        <th>NAMA & IDENTITAS</th>
                        <th class="text-center">KELAS</th>
                        <th class="text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($data_siswa->num_rows > 0): ?>
                        <?php while($row = $data_siswa->fetch_assoc()): ?>
                        <tr class="border-bottom border-white border-opacity-50">
                            <td class="text-center">
                                <div class="photo-circle mx-auto">
                                    <?php $foto = "img/siswa/" . $row['foto']; if (!empty($row['foto']) && file_exists($foto)): ?>
                                        <img src="<?= $foto ?>">
                                    <?php else: ?><i class="bi bi-person text-muted fs-4"></i><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= xss($row['nama']) ?></div>
                                <code class="small text-muted"><?= xss($row['nis']) ?></code>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-white text-primary border border-primary border-opacity-25 rounded-pill px-3"><?= xss($row['kelas']) ?></span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group gap-1">
                                    

                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-light border text-dark rounded-3" data-bs-toggle="dropdown" title="Cetak Kartu">
                                            <i class="bi bi-printer"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow">
                                            <li><a class="dropdown-item" href="cetak_kartu.php?id=<?= $row['id'] ?>&tipe=siswa" target="_blank"><i class="bi bi-person-vcard text-primary me-2"></i>Kartu Siswa</a></li>
                                        </ul>
                                    </div>

                                    <a href="edit_siswa.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light border text-primary rounded-3" title="Edit Data">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <a href="data_siswa.php?hapus_id=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-light border text-danger rounded-3" onclick="return confirm('Hapus permanen?')" title="Hapus Data">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Data tidak ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_halaman > 1): ?>
        <nav class="mt-4"><ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?= ($halaman <= 1) ? 'disabled' : '' ?>"><a class="page-link border-0 shadow-sm mx-1 rounded-circle" href="?p=<?= $halaman - 1 ?>&q=<?= xss($keyword) ?>&kelas=<?= xss($kelas_filter) ?>"><i class="bi bi-chevron-left"></i></a></li>
            <li class="page-item active"><span class="page-link border-0 shadow-sm mx-1 rounded-circle px-3"><?= $halaman ?></span></li>
            <li class="page-item <?= ($halaman >= $total_halaman) ? 'disabled' : '' ?>"><a class="page-link border-0 shadow-sm mx-1 rounded-circle" href="?p=<?= $halaman + 1 ?>&q=<?= xss($keyword) ?>&kelas=<?= xss($kelas_filter) ?>"><i class="bi bi-chevron-right"></i></a></li>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalCetak" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card p-2 border-0">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-primary">Cetak Kartu Absensi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="cetak_kelas.php" method="GET" target="_blank">
          <div class="modal-body">
              <label class="small fw-bold mb-2">Pilih Kelas</label>
              <select name="kelas" class="form-select py-3 border-0 bg-light rounded-4" required>
                  <option value="">-- Pilih Kelas --</option>
                  <?php 
                  $q_m = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas ASC");
                  while($m = mysqli_fetch_assoc($q_m)) echo "<option value='".xss($m['nama_kelas'])."'>".xss($m['nama_kelas'])."</option>";
                  ?>
              </select>
          </div>
          <!-- <div class="modal-footer border-0">
            <button type="submit" class="btn btn-primary w-100 btn-action py-3">MULAI CETAK</button>
          </div> -->
          <div class="modal-footer border-0 flex-column">
            <button type="submit" class="btn btn-primary w-100 btn-action py-3 mb-2">
                <i class="bi bi-printer me-2"></i>PRINT PDF / CETAK
            </button>
            
            <button type="button" onclick="downloadZipMode()" class="btn btn-success w-100 btn-action py-3">
                <i class="bi bi-file-earmark-zip me-2"></i>DOWNLOAD GAMBAR (.ZIP)
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleForm() {
        const form = document.getElementById('formTambah');
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            form.style.display = 'none';
        }
    }

    function updateClock() {
        const options = { timeZone: '<?= $timezone_aktif ?>', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
        document.getElementById('live-clock').textContent = new Intl.DateTimeFormat('id-ID', options).format(new Date());
    }
    setInterval(updateClock, 1000); updateClock();

    function previewImage(event) {
        const reader = new FileReader();
        reader.onload = function(){
            const output = document.getElementById('img-preview');
            output.src = reader.result;
            output.style.display = 'block';
            document.getElementById('icon-placeholder').style.display = 'none';
        };
        reader.readAsDataURL(event.target.files[0]);
    }

    function downloadZipMode() {
        // Tambahkan '#modalCetak' sebelum selector select agar lebih spesifik
        const selectKelas = document.querySelector('#modalCetak select[name="kelas"]');
        const kelas = selectKelas.value;

        if (!kelas || kelas === "") {
            alert("Pilih kelas terlebih dahulu di dalam modal!");
            return;
        }

        // Arahkan ke halaman generator
        window.location.href = `generate_zip_kartu.php?kelas=${encodeURIComponent(kelas)}`;
    }
</script>
</body>
</html>