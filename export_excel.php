<?php
session_start();
include 'koneksi.php';

// --- SECURITY: CEK LOGIN ---
if(!isset($_SESSION['login'])){
    die("Akses ditolak! Silakan login terlebih dahulu.");
}

// --- SECURITY: HELPER XSS ---
function xss($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// 1. AMBIL PARAMETER SECURELY
$mode         = $_GET['mode'] ?? 'harian';
$tgl_harian   = $_GET['tgl_harian'] ?? date('Y-m-d');
$tgl_awal     = $_GET['tgl_awal'] ?? date('Y-m-d');
$tgl_akhir    = $_GET['tgl_akhir'] ?? date('Y-m-d');
$kelas_filter = $_GET['kelas'] ?? '';

// 2. SETTING HEADER EXCEL
$filename = "Laporan_" . strtoupper($mode) . "_" . ($kelas_filter ?: "Semua_Kelas") . "_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$filename");
header("Pragma: no-cache");
header("Expires: 0");

// 3. AMBIL SETTING (PREPARED STATEMENT)
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id = 1");
$stmt_set->execute();
$sett = $stmt_set->get_result()->fetch_assoc();
$libur_pekanan = $sett['libur_pekanan'];

// --- 4. FUNGSI HITUNG HARI KERJA AMAN ---
function hitungHariKerjaExcel($start, $end, $libur_p, $conn) {
    $count = 0;
    $period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));
    
    $stmt_l = $conn->prepare("SELECT tanggal FROM libur_manual WHERE tanggal BETWEEN ? AND ?");
    $stmt_l->bind_param("ss", $start, $end);
    $stmt_l->execute();
    $res_l = $stmt_l->get_result();
    $libur_manual = [];
    while($l = $res_l->fetch_assoc()) { $libur_manual[] = $l['tanggal']; }

    foreach ($period as $date) {
        $hari = $date->format('N');
        $tgl_str = $date->format('Y-m-d');
        $is_weekend = ($libur_p == 'minggu' && $hari == 7) || ($libur_p == 'sabtu_minggu' && ($hari == 6 || $hari == 7));
        if (!$is_weekend && !in_array($tgl_str, $libur_manual)) { $count++; }
    }
    return $count;
}

// --- 5. QUERY DATA (PREPARED STATEMENTS) ---
$params = [];
$types = "";

if ($mode == 'harian') {
    $sql = "SELECT s.nis, s.nama, s.kelas, a.waktu_masuk, a.waktu_pulang, a.keterangan, a.status_kehadiran
            FROM siswa s
            LEFT JOIN absensi a ON s.nis = a.nis AND DATE(a.waktu_masuk) = ?
            WHERE 1=1";
    $params[] = $tgl_harian;
    $types .= "s";
} else {
    $hari_efektif = hitungHariKerjaExcel($tgl_awal, $tgl_akhir, $libur_pekanan, $conn);
    $sql = "SELECT s.nis, s.nama, s.kelas,
            COUNT(CASE WHEN a.keterangan = 'Hadir' AND a.status_kehadiran = 'Tepat Waktu' THEN 1 END) as jml_hadir,
            COUNT(CASE WHEN a.status_kehadiran = 'Terlambat' THEN 1 END) as jml_telat,
            COUNT(CASE WHEN a.keterangan = 'Izin' THEN 1 END) as jml_izin,
            COUNT(CASE WHEN a.keterangan = 'Sakit' THEN 1 END) as jml_sakit,
            COUNT(CASE WHEN a.keterangan = 'Bolos' THEN 1 END) as jml_bolos
            FROM siswa s
            LEFT JOIN absensi a ON s.nis = a.nis AND DATE(a.waktu_masuk) BETWEEN ? AND ?
            WHERE 1=1";
    $params[] = $tgl_awal;
    $params[] = $tgl_akhir;
    $types .= "ss";
}

