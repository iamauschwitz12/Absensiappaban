<?php
// simpan_wajah.php
session_start();
include 'koneksi.php';

// Bersihkan output buffer untuk mencegah spasi/karakter liar
ob_clean();
header('Content-Type: application/json');

try {
    if (!isset($_POST['id']) || !isset($_POST['descriptor'])) {
        throw new Exception("Data input tidak lengkap");
    }

    $id_target = (int)$_POST['id'];
    $descriptor_baru_json = $_POST['descriptor'];
    $descriptor_baru = json_decode($descriptor_baru_json);

    if (!$descriptor_baru) {
        throw new Exception("Format descriptor wajah rusak");
    }

    // --- 1. CEK APAKAH KOLOM face_embedding ADA ---
    // Jika belum ada, jalankan perintah SQL ini di database: 
    // ALTER TABLE siswa ADD COLUMN face_embedding LONGTEXT;

    // --- 2. CEK DUPLIKAT ---
    $query = mysqli_query($conn, "SELECT id, nama, face_embedding FROM siswa WHERE face_embedding IS NOT NULL AND id != '$id_target'");
    
    if (!$query) {
        throw new Exception("Gagal query database. Pastikan kolom face_embedding sudah dibuat.");
    }

    while($row = mysqli_fetch_assoc($query)) {
        $descriptor_db = json_decode($row['face_embedding']);
        if (!$descriptor_db) continue;

        // Hitung Similarity
        $dot = 0; $na = 0; $nb = 0;
        for($i=0; $i<count($descriptor_baru); $i++) {
            $dot += $descriptor_baru[$i] * $descriptor_db[$i];
            $na  += $descriptor_baru[$i] * $descriptor_baru[$i];
            $nb  += $descriptor_db[$i] * $descriptor_db[$i];
        }
        $similarity = $dot / (sqrt($na) * sqrt($nb));

        if($similarity > 0.90) {
            echo json_encode(['status' => 'duplicate', 'owner' => $row['nama']]);
            exit;
        }
    }

    // --- 3. SIMPAN ---
    $stmt = $conn->prepare("UPDATE siswa SET face_embedding = ? WHERE id = ?");
    $stmt->bind_param("si", $descriptor_baru_json, $id_target);

    if($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception("Gagal menyimpan ke database");
    }

} catch (Exception $e) {
    // Tangkap semua error dan kirim sebagai JSON agar JS tidak 'muter'
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;