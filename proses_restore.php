<?php
session_start();
include 'koneksi.php';

if($_SESSION['role'] !== 'admin') exit;

if(isset($_FILES['backup_file'])){
    $file = $_FILES['backup_file']['tmp_name'];
    $handle = fopen($file, "r");
    $contents = fread($handle, filesize($file));
    fclose($handle);

    // Membagi script SQL berdasarkan semicolon
    $queries = explode(';', $contents);
    $success = 0;

    foreach($queries as $query){
        if(trim($query) != ""){
            if(mysqli_query($conn, $query)){
                $success++;
            }
        }
    }

    if($success > 0){
        echo "<script>alert('Restore Berhasil! Silakan cek kembali data Anda.'); window.location='backup_database.php';</script>";
    } else {
        echo "<script>alert('Gagal melakukan restore.'); window.location='backup_database.php';</script>";
    }
}