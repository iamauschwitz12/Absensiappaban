<?php
session_start();
include 'koneksi.php';

// --- SECURITY: CEK LOGIN ---
if(!isset($_SESSION['login'])){
    die("Akses ditolak! Silakan login terlebih dahulu.");
}

function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// 1. AMBIL PARAMETER
$mode       = $_GET['mode']       ?? 'harian';
$tgl_harian = $_GET['tgl_harian'] ?? date('Y-m-d');
$tgl_awal   = $_GET['tgl_awal']   ?? date('Y-m-01');
$tgl_akhir  = $_GET['tgl_akhir']  ?? date('Y-m-d');
$keyword    = $_GET['q']          ?? '';

// 2. SETTING HEADER EXCEL
$filename = "Laporan_Guru_" . strtoupper($mode) . "_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 3. AMBIL SETTING
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id = 1");
$stmt_set->execute();
$sett = $stmt_set->get_result()->fetch_assoc();
$libur_pekanan = $sett['libur_pekanan'] ?? 'minggu';

// 4. FUNGSI HITUNG HARI KERJA
function hitungHariKerjaGuru($start, $end, $libur_p, $conn) {
    $count = 0;
    $period = new DatePeriod(
        new DateTime($start),
        new DateInterval('P1D'),
        (new DateTime($end))->modify('+1 day')
    );
    $stmt_l = $conn->prepare("SELECT tanggal FROM libur_manual WHERE tanggal BETWEEN ? AND ?");
    $stmt_l->bind_param("ss", $start, $end);
    $stmt_l->execute();
    $res_l = $stmt_l->get_result();
    $libur_manual = [];
    while($l = $res_l->fetch_assoc()) { $libur_manual[] = $l['tanggal']; }
    foreach ($period as $date) {
        $hari    = $date->format('N');
        $tgl_str = $date->format('Y-m-d');
        $is_weekend = ($libur_p == 'minggu' && $hari == 7)
                   || ($libur_p == 'sabtu_minggu' && ($hari == 6 || $hari == 7));
        if (!$is_weekend && !in_array($tgl_str, $libur_manual)) { $count++; }
    }
    return $count;
}

// 5. QUERY DATA
$params = [];
$types  = "";

if ($mode == 'harian') {
    $sql = "SELECT g.nip, g.nama, g.jabatan,
                   ag.waktu_masuk, ag.waktu_pulang,
                   ag.keterangan, ag.status_kehadiran
            FROM guru g
            LEFT JOIN absensi_guru ag ON g.nip = ag.nip AND DATE(ag.waktu_masuk) = ?
            WHERE 1=1";
    $params[] = $tgl_harian;
    $types   .= "s";
} else {
    $hari_efektif = hitungHariKerjaGuru($tgl_awal, $tgl_akhir, $libur_pekanan, $conn);
    $sql = "SELECT g.nip, g.nama, g.jabatan,
                   COUNT(CASE WHEN ag.status_kehadiran = 'Tepat Waktu' AND ag.keterangan NOT IN ('Izin','Sakit','Bolos') THEN 1 END) as jml_hadir,
                   COUNT(CASE WHEN ag.status_kehadiran = 'Terlambat'   THEN 1 END) as jml_telat,
                   COUNT(CASE WHEN ag.keterangan = 'Izin'   THEN 1 END) as jml_izin,
                   COUNT(CASE WHEN ag.keterangan = 'Sakit'  THEN 1 END) as jml_sakit,
                   COUNT(CASE WHEN ag.keterangan = 'Bolos'  THEN 1 END) as jml_bolos
            FROM guru g
            LEFT JOIN absensi_guru ag ON g.nip = ag.nip AND DATE(ag.waktu_masuk) BETWEEN ? AND ?
            WHERE 1=1";
    $params[] = $tgl_awal;
    $params[] = $tgl_akhir;
    $types   .= "ss";
}

