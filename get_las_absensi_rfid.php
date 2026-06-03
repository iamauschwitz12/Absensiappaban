<?php
include 'koneksi.php';

$tgl = date('Y-m-d');

// Paksa semua kolom string ke collation yang sama (general_ci) agar UNION tidak error
$sql = "
    SELECT
        a.waktu_masuk,
        a.waktu_pulang,
        a.status_kehadiran COLLATE utf8mb4_general_ci AS status_kehadiran,
        s.nama             COLLATE utf8mb4_general_ci AS nama,
        s.kelas            COLLATE utf8mb4_general_ci AS info,
        s.foto             COLLATE utf8mb4_general_ci AS foto,
        'siswa'                                       AS tipe
    FROM absensi a
    JOIN siswa s ON a.nis = s.nis
    WHERE DATE(a.waktu_masuk) = '$tgl'

    UNION ALL

    SELECT
        ag.waktu_masuk,
        ag.waktu_pulang,
        ag.status_kehadiran COLLATE utf8mb4_general_ci AS status_kehadiran,
        g.nama              COLLATE utf8mb4_general_ci AS nama,
        g.jabatan           COLLATE utf8mb4_general_ci AS info,
        g.foto              COLLATE utf8mb4_general_ci AS foto,
        'guru'                                         AS tipe
    FROM absensi_guru ag
    JOIN guru g ON ag.nip = g.nip
    WHERE DATE(ag.waktu_masuk) = '$tgl'

    ORDER BY GREATEST(
        IFNULL(waktu_masuk,  '1970-01-01 00:00:00'),
        IFNULL(waktu_pulang, '1970-01-01 00:00:00')
    ) DESC
    LIMIT 5
";

$query = mysqli_query($conn, $sql);

if ($query && mysqli_num_rows($query) > 0) {
    while ($row = mysqli_fetch_assoc($query)) {
        $isGuru = ($row['tipe'] === 'guru');

        // Logika Jam & Label
        if (!empty($row['waktu_pulang']) && $row['waktu_pulang'] != '0000-00-00 00:00:00') {
            $jam_tampil = date('H:i', strtotime($row['waktu_pulang']));
            $label      = "PULANG";
            $color      = "#ef4444";
        } elseif ($row['status_kehadiran'] === 'Terlambat') {
            $jam_tampil = date('H:i', strtotime($row['waktu_masuk']));
            $label      = "TELAT";
            $color      = "#f59e0b";
        } else {
            $jam_tampil = date('H:i', strtotime($row['waktu_masuk']));
            $label      = "MASUK";
            $color      = "#10b981";
        }

        // Logika foto / icon CSS
        $foto_dir  = $isGuru ? "img/guru/" : "img/siswa/";
        $foto_path = $foto_dir . $row['foto'];

        if (!empty($row['foto']) && file_exists($foto_path)) {
            $img_html = '<img src="'.$foto_path.'" class="log-img shadow-sm" alt="foto">';
        } else {
            $icon_style = $isGuru
                ? 'background:linear-gradient(135deg,#ede9fe,#f3e8ff);color:#7c3aed;'
                : 'background:#e2e8f0;color:#94a3b8;';
            $icon_name  = $isGuru ? 'bi-person-badge-fill' : 'bi-person-fill';
            $img_html   = '<div class="log-icon-css shadow-sm" style="'.$icon_style.'"><i class="bi '.$icon_name.'"></i></div>';
        }

        // Badge GURU kecil
        $tipe_badge = $isGuru
            ? '<span style="font-size:0.5rem;font-weight:800;background:rgba(124,58,237,0.12);color:#7c3aed;border-radius:4px;padding:1px 5px;display:inline-block;margin-bottom:2px;line-height:1.4;">GURU</span>'
            : '';

        echo '
        <div class="log-item shadow-sm">
            '.$img_html.'
            <div style="flex:1;min-width:0;">
                '.$tipe_badge.'
                <div class="log-name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'.htmlspecialchars($row['nama']).'</div>
                <div class="log-info">'.htmlspecialchars($row['info'] ?? '-').'</div>
            </div>
            <div class="text-end" style="flex-shrink:0;">
                <div class="log-time" style="background:'.$color.'15;color:'.$color.';">'.$jam_tampil.'</div>
                <div style="font-size:0.55rem;font-weight:800;color:#94a3b8;margin-top:3px;">'.$label.'</div>
            </div>
        </div>';
    }
} else {
    echo '<div class="text-center py-5 text-muted small">Belum ada aktivitas hari ini.</div>';
}
?>