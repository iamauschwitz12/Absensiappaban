<?php
session_start();
include 'koneksi.php';

// --- SECURITY: INISIALISASI CSRF TOKEN ---
// CSRF token digunakan untuk mencegah request hapus/aksi sensitif yang dibuat oleh pihak lain.
// Token disimpan pada session, lalu diverifikasi ketika halaman menerima parameter aksi tertentu.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fungsi Keamanan XSS
// Dipakai untuk meng-escape data dari database sebelum dirender ke HTML.
function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// 1. Cek Login
// Jika user belum login, redirect ke halaman login.
if(!isset($_SESSION['login'])){ 
    header("location: login.php"); 
    exit; 
}

// role disiapkan untuk kebutuhan otorisasi fitur (meski pada file ini tidak selalu digunakan).
$role = $_SESSION['role'];

// --- SECURITY: LOGIKA HAPUS DATA ---
// Hapus data guru dilakukan melalui parameter GET (hapus_id) dan validasi CSRF token.
// Jika token tidak valid, proses dihentikan untuk mencegah CSRF.
if(isset($_GET['hapus_id']) && isset($_GET['token'])){
    if($_GET['token'] !== $_SESSION['csrf_token']){
        die("Terdeteksi upaya ilegal (CSRF)!" );
    }

    // Pastikan id yang diterima adalah integer.
    $id_hapus = (int)$_GET['hapus_id'];
    
    // Ambil nama file foto guru sebelum dihapus (untuk membersihkan file fisik di server).
    $stmt_foto = $conn->prepare("SELECT foto FROM guru WHERE id = ?");
    $stmt_foto->bind_param("i", $id_hapus);
    $stmt_foto->execute();
    $data_lama = $stmt_foto->get_result()->fetch_assoc();

    // Jika file foto ada dan nilainya tidak kosong, hapus file dari folder img/guru/.
    if($data_lama && !empty($data_lama['foto'])){
        $path_foto = "img/guru/" . $data_lama['foto'];
        if(file_exists($path_foto)) unlink($path_foto);
    }

    // Hapus record guru dari database.
    $stmt_del = $conn->prepare("DELETE FROM guru WHERE id = ?");
    $stmt_del->bind_param("i", $id_hapus);
    
    // Jika sukses, tampilkan alert dan kembali ke halaman ini.
    if($stmt_del->execute()){
        echo "<script>alert('Data guru berhasil dihapus!'); window.location='data_guru.php';</script>";
    }

    // Hentikan eksekusi karena aksi hapus sudah ditangani.
    exit;
}

// Ambil Nama Sekolah & Timezone
// Informasi konfigurasi aplikasi diambil dari tabel `pengaturan`.
$query_set = mysqli_query($conn, "SELECT nama_sekolah FROM pengaturan WHERE id=1");
$set_sch = mysqli_fetch_assoc($query_set);
$nama_sekolah = $set_sch['nama_sekolah'] ?? 'Sistem Absensi';

$querySetting = mysqli_query($conn, "SELECT timezone FROM pengaturan WHERE id=1");
$sett = mysqli_fetch_assoc($querySetting);
$timezone_aktif = $sett['timezone'] ?? 'Asia/Jakarta';

// --- LOGIKA TANGGAL INDONESIA ---
// Membuat format tanggal berbahasa Indonesia untuk ditampilkan di header.
$daftar_hari = array('Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu');
$daftar_bulan = array('January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember');
$tgl_indo = $daftar_hari[date('l')] . ', ' . date('d ') . $daftar_bulan[date('F')] . date(' Y');

// --- LOGIKA PAGINATION ---
// Membagi data menjadi beberapa halaman untuk performa dan kenyamanan tampilan.
$limit = 40; 
$halaman = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($halaman - 1) * $limit;

