<?php
session_start();
include 'koneksi.php';

// Proteksi Login
if (!isset($_SESSION['login'])) { exit("Akses Ditolak"); }

// 1. Ambil Parameter Filter agar data di Excel sama dengan yang ada di layar
$keyword = $_GET['q'] ?? '';
$kelas_filter = $_GET['kelas'] ?? '';
$role = $_SESSION['role'];
$kelas_diampu = $_SESSION['kelas_diampu'] ?? '';

// 2. Query Data berdasarkan filter
$where = ($role == 'walikelas') ? "WHERE kelas = '$kelas_diampu'" : "WHERE 1=1";
if (!empty($keyword)) {
    $where .= " AND (nama LIKE '%$keyword%' OR nis LIKE '%$keyword%')";
}
if (!empty($kelas_filter)) {
    $where .= " AND kelas = '$kelas_filter'";
}

// Mengambil semua kolom kecuali yang berkaitan dengan wajah (face_embedding)
$query = mysqli_query($conn, "SELECT id, nis, nama, kelas, no_hp_ortu, telegram_chat_id, email, sesi, rfid_uid FROM siswa $where ORDER BY kelas ASC, nama ASC");

// 3. Header untuk download file Excel (.xls)
$filename = "Data_Siswa_Full_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>

<style>
    /* CSS agar format nomor (NIS, WA, RFID) tetap sebagai teks (bukan 6.2E+12) */
    .str-text { 
        mso-number-format:"\@"; 
    } 
    th { 
        background-color: #1d6f42; 
        color: white; 
        text-align: center; 
        font-weight: bold; 
        border: 1px solid #000;
    }
    td { 
        vertical-align: middle; 
        border: 1px solid #ccc; 
        padding: 5px;
    }
</style>

<table border="1">
    <thead>
        <tr>
            <th width="30">NO</th>
            <th width="100">NIS</th>
            <th width="250">NAMA LENGKAP</th>
            <th width="100">KELAS</th>
            <th width="150">WHATSAPP (ORTU)</th>
            <th width="150">TELEGRAM ID</th>
            <th width="200">EMAIL</th>
            <th width="60">SESI</th>
            <th width="150">RFID UID</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        while($row = mysqli_fetch_assoc($query)): 
        ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td class="str-text"><?= $row['nis'] ?></td>
            <td><?= strtoupper($row['nama']) ?></td>
            <td align="center"><?= $row['kelas'] ?></td>
            <td class="str-text"><?= $row['no_hp_ortu'] ?? '-' ?></td> 
            <td class="str-text"><?= $row['telegram_chat_id'] ?? '-' ?></td>
            <td><?= strtolower($row['email'] ?? '-') ?></td>
            <td align="center"><?= $row['sesi'] ?></td>
            <td class="str-text"><?= $row['rfid_uid'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>