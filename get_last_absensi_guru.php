<?php
include 'koneksi.php';

// Ambil 5 absensi guru terbaru hari ini (siswa + guru digabung, diurutkan waktu terbaru)
$tgl = date('Y-m-d');

$sql = "SELECT 
            ag.waktu_masuk, ag.waktu_pulang, ag.status_kehadiran, ag.keterangan,
            g.nama, g.jabatan AS kelas, g.foto,
            'guru' AS tipe
        FROM absensi_guru ag
        JOIN guru g ON ag.nip = g.nip
        WHERE DATE(ag.waktu_masuk) = '$tgl'
        ORDER BY GREATEST(IFNULL(ag.waktu_masuk, '1970-01-01'), IFNULL(ag.waktu_pulang, '1970-01-01')) DESC
        LIMIT 10";

$query = mysqli_query($conn, $sql);

if(mysqli_num_rows($query) > 0){
    while($row = mysqli_fetch_assoc($query)){

        // Label status & waktu tampil
        if(!empty($row['waktu_pulang']) && $row['waktu_pulang'] != '0000-00-00 00:00:00'){
            $jam_tampil   = date('H:i', strtotime($row['waktu_pulang']));
            $status_label = "PULANG";
            $badge_class  = "bg-danger-subtle text-danger border-danger-subtle";
        } else {
            $jam_tampil   = date('H:i', strtotime($row['waktu_masuk']));
            $status_label = ($row['status_kehadiran'] == 'Terlambat') ? "TERLAMBAT" : "MASUK";
            $badge_class  = ($row['status_kehadiran'] == 'Terlambat')
                ? "bg-warning-subtle text-warning border-warning-subtle"
                : "bg-success-subtle text-success border-success-subtle";
        }

        // Avatar guru
        $foto_path = "img/guru/" . $row['foto'];
        if(!empty($row['foto']) && file_exists($foto_path)){
            $avatar = '<img src="'.$foto_path.'" class="avatar-img shadow-sm" alt="foto">';
        } else {
            $avatar = '<div class="avatar-icon-css shadow-sm" style="background:linear-gradient(135deg,#7c3aed22,#a855f722);">
                           <i class="bi bi-person-badge-fill" style="color:#7c3aed;"></i>
                       </div>';
        }

        echo '
        <div class="attendance-item">
            <div class="d-flex align-items-center w-100">
                <div class="flex-shrink-0">'.$avatar.'</div>
                <div class="flex-grow-1 ms-3">
                    <div class="student-name">'.htmlspecialchars($row['nama']).'</div>
                    <div class="student-class">'.htmlspecialchars($row['kelas'] ?? 'Guru').'</div>
                </div>
                <div class="text-end">
                    <span class="badge-status border '.$badge_class.'">'.$status_label.'</span>
                    <div class="attendance-time">'.$jam_tampil.'</div>
                </div>
            </div>
        </div>';
    }
} else {
    echo '
    <div class="text-center py-5">
        <i class="bi bi-person-badge fs-1 text-muted opacity-25"></i>
        <p class="text-muted small fw-bold mt-2">Belum ada aktivitas guru hari ini</p>
    </div>';
}
?>
