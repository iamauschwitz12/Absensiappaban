<?php
session_start();
include 'koneksi.php';

if(!isset($_SESSION['login'])){
    header("location: login.php");
    exit;
}

$kelas = mysqli_real_escape_string($conn, $_GET['kelas']);
$query = mysqli_query($conn, "SELECT * FROM siswa WHERE kelas = '$kelas' ORDER BY nama ASC");
$set = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_sekolah FROM pengaturan WHERE id=1"));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Kartu Kelas <?= $kelas ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0f172a;
            --accent: #3b82f6;
        }
        
        * { box-sizing: border-box; }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: #f1f5f9; 
            padding: 20px; 
            margin: 0;
        }
        
        /* Pengaturan Halaman A4 */
        @page {
            size: A4;
            margin: 1cm;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            max-width: 210mm; /* Lebar A4 */
            margin: 0 auto;
        }

        .card {
            width: 100%;
            height: 9.5cm;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            page-break-inside: avoid;
            /* Memastikan warna background & gradient muncul saat cetak PDF */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Desain Header */
        .header {
            height: 90px;
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 15px 10px;
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0% 100%);
        }

        .header h4 { margin: 0; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 7px; opacity: 0.7; font-weight: 600; }

        /* Lingkaran Foto */
        .photo-box {
            width: 80px;
            height: 80px;
            margin: -40px auto 10px;
            background: white;
            padding: 3px;
            border-radius: 50%;
            z-index: 2;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .photo-box img {
            width: 100%; height: 100%;
            object-fit: cover; border-radius: 50%;
        }

        .photo-placeholder {
            width: 100%; height: 100%;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #cbd5e1;
        }

        /* Detail Info */
        .info { padding: 0 15px; text-align: center; flex-grow: 1; }
        .nama { 
            font-weight: 800; font-size: 13px; color: var(--primary); 
            margin: 5px 0 2px; height: 32px; overflow: hidden; 
            display: flex; align-items: center; justify-content: center;
            text-transform: uppercase;
        }
        .nis { font-size: 10px; color: #64748b; font-weight: 600; margin-bottom: 5px; }
        .kelas-tag { 
            background: #eff6ff; color: var(--accent); 
            font-size: 8px; padding: 2px 10px; border-radius: 20px; 
            font-weight: 800; text-transform: uppercase;
        }

        .qrcode-box {
            margin: 12px auto;
            width: 90px; height: 90px;
            display: flex; justify-content: center;
        }

        .footer {
            background: #f8fafc;
            padding: 8px 0;
            font-size: 7px;
            color: #94a3b8;
            font-weight: 700;
            text-align: center;
            border-top: 1px dashed #e2e8f0;
        }

        .btn-print {
            position: fixed; top: 20px; right: 20px;
            background: #0f172a; color: white; padding: 12px 25px;
            border: none; border-radius: 50px; cursor: pointer; z-index: 1000;
            font-weight: 700; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        @media print {
            body { background: white; padding: 0; }
            .btn-print { display: none; }
            .card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">
    <i class="bi bi-printer"></i> CETAK KE PDF / PRINTER
</button>

<div class="grid-container">
    <?php while($s = mysqli_fetch_assoc($query)): 
        $foto_path = "img/siswa/" . $s['foto'];
        $punya_foto = (!empty($s['foto']) && file_exists($foto_path)) ? true : false;
    ?>
    <div class="card">
        <div class="header">
            <h4>KARTU ABSENSI</h4>
            <p><?= htmlspecialchars($set['nama_sekolah']) ?></p>
        </div>
        
        <div class="photo-box">
            <?php if($punya_foto): ?>
                <img src="<?= $foto_path ?>?t=<?= time() ?>">
            <?php else: ?>
                <div class="photo-placeholder">
                    <svg width="40" height="40" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                        <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
                    </svg>
                </div>
            <?php endif; ?>
        </div>

        <div class="info">
            <div class="nama"><?= $s['nama'] ?></div>
            <div class="nis"><?= $s['nis'] ?></div>
            <span class="kelas-tag"><?= $s['kelas'] ?></span>

            <div class="qrcode-box" id="qr-<?= $s['nis'] ?>"></div>
        </div>

        <div class="footer"></div>
    </div>

    <script>
        new QRCode(document.getElementById("qr-<?= $s['nis'] ?>"), {
            text: "<?= $s['nis'] ?>",
            width: 250,
            height: 250,
            colorDark : "#0f172a",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
    </script>
    <?php endwhile; ?>
</div>



</body>
</html>