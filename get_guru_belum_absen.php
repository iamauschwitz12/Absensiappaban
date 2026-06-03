<?php
include 'koneksi.php';

$tgl = date('Y-m-d');

// Guru yang belum ada record absensi hari ini
$sql = "SELECT g.nama, g.jabatan, g.foto
        FROM guru g
        WHERE g.nip NOT IN (
            SELECT ag.nip FROM absensi_guru ag WHERE DATE(ag.waktu_masuk) = '$tgl'
        )
        ORDER BY g.nama ASC
        LIMIT 20";

$query = mysqli_query($conn, $sql);

if (mysqli_num_rows($query) > 0) {
    while ($row = mysqli_fetch_assoc($query)) {
        $foto_path = "img/guru/" . $row['foto'];
        if (!empty($row['foto']) && file_exists($foto_path)) {
            $avatar = '<img src="' . $foto_path . '" style="width:32px;height:32px;border-radius:8px;object-fit:cover;" alt="">';
        } else {
            $avatar = '<div style="width:32px;height:32px;border-radius:8px;background:#ede9fe;display:flex;align-items:center;justify-content:center;">
                           <i class="bi bi-person-badge-fill" style="color:#7c3aed;font-size:.9rem;"></i>
                       </div>';
        }

        echo '<div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid rgba(0,0,0,0.04);">'
            . $avatar
            . '<div style="flex:1;min-width:0;">'
            .   '<div style="font-size:.75rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'
            .       htmlspecialchars($row['nama'])
            .   '</div>'
            .   '<div style="font-size:.65rem;color:#94a3b8;">' . htmlspecialchars($row['jabatan'] ?? 'Guru') . '</div>'
            . '</div>'
            . '<i class="bi bi-dot text-danger" style="font-size:1.4rem;flex-shrink:0;"></i>'
            . '</div>';
    }
} else {
    echo '<div class="text-center py-3" style="font-size:.75rem;color:#10b981;font-weight:700;">
              <i class="bi bi-check-circle-fill me-1"></i>Semua guru sudah absen!
          </div>';
}
?>
