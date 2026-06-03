<?php
require_once 'libs/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

// Menyiapkan data template dengan kolom Sesi di akhir (Kolom H)
$data = [
    ['No', 'NIS', 'Nama Siswa', 'Kelas', 'No Whatsapp', 'Id_Telegram', 'Email', 'Sesi'],
    ['1', '1001', 'Budi Santoso', '10 TKJ', '628123456789', '886521209', 'budi@gmail.com', '1']
];

// Menghasilkan file Excel
$xlsx = SimpleXLSXGen::fromArray($data);

// Memberikan instruksi download kepada browser
$xlsx->downloadAs('Template_Import_Siswa_Sesi.xlsx');