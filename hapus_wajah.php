<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

// Validasi CSRF & Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['status' => 'error', 'message' => 'Token CSRF tidak valid']);
    exit;
}

$id = (int)$_POST['id'];

if ($id > 0) {
    // Kosongkan kolom face_embedding (sesuaikan nama kolomnya jika berbeda)
    $stmt = $conn->prepare("UPDATE siswa SET face_embedding = NULL WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate database']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
}