if ($keyword != '') {
    $search = "%$keyword%";
    $sql   .= " AND (g.nama LIKE ? OR g.nip LIKE ? OR g.jabatan LIKE ?)";
    $params[] = $search; $params[] = $search; $params[] = $search;
    $types   .= "sss";
}

if ($mode == 'rekap') { $sql .= " GROUP BY g.nip"; }
$sql .= " ORDER BY g.nama ASC";

$stmt_main = $conn->prepare($sql);
if ($types) $stmt_main->bind_param($types, ...$params);
$stmt_main->execute();
$query = $stmt_main->get_result();

// Jumlah kolom untuk colspan
$colspan = ($mode == 'harian') ? 6 : 9;
?>
<table border="1">
    <tr>
        <th colspan="<?= $colspan ?>" style="font-size:16px;font-weight:bold;background-color:#7c3aed;color:white;">
            LAPORAN ABSENSI GURU <?= strtoupper($mode) ?> - <?= xss($sett['nama_sekolah'] ?? '') ?>
        </th>
    </tr>
    <tr>
        <th colspan="<?= $colspan ?>" style="background-color:#f8f9fa;">
            Periode:
            <?php if ($mode == 'harian'): ?>
                <?= date('d-m-Y', strtotime($tgl_harian)) ?>
            <?php else: ?>
                <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?>
                | Hari Efektif: <?= $hari_efektif ?? 0 ?> Hari
            <?php endif; ?>
        </th>
    </tr>
    <thead>
        <tr style="background-color:#ede9fe;font-weight:bold;">
            <th>No</th>
            <th>NIP</th>
            <th>Nama Guru</th>
            <th>Jabatan</th>
            <?php if($mode == 'harian'): ?>
                <th>Jam Masuk</th>
                <th>Status</th>
            <?php else: ?>
                <th>Hadir</th>
                <th>Telat</th>
                <th>Izin</th>
                <th>Sakit</th>
                <th>Bolos</th>
                <th>Alpha</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        while($row = $query->fetch_assoc()):
            if ($mode == 'harian'):
                $jam_m = $row['waktu_masuk'] ? date('H:i', strtotime($row['waktu_masuk'])) : '-';
                $ket   = $row['keterangan'] ?? '';

                if (!$row['waktu_masuk'] && empty($ket)) {
                    $status = "Alpha";
                } elseif (!$row['waktu_masuk'] && $ket == 'Alpha') {
                    $status = "Alpha";
                } elseif (in_array($ket, ['Sakit','Izin','Bolos'])) {
                    $status = $ket;
                } else {
                    $status = ($row['status_kehadiran'] == 'Terlambat') ? 'Telat' : 'Hadir';
                }
        ?>
            <tr>
                <td align="center"><?= $no++ ?></td>
                <td>'<?= xss($row['nip']) ?></td>
                <td><?= xss($row['nama']) ?></td>
                <td align="center"><?= xss($row['jabatan'] ?? '-') ?></td>
                <td align="center"><?= $jam_m ?></td>
                <td align="center"><?= strtoupper(xss($status)) ?></td>
            </tr>
        <?php
            else:
                $total_in  = $row['jml_hadir'] + $row['jml_telat'] + $row['jml_izin'] + $row['jml_sakit'] + $row['jml_bolos'];
                $jml_alpha = max(0, $hari_efektif - $total_in);
        ?>
            <tr>
                <td align="center"><?= $no++ ?></td>
                <td>'<?= xss($row['nip']) ?></td>
                <td><?= xss($row['nama']) ?></td>
                <td align="center"><?= xss($row['jabatan'] ?? '-') ?></td>
                <td align="center"><?= $row['jml_hadir'] ?></td>
                <td align="center"><?= $row['jml_telat'] ?></td>
                <td align="center"><?= $row['jml_izin'] ?></td>
                <td align="center"><?= $row['jml_sakit'] ?></td>
                <td align="center"><?= $row['jml_bolos'] ?></td>
                <td align="center" style="color:red;font-weight:bold;"><?= $jml_alpha ?></td>
            </tr>
        <?php endif; endwhile; ?>
    </tbody>
</table>