// --- PENCARIAN & FILTER ---
// keyword (q) dipakai untuk pencarian berdasarkan nama/nip/jabatan.
// mapel_filter (mapel) dipakai untuk memfilter mata pelajaran.
$keyword = $_GET['q'] ?? '';
$mapel_filter = $_GET['mapel'] ?? '';
$where = "WHERE 1=1";
$params = [];
$types = "";

if(!empty($keyword)) {
    $where .= " AND (nama LIKE ? OR nip LIKE ? OR jabatan LIKE ?)";
    // search_key dipakai bersama untuk tiga kolom sekaligus (nama/nip/jabatan).
    $search_key = "%$keyword%"; 
    $params[] = $search_key; 
    $params[] = $search_key; 
    $params[] = $search_key; 
    $types .= "sss";
}
if(!empty($mapel_filter)) { 
    $where .= " AND mata_pelajaran = ?"; 
    $params[] = $mapel_filter; 
    $types .= "s"; 
}

// Hitung total data untuk menentukan jumlah halaman.
$stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM guru $where");
if($types) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_data = $stmt_count->get_result()->fetch_assoc()['total'];
$total_halaman = ceil($total_data / $limit);

// Query utama mengambil data sesuai filter + pagination.
$final_query = "SELECT * FROM guru $where ORDER BY nama ASC LIMIT ?, ?";
$params[] = $offset; 
$params[] = $limit; 
$types .= "ii";
$stmt_main = $conn->prepare($final_query);
$stmt_main->bind_param($types, ...$params);
$stmt_main->execute();
$data_guru = $stmt_main->get_result();

// Ambil daftar mata pelajaran unik untuk filter dropdown pada bagian pencarian.
$q_mapel_list = mysqli_query($conn, "SELECT DISTINCT mata_pelajaran FROM guru WHERE mata_pelajaran IS NOT NULL AND mata_pelajaran != '' ORDER BY mata_pelajaran ASC");


