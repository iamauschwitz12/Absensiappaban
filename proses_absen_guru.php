<?php
session_start();
include 'koneksi.php';

// --- AMBIL PENGATURAN ---
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id = 1");
$stmt_set->execute();
$pengaturan = $stmt_set->get_result()->fetch_assoc();

date_default_timezone_set($pengaturan['timezone'] ?? 'Asia/Jakarta');

$tgl_hari_ini  = date('Y-m-d');
$jam_sekarang  = date('H:i:s');
$waktu_lengkap = date('Y-m-d H:i:s');

// Patokan jam masuk/pulang guru (pakai sesi 1 sebagai acuan)
$jam_masuk_patokan  = $pengaturan['s1_masuk']  ?? '07:00:00';
$jam_pulang_patokan = $pengaturan['s1_pulang'] ?? '15:00:00';

if (isset($_POST['nip'])) {
    $nip = trim($_POST['nip']);

    // Cari data guru
    $stmt_guru = $conn->prepare("SELECT * FROM guru WHERE nip = ?");
    $stmt_guru->bind_param("s", $nip);
    $stmt_guru->execute();
    $guru = $stmt_guru->get_result()->fetch_assoc();

    if (!$guru) {
        echo json_encode(['status' => 'error', 'nama' => 'Gagal', 'pesan' => 'NIP Tidak Dikenal!']);
        exit;
    }

    $nama    = $guru['nama'];
    $jabatan = $guru['jabatan'] ?? 'Guru';
    $foto    = $guru['foto'];

    // Cek absensi hari ini
    $stmt_cek = $conn->prepare("SELECT * FROM absensi_guru WHERE nip = ? AND DATE(waktu_masuk) = ?");
    $stmt_cek->bind_param("ss", $nip, $tgl_hari_ini);
    $stmt_cek->execute();
    $data_absen = $stmt_cek->get_result()->fetch_assoc();

    if (!$data_absen) {
        // ABSEN MASUK
        $status = ($jam_sekarang > $jam_masuk_patokan) ? 'Terlambat' : 'Tepat Waktu';

        $stmt_ins = $conn->prepare("INSERT INTO absensi_guru (nip, waktu_masuk, status_kehadiran, keterangan) VALUES (?, ?, ?, 'Hadir')");
        $stmt_ins->bind_param("sss", $nip, $waktu_lengkap, $status);

        if ($stmt_ins->execute()) {
            echo json_encode([
                'status'  => 'success',
                'nama'    => $nama,
                'kelas'   => $jabatan,
                'foto'    => $foto,
                'tipe'    => 'guru',
                'pesan'   => "Absen Masuk Guru Berhasil! ($status)"
            ]);
        } else {
            echo json_encode(['status' => 'error', 'nama' => $nama, 'pesan' => 'Gagal menyimpan absensi guru.']);
        }

    } else {
        // Sudah absen masuk — cek absen pulang
        if ($jam_sekarang >= $jam_pulang_patokan) {
            if (empty($data_absen['waktu_pulang']) || $data_absen['waktu_pulang'] == '0000-00-00 00:00:00') {
                $stmt_upd = $conn->prepare("UPDATE absensi_guru SET waktu_pulang = ?, keterangan = 'Hadir' WHERE id = ?");
                $stmt_upd->bind_param("si", $waktu_lengkap, $data_absen['id']);
                $stmt_upd->execute();

                echo json_encode([
                    'status'  => 'success',
                    'nama'    => $nama,
                    'kelas'   => $jabatan,
                    'foto'    => $foto,
                    'tipe'    => 'guru',
                    'pesan'   => "Absen Pulang Guru Berhasil!"
                ]);
            } else {
                echo json_encode([
                    'status'  => 'warning',
                    'nama'    => $nama,
                    'kelas'   => $jabatan,
                    'foto'    => $foto,
                    'tipe'    => 'guru',
                    'pesan'   => "Anda sudah absen pulang hari ini!"
                ]);
            }
        } else {
            echo json_encode([
                'status'  => 'warning',
                'nama'    => $nama,
                'kelas'   => $jabatan,
                'foto'    => $foto,
                'tipe'    => 'guru',
                'pesan'   => "Sudah absen masuk hari ini!"
            ]);
        }
    }
}
?>
