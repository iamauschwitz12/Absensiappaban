<?php
// Memulai session untuk kebutuhan validasi CSRF token.
session_start();

// Mengimpor koneksi database (menghasilkan variabel `$conn` untuk query).
include 'koneksi.php';

// Pastikan response berupa JSON agar frontend mudah memproses hasilnya.
header('Content-Type: application/json');

// Validasi request hanya menerima metode POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    exit;
}

// Validasi CSRF token untuk mencegah request berbahaya/forged.
// - $_POST['csrf_token'] harus ada
// - harus sama dengan token yang disimpan pada session
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['status' => 'error', 'message' => 'Token CSRF tidak valid']);
    exit;
}

// Ambil dan normalisasi parameter `id` dari input POST.
// Casting ke int untuk menghindari input tidak valid.
$id = (int)$_POST['id'];

// Hanya proses update jika ID valid (> 0).
if ($id > 0) {
    // Kosongkan embedding wajah pada tabel `siswa`.
    // Menggunakan prepared statement untuk keamanan (parameter binding).
    $stmt = $conn->prepare("UPDATE siswa SET face_embedding = NULL WHERE id = ?");

    // Bind parameter ke query ("i" = integer).
    $stmt->bind_param("i", $id);

    // Eksekusi update database dan kembalikan status sesuai hasilnya.
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate database']);
    }
} else {
    // Jika ID tidak valid, kembalikan error tanpa menjalankan query.
    echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
}