include 'header.php'; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Manajemen data guru <?= xss($nama_sekolah) ?>">
    <title>Database Guru - <?= xss($nama_sekolah) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f0f3f9;
            background-image: radial-gradient(at 0% 0%, rgba(220, 38, 127, 0.06) 0px, transparent 50%),
                              radial-gradient(at 100% 100%, rgba(124, 58, 237, 0.05) 0px, transparent 50%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.35);
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
            display: flex; align-items: center; justify-content: center;
        }
        .photo-circle img { width: 100%; height: 100%; object-fit: cover; }

        .table thead th { 
            background: rgba(124, 58, 237, 0.06); 
            color: #7c3aed;
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

        .badge-mapel {
            background: rgba(124, 58, 237, 0.1);
            color: #7c3aed;
            border: 1px solid rgba(124, 58, 237, 0.2);
        }

        .text-guru { color: #7c3aed !important; }
        .btn-guru { background: linear-gradient(135deg, #7c3aed, #a855f7); border: none; color: #fff; }
        .btn-guru:hover { background: linear-gradient(135deg, #6d28d9, #9333ea); color: #fff; transform: translateY(-2px); }

        .header-gradient {
            background: linear-gradient(135deg, rgba(124,58,237,0.08) 0%, rgba(168,85,247,0.04) 100%);
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="glass-card p-4 mb-4 header-gradient">
        <div class="row align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:52px;height:52px;background:linear-gradient(135deg,#7c3aed,#a855f7);">
                        <i class="bi bi-person-badge-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0 text-guru">Manajemen Data Guru</h3>
                        <p class="text-muted mb-0 small"><?= $tgl_indo ?> | <span id="live-clock">--:--:--</span></p>
                    </div>
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <button onclick="toggleForm()" id="btn-tambah-guru" class="btn btn-guru btn-action px-4 py-2">
                    <i class="bi bi-person-plus-fill me-2"></i> Tambah Guru
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="export_guru_excel.php?mapel=<?= xss($mapel_filter) ?>&q=<?= xss($keyword) ?>" class="btn btn-success w-100 btn-action py-3 bg-opacity-75">
                <div class="small opacity-75">Download</div>
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Data Guru
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="dashboard.php" class="btn btn-secondary w-100 btn-action py-3 bg-opacity-75">
                <div class="small opacity-75">Navigasi</div>
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="data_siswa.php" class="btn btn-primary w-100 btn-action py-3 bg-opacity-75">
                <div class="small opacity-75">Data Lain</div>
                <i class="bi bi-people me-1"></i> Data Siswa
            </a>
        </div>
        <div class="col-6 col-md-3">
            <span class="btn btn-light w-100 btn-action py-3 border">
                <div class="small opacity-75 text-muted">Total Guru</div>
                <span class="fw-bold text-guru fs-5"><?= $total_data ?></span>
                <span class="small ms-1 text-muted">orang</span>
            </span>
        </div>
    </div>

    <div id="formTambah" class="glass-card p-4 mb-4 border-top border-4 border-opacity-25" style="border-color: #7c3aed !important;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold m-0"><i class="bi bi-plus-circle me-2 text-guru"></i>Input Data Guru Baru</h5>
            <button onclick="toggleForm()" class="btn-close"></button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="proses_tambah_guru.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="row g-3">
                <div class="col-md-2 text-center">
                    <div class="mx-auto rounded-circle bg-light border d-flex align-items-center justify-content-center" style="width: 110px; height: 110px; overflow: hidden;">
                        <img id="img-preview" src="" style="display:none; width: 100%; height: 100%; object-fit: cover;">
                        <i id="icon-placeholder" class="bi bi-person-bounding-box fs-1 text-muted"></i>
                    </div>
                    <label for="foto-input" class="btn btn-sm btn-outline-secondary mt-3 rounded-pill">Upload Foto</label>
                    <input type="file" name="foto" id="foto-input" class="d-none" accept="image/*" onchange="previewImage(event)">
                </div>
                
                <div class="col-md-10">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="small fw-bold">NIP</label>
                            <input type="text" name="nip" class="form-control" placeholder="Nomor Induk Pegawai" required>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">RFID UID</label>
                            <input type="text" name="rfid_uid" class="form-control" placeholder="Tempel kartu...">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" placeholder="Nama guru..." required>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="small fw-bold">Mata Pelajaran</label>
                            <input type="text" name="mata_pelajaran" class="form-control" placeholder="cth: Matematika">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Jabatan</label>
                            <select name="jabatan" class="form-select">
                                <option value="">-- Pilih Jabatan --</option>
                                <option value="Guru">Guru</option>
                                <option value="Wali Kelas">Wali Kelas</option>
                                <option value="Kepala Sekolah">Kepala Sekolah</option>
                                <option value="Wakil Kepala Sekolah">Wakil Kepala Sekolah</option>
                                <option value="Guru BK">Guru BK</option>
                                <option value="Staf TU">Staf TU</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">No. HP / WA</label>
                            <input type="text" name="no_hp" class="form-control" placeholder="628...">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="small fw-bold">ID Telegram</label>
                            <input type="text" name="telegram_id" class="form-control" placeholder="Opsional">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Alamat Email</label>
                            <input type="email" name="email" class="form-control" placeholder="guru@sekolah.sch.id">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="tambah" class="btn btn-guru w-100 fw-bold py-2 rounded-3">
                                <i class="bi bi-cloud-arrow-up me-1"></i> SIMPAN DATA
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="glass-card p-4">
        <div class="row g-3 mb-4 align-items-center">
            <div class="col-md-6">
                <h5 class="fw-bold m-0 text-dark">Daftar Guru <span class="badge bg-opacity-10 text-guru rounded-pill ms-2" style="background-color: rgba(124,58,237,0.1);"><?= $total_data ?></span></h5>
            </div>
            <div class="col-md-6">
                <form method="GET" class="d-flex gap-2">
                    <select name="mapel" class="form-select form-select-sm w-auto border-0 shadow-sm">
                        <option value="">Semua Mapel</option>
                        <?php 
                        while($mpl = mysqli_fetch_assoc($q_mapel_list)): ?>
                            <option value="<?= xss($mpl['mata_pelajaran']) ?>" <?= ($mapel_filter == $mpl['mata_pelajaran']) ? 'selected' : '' ?>><?= xss($mpl['mata_pelajaran']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="input-group input-group-sm shadow-sm">
                        <input type="text" name="q" class="form-control border-0" placeholder="Cari nama, NIP, jabatan..." value="<?= xss($keyword) ?>">
                        <button class="btn btn-white bg-white border-0" type="submit"><i class="bi bi-search" style="color:#7c3aed;"></i></button>
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
                        <th class="text-center">MATA PELAJARAN</th>
                        <th class="text-center">JABATAN</th>
                        <th class="text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($data_guru->num_rows > 0): ?>
                        <?php while($row = $data_guru->fetch_assoc()): ?>
                        <tr class="border-bottom border-white border-opacity-50">
                            <td class="text-center">
                                <div class="photo-circle mx-auto">
                                    <?php $foto = "img/guru/" . $row['foto']; if (!empty($row['foto']) && file_exists($foto)): ?>
                                        <img src="<?= $foto ?>" alt="<?= xss($row['nama']) ?>">
                                    <?php else: ?><i class="bi bi-person-badge text-muted fs-4"></i><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= xss($row['nama']) ?></div>
                                <code class="small text-muted">NIP: <?= xss($row['nip']) ?></code>
                                <?php if(!empty($row['no_hp'])): ?>
                                <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= xss($row['no_hp']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if(!empty($row['mata_pelajaran'])): ?>
                                <span class="badge badge-mapel rounded-pill px-3"><?= xss($row['mata_pelajaran']) ?></span>
                                <?php else: ?>
                                <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if(!empty($row['jabatan'])): ?>
                                <span class="badge bg-light text-secondary border rounded-pill px-3"><?= xss($row['jabatan']) ?></span>
                                <?php else: ?>
                                <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group gap-1">
                                    <a href="daftar_wajah_guru.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light border text-info rounded-3" title="Rekam Wajah">
                                        <i class="bi bi-person-bounding-box"></i>
                                    </a>

                                    <a href="edit_guru.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light border rounded-3" style="color:#7c3aed;" title="Edit Data">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <a href="data_guru.php?hapus_id=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-light border text-danger rounded-3" onclick="return confirm('Hapus data guru ini secara permanen?')" title="Hapus Data">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="bi bi-person-badge fs-1 text-muted opacity-25 d-block mb-2"></i>
                                <span class="text-muted">Belum ada data guru. Klik <strong>Tambah Guru</strong> untuk memulai.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_halaman > 1): ?>
        <nav class="mt-4"><ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?= ($halaman <= 1) ? 'disabled' : '' ?>"><a class="page-link border-0 shadow-sm mx-1 rounded-circle" href="?p=<?= $halaman - 1 ?>&q=<?= xss($keyword) ?>&mapel=<?= xss($mapel_filter) ?>"><i class="bi bi-chevron-left"></i></a></li>
            <li class="page-item active"><span class="page-link border-0 shadow-sm mx-1 rounded-circle px-3"><?= $halaman ?></span></li>
            <li class="page-item <?= ($halaman >= $total_halaman) ? 'disabled' : '' ?>"><a class="page-link border-0 shadow-sm mx-1 rounded-circle" href="?p=<?= $halaman + 1 ?>&q=<?= xss($keyword) ?>&mapel=<?= xss($mapel_filter) ?>"><i class="bi bi-chevron-right"></i></a></li>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleForm() {
        const form = document.getElementById('formTambah');
        const btn  = document.getElementById('btn-tambah-guru');
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'block';
            btn.innerHTML = '<i class="bi bi-x-circle me-2"></i> Tutup Form';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            form.style.display = 'none';
            btn.innerHTML = '<i class="bi bi-person-plus-fill me-2"></i> Tambah Guru';
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
</script>
</body>
</html>
