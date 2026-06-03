<?php
// simpan_wajah_guru.php
session_start();
include 'koneksi.php';

ob_clean();
header('Content-Type: application/json');

try {
    // --- VALIDASI CSRF ---
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Token CSRF tidak valid");
    }

    if (!isset($_POST['id']) || !isset($_POST['descriptor'])) {
        throw new Exception("Data input tidak lengkap");
    }

    $id_target = (int)$_POST['id'];
    $descriptor_baru_json = $_POST['descriptor'];
    $descriptor_baru = json_decode($descriptor_baru_json);

    if (!$descriptor_baru || count($descriptor_baru) < 128) {
        throw new Exception("Format descriptor wajah rusak atau terlalu pendek");
    }

    // --- CEK DUPLIKAT di tabel guru (threshold 0.75) ---
    $DUPLICATE_THRESHOLD = 0.75;

    $query = mysqli_query($conn, "SELECT id, nama, face_embedding FROM guru WHERE face_embedding IS NOT NULL AND id != '$id_target'");

    if (!$query) {
        throw new Exception("Gagal query database guru.");
    }

    while ($row = mysqli_fetch_assoc($query)) {
        $descriptor_db = json_decode($row['face_embedding']);
        if (!$descriptor_db || count($descriptor_db) !== count($descriptor_baru)) continue;

        // Hitung cosine similarity
        $dot = 0; $na = 0; $nb = 0;
        for ($i = 0; $i < count($descriptor_baru); $i++) {
            $dot += $descriptor_baru[$i] * $descriptor_db[$i];
            $na  += $descriptor_baru[$i] * $descriptor_baru[$i];
            $nb  += $descriptor_db[$i]   * $descriptor_db[$i];
        }
        $denom = sqrt($na) * sqrt($nb);
        $similarity = ($denom > 0) ? ($dot / $denom) : 0;

        if ($similarity > $DUPLICATE_THRESHOLD) {
            echo json_encode([
                'status' => 'duplicate',
                'owner'  => $row['nama'],
                'similarity' => round($similarity * 100, 1)
            ]);
            exit;
        }
    }

    // --- SIMPAN ke tabel guru ---
    $stmt = $conn->prepare("UPDATE guru SET face_embedding = ? WHERE id = ?");
    $stmt->bind_param("si", $descriptor_baru_json, $id_target);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception("Gagal menyimpan ke database guru");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;
