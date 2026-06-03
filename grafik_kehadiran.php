<?php
session_start();
include 'koneksi.php';

if(!isset($_SESSION['login'])){
    header("location: login.php");
    exit;
}

// --- 1. LOGIKA FILTER ---
$mode = $_GET['mode'] ?? '7hari';
$bulan_pilih = $_GET['bulan'] ?? date('m');
$tahun_pilih = $_GET['tahun'] ?? date('Y');

$tgl_db = [];
$labels_js = [];

if ($mode == '7hari') {
    // Mode 7 Hari: Cari 7 tanggal terakhir yang ada aktivitasnya
    $res_tgl = $conn->query("SELECT DISTINCT DATE(waktu_masuk) as tgl FROM absensi ORDER BY tgl DESC LIMIT 7");
    while($row = $res_tgl->fetch_assoc()) { $tgl_db[] = $row['tgl']; }
    $tgl_db = array_reverse($tgl_db);
} else {
    // Mode Bulanan: Cari semua tanggal aktif di bulan tersebut
    $res_tgl = $conn->query("SELECT DISTINCT DATE(waktu_masuk) as tgl FROM absensi 
                             WHERE MONTH(waktu_masuk) = '$bulan_pilih' AND YEAR(waktu_masuk) = '$tahun_pilih' 
                             ORDER BY tgl ASC");
    while($row = $res_tgl->fetch_assoc()) { $tgl_db[] = $row['tgl']; }
}

// Format label untuk Chart.js (contoh: 15 Apr)
foreach($tgl_db as $t) {
    $labels_js[] = date('d M', strtotime($t));
}

// --- 2. AMBIL DATA KELAS & TOTAL SISWA ---
$query_siswa = mysqli_query($conn, "SELECT kelas, COUNT(*) as total FROM siswa WHERE kelas IS NOT NULL AND kelas != '' GROUP BY kelas");
$list_kelas = [];
while($rk = mysqli_fetch_assoc($query_siswa)) {
    $list_kelas[$rk['kelas']] = (int)$rk['total'];
}

// --- 3. AMBIL DATA HADIR ---
$data_hadir = [];
if (!empty($tgl_db)) {
    $tgl_awal = $tgl_db[0];
    $tgl_akhir = end($tgl_db);
    
    $sql = "SELECT DATE(a.waktu_masuk) as tgl, s.kelas, COUNT(*) as jml 
            FROM absensi a 
            LEFT JOIN siswa s ON a.nis = s.nis 
            WHERE DATE(a.waktu_masuk) BETWEEN '$tgl_awal' AND '$tgl_akhir'
            GROUP BY tgl, s.kelas";
    
    $res_data = mysqli_query($conn, $sql);
    while($row = mysqli_fetch_assoc($res_data)) {
        $kls_name = $row['kelas'] ? $row['kelas'] : 'Tanpa Kelas';
        $data_hadir[$kls_name][$row['tgl']] = (int)$row['jml'];
    }
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Grafik Presensi - Asofa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f0f3f9; }
        .glass-card { background: white; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; }
        .chart-container { position: relative; height: 400px; width: 100%; }
        .filter-box { background: white; border-radius: 15px; padding: 15px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-800 text-dark mb-0">📈 Grafik Progres</h2>
        <a href="dashboard.php" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">Dashboard</a>
    </div>

    <div class="filter-box shadow-sm">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted mb-1">Mode Tampilan</label>
                <select name="mode" class="form-select form-select-sm border-0 bg-light" onchange="this.form.submit()">
                    <option value="7hari" <?= $mode == '7hari' ? 'selected' : '' ?>>7 Hari Aktif Terakhir</option>
                    <option value="bulanan" <?= $mode == 'bulanan' ? 'selected' : '' ?>>Rekap Bulanan</option>
                </select>
            </div>
            
            <?php if($mode == 'bulanan'): ?>
            <div class="col-md-3">
                <label class="small fw-bold text-muted mb-1">Pilih Bulan</label>
                <select name="bulan" class="form-select form-select-sm border-0 bg-light">
                    <?php
                    $bln_nama = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
                    foreach($bln_nama as $k => $v) echo "<option value='$k' ".($k==$bulan_pilih?'selected':'').">$v</option>";
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted mb-1">Tahun</label>
                <input type="number" name="tahun" class="form-control form-select-sm border-0 bg-light" value="<?= $tahun_pilih ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 rounded-3">Tampilkan</button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="glass-card shadow-sm">
        <?php if(empty($tgl_db)): ?>
            <div class="text-center py-5">
                <h6 class="text-muted">Tidak ada data absensi untuk periode ini.</h6>
            </div>
        <?php else: ?>
            <div class="chart-container">
                <canvas id="progressChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
<?php if(!empty($tgl_db)): ?>
    const ctx = document.getElementById('progressChart').getContext('2d');
    const colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316', '#14b8a6', '#6366f1'];

    const datasets = [
        <?php 
        $i = 0;
        foreach($list_kelas as $kls_name => $total_siswa): 
            $points = [];
            foreach($tgl_db as $t) {
                $hadir = isset($data_hadir[$kls_name][$t]) ? $data_hadir[$kls_name][$t] : 0;
                $persen = ($total_siswa > 0) ? round(($hadir / $total_siswa) * 100, 1) : 0;
                $points[] = $persen;
            }
        ?>
        {
            label: '<?= addslashes($kls_name) ?>',
            data: <?= json_encode($points) ?>,
            borderColor: colors[<?= $i % 10 ?>],
            backgroundColor: colors[<?= $i % 10 ?>],
            borderWidth: 3,
            tension: 0.3,
            fill: false
        },
        <?php $i++; endforeach; ?>
    ];

    new Chart(ctx, {
        type: 'line',
        data: { labels: <?= json_encode($labels_js) ?>, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { weight: 'bold' } } },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y}%` } }
            },
            scales: {
                y: { beginAtZero: true, max: 100, ticks: { callback: (v) => v + "%", font: { weight: 'bold' } } },
                x: { ticks: { font: { weight: 'bold' } }, grid: { display: false } }
            }
        }
    });
<?php endif; ?>
</script>
</body>
</html>