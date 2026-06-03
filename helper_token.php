<?php
// File: helper_token.php

function getOrUpdateToken($conn) {
    // 1. SET ZONA WAKTU (PENTING AGAR TIDAK ERROR 400 MENIT)
    date_default_timezone_set('Asia/Jakarta');

    // 2. Ambil data token saat ini (ID selalu 1)
    $query = mysqli_query($conn, "SELECT * FROM kiosk_tokens WHERE id = 1");
    $data = mysqli_fetch_assoc($query);

    $now = date('Y-m-d H:i:s');

    // 3. Cek apakah token sudah kadaluarsa (atau data belum ada)
    // Jika data kosong ATAU waktu sekarang > waktu expired
    if (!$data || $now > $data['expires_at']) {
        
        // --- BUAT TOKEN BARU ---
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $newToken = substr(str_shuffle($chars), 0, 6); 
        
        // UBAH JADI 5 MENIT
        $newExpired = date('Y-m-d H:i:s', strtotime('+5 minutes')); 

        // Update Database 
        // Kita gunakan ON DUPLICATE KEY UPDATE agar jika tabel kosong dia Insert, jika ada dia Update
        // Atau cara simpel: Cek dulu kosong atau tidak
        
        if(!$data) {
            // Jika tabel kosong melompong (baru pertama kali)
            $sql = "INSERT INTO kiosk_tokens (id, token, updated_at, expires_at) VALUES (1, '$newToken', '$now', '$newExpired')";
        } else {
            // Jika sudah ada data, kita timpa
            $sql = "UPDATE kiosk_tokens SET 
                   token = '$newToken', 
                   updated_at = '$now', 
                   expires_at = '$newExpired' 
                   WHERE id = 1";
        }
                   
        mysqli_query($conn, $sql);

        return [
            'token' => $newToken,
            'expires_at' => $newExpired,
            'status' => 'new'
        ];

    } else {
        // --- TOKEN MASIH BERLAKU ---
        return [
            'token' => $data['token'],
            'expires_at' => $data['expires_at'],
            'status' => 'active'
        ];
    }
}
?>