<?php
include 'koneksi.php';

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'harian';
$tgl_harian = isset($_GET['tgl_harian']) ? $_GET['tgl_harian'] : date('Y-m-d');
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kelas_filter = isset($_GET['kelas']) ? $_GET['kelas'] : '';

$q_set = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan WHERE id=1"));
$nama_sekolah = $q_set['nama_sekolah'];
$libur_pekanan = $q_set['libur_pekanan'];

function hitungHariKerjaPDF($start, $end, $libur_p, $conn) {
    $count = 0;
    $period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));
    $libur_manual = [];
    $q_lib = mysqli_query($conn, "SELECT tanggal FROM libur_manual WHERE tanggal BETWEEN '$start' AND '$end'");
    while($l = mysqli_fetch_assoc($q_lib)) { $libur_manual[] = $l['tanggal']; }
    foreach ($period as $date) {
        $hari = $date->format('N');
        $tgl_str = $date->format('Y-m-d');
        $is_weekend = ($libur_p == 'minggu' && $hari == 7) || ($libur_p == 'sabtu_minggu' && ($hari == 6 || $hari == 7));
        if (!$is_weekend && !in_array($tgl_str, $libur_manual)) { $count++; }
    }
    return $count;
}

if ($mode == 'harian') {
    $sql = "SELECT s.nis, s.nama, s.kelas, a.waktu_masuk, a.waktu_pulang, a.keterangan, a.status_kehadiran
            FROM siswa s
            LEFT JOIN absensi a ON s.nis = a.nis AND DATE(a.waktu_masuk) = '$tgl_harian'
            WHERE 1=1";
} else {
    $hari_efektif = hitungHariKerjaPDF($tgl_awal, $tgl_akhir, $libur_pekanan, $conn);
    $sql = "SELECT s.nis, s.nama, s.kelas,
            COUNT(CASE WHEN a.keterangan = 'Hadir' AND a.status_kehadiran = 'Tepat Waktu' THEN 1 END) as jml_hadir,
            COUNT(CASE WHEN a.status_kehadiran = 'Terlambat' THEN 1 END) as jml_telat,
            COUNT(CASE WHEN a.keterangan = 'Izin' THEN 1 END) as jml_izin,
            COUNT(CASE WHEN a.keterangan = 'Sakit' THEN 1 END) as jml_sakit
            FROM siswa s
            LEFT JOIN absensi a ON s.nis = a.nis AND DATE(a.waktu_masuk) BETWEEN '$tgl_awal' AND '$tgl_akhir'
            WHERE 1=1";
}

if ($kelas_filter != '') { $sql .= " AND s.kelas = '$kelas_filter'"; }
if ($mode == 'rekap') { $sql .= " GROUP BY s.nis"; }
$sql .= " ORDER BY s.nama ASC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cetak Laporan <?= ucfirst($mode) ?></title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background-color: #f2f2f2; }
        .text-center { text-align: center; }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h2 style="margin:0;"><?= strtoupper($nama_sekolah) ?></h2>
        <h3 style="margin:5px 0;">LAPORAN PRESENSI <?= strtoupper($mode) ?></h3>
        <p style="margin:0;">Periode: <?= ($mode == 'harian') ? date('d/m/Y', strtotime($tgl_harian)) : date('d/m/Y', strtotime($tgl_awal))." - ".date('d/m/Y', strtotime($tgl_akhir)) ?></p>
        <?php if($mode == 'rekap') echo "<p style='margin:0;'>Hari Efektif: $hari_efektif Hari</p>"; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama Siswa</th>
                <?php if($mode == 'harian'): ?>
                    <th>Masuk</th>
                    <th>Pulang</th>
                    <th>Status</th>
                <?php else: ?>
                    <th>H</th>
                    <th>T</th>
                    <th>I/S</th>
                    <th>A</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while($row = mysqli_fetch_assoc($result)): 
                if($mode == 'harian'):
                    $jam_m = $row['waktu_masuk'] ? date('H:i', strtotime($row['waktu_masuk'])) : '-';
                    $jam_p = $row['waktu_pulang'] ? date('H:i', strtotime($row['waktu_pulang'])) : '-';
                    $status = (!$row['waktu_masuk']) ? "ALPHA" : (($row['status_kehadiran'] == 'Terlambat') ? 'TELAT' : strtoupper($row['keterangan']));
            ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center"><?= $row['nis'] ?></td>
                    <td><?= $row['nama'] ?></td>
                    <td class="text-center"><?= $jam_m ?></td>
                    <td class="text-center"><?= $jam_p ?></td>
                    <td class="text-center"><?= $status ?></td>
                </tr>
            <?php else: 
                    $total_in = $row['jml_hadir'] + $row['jml_telat'] + $row['jml_izin'] + $row['jml_sakit'];
                    $jml_alpha = max(0, $hari_efektif - $total_in);
            ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center"><?= $row['nis'] ?></td>
                    <td><?= $row['nama'] ?></td>
                    <td class="text-center"><?= $row['jml_hadir'] ?></td>
                    <td class="text-center"><?= $row['jml_telat'] ?></td>
                    <td class="text-center"><?= $row['jml_izin'] + $row['jml_sakit'] ?></td>
                    <td class="text-center" style="font-weight:bold; <?= $jml_alpha > 0 ? 'color:red;' : '' ?>"><?= $jml_alpha ?></td>
                </tr>
            <?php endif; endwhile; ?>
        </tbody>
    </table>

    <p style="text-align: right; margin-top: 20px;">Dicetak pada: <?= date('d/m/Y H:i') ?></p>
</body>
</html>