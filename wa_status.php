<?php
include 'koneksi.php';

// Hitung pesan yang masih berstatus 'pending'
$query = mysqli_query($conn, "SELECT COUNT(*) as total FROM wa_queue WHERE status = 'pending'");
$data = mysqli_fetch_assoc($query);

echo json_encode(['pending' => $data['total']]);
?>