if ($kelas_filter != '') { 
    $sql .= " AND s.kelas = ?"; 
    $params[] = $kelas_filter;
    $types .= "s";
}

if ($mode == 'rekap') { $sql .= " GROUP BY s.nis"; }
$sql .= " ORDER BY s.nama ASC";

$stmt_main = $conn->prepare($sql);
if($types) $stmt_main->bind_param($types, ...$params);
$stmt_main->execute();
$query = $stmt_main->get_result();
?>

<table border="1">
    <tr>
        <th colspan="<?= ($mode == 'harian') ? '7' : '9' ?>" style="font-size: 16px; font-weight: bold; background-color: #0d6efd; color: white;">
            LAPORAN PRESENSI <?= strtoupper($mode) ?> - <?= xss($sett['nama_sekolah']) ?>
        </th>
    </tr>
    <tr>
        <th colspan="<?= ($mode == 'harian') ? '7' : '9' ?>" style="background-color: #f8f9fa;">
            Periode: <?= ($mode == 'harian') ? date('d-m-Y', strtotime($tgl_harian)) : date('d-m-Y', strtotime($tgl_awal)) . " s/d " . date('d-m-Y', strtotime($tgl_akhir)) ?>
            <?= ($mode == 'rekap') ? " | Hari Efektif: $hari_efektif Hari" : "" ?>
        </th>
    </tr>
    <thead>
        <tr style="background-color: #e9ecef; font-weight: bold;">
            <th>No</th>
            <th>NIS</th>
            <th>Nama Siswa</th>
            <th>Kelas</th>
            <?php if($mode == 'harian'): ?>
                <th>Jam Masuk</th>
                <th>Jam Pulang</th>
                <th>Status Kehadiran</th>
            <?php else: ?>
                <th>Hadir</th>
                <th>Telat</th>
                <th>Izin/Sakit</th>
                <th>Bolos</th>
                <th>Alpha</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        while($row = $query->fetch_assoc()): 
            if($mode == 'harian'):
                $jam_m = $row['waktu_masuk'] ? date('H:i', strtotime($row['waktu_masuk'])) : '-';
                $jam_p = $row['waktu_pulang'] ? date('H:i', strtotime($row['waktu_pulang'])) : '-';
                
                if(!$row['waktu_masuk']) {
                    $status = "Alpha";
                } else {
                    if($row['keterangan'] == 'Bolos'){
                        $status = "Bolos";
                    } else {
                        $status = ($row['status_kehadiran'] == 'Terlambat') ? 'Telat' : $row['keterangan'];
                    }
                }
        ?>
            <tr>
                <td align="center"><?= $no++ ?></td>
                <td>'<?= xss($row['nis']) ?></td>
                <td><?= xss($row['nama']) ?></td>
                <td align="center"><?= xss($row['kelas']) ?></td>
                <td align="center"><?= $jam_m ?></td>
                <td align="center"><?= $jam_p ?></td>
                <td align="center"><?= strtoupper(xss($status)) ?></td>
            </tr>
        <?php else: 
                $total_in = $row['jml_hadir'] + $row['jml_telat'] + $row['jml_izin'] + $row['jml_sakit'] + $row['jml_bolos'];
                $jml_alpha = max(0, $hari_efektif - $total_in);
        ?>
            <tr>
                <td align="center"><?= $no++ ?></td>
                <td>'<?= xss($row['nis']) ?></td>
                <td><?= xss($row['nama']) ?></td>
                <td align="center"><?= xss($row['kelas']) ?></td>
                <td align="center"><?= $row['jml_hadir'] ?></td>
                <td align="center"><?= $row['jml_telat'] ?></td>
                <td align="center"><?= $row['jml_izin'] + $row['jml_sakit'] ?></td>
                <td align="center"><?= $row['jml_bolos'] ?></td>
                <td align="center" style="color: red; font-weight: bold;"><?= $jml_alpha ?></td>
            </tr>
        <?php endif; endwhile; ?>
    </tbody>
</table>