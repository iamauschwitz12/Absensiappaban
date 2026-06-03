<?php
session_start();
include 'koneksi.php';

if(!isset($_SESSION['login'])){
    header("location: login.php");
    exit;
}

// Menangkap ID dan TIPE dari URL
$id = $_GET['id'];
$tipe = isset($_GET['tipe']) ? $_GET['tipe'] : 'siswa'; 

$query = mysqli_query($conn, "SELECT * FROM siswa WHERE id = '$id'");
$s = mysqli_fetch_assoc($query);

// Ambil Nama Sekolah
$set = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_sekolah FROM pengaturan WHERE id=1"));

if(!$s){
    echo "Data tidak ditemukan";
    exit;
}

// Logika Label dan Warna Kartu
if ($tipe == 'guru') {
    $label_kartu = "KARTU TANDA GURU";
    $aksen_warna = "#e11d48"; 
} else {
    $label_kartu = "KARTU TANDA SISWA";
    $aksen_warna = "#0ea5e9"; 
}

// Logika Cek Foto
$punya_foto = false;
$foto_path = "img/siswa/" . $s['foto'];
if (!empty($s['foto']) && file_exists($foto_path)) {
    $punya_foto = true;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ID Card - <?= $s['nama'] ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary: #0f172a; 
            --accent: <?= $aksen_warna ?>; 
            --bg-page: #f1f5f9;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-page);
            display: flex; 
            flex-direction: column;
            align-items: center; 
            padding: 40px 0;
            min-height: 100vh;
        }

        /* --- ACTION BAR --- */
        .action-bar {
            margin-bottom: 25px;
            display: flex;
            gap: 12px;
            background: white;
            padding: 10px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .btn {
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-print { background: var(--primary); color: white; }
        .btn-download { background: white; color: var(--primary); border: 1px solid var(--primary); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

        /* --- CARD DESIGN --- */
        .id-card {
            width: 5.5cm;
            height: 8.6cm;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            border: 1px solid #e2e8f0;
        }

        .card-header {
            height: 110px;
            background: var(--primary);
            position: relative;
            z-index: 1;
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0% 100%);
        }

        .school-info {
            position: relative;
            z-index: 2;
            text-align: center;
            padding-top: 15px;
            color: white;
        }

        .school-name {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .card-label {
            font-size: 8px;
            font-weight: 600;
            letter-spacing: 1.5px;
            margin-top: 4px;
            color: var(--accent);
        }

        /* --- PHOTO & PLACEHOLDER --- */
        .photo-wrapper {
            width: 85px;
            height: 85px;
            margin: -40px auto 0; 
            position: relative;
            z-index: 5;
            padding: 4px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .photo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .photo-placeholder {
            width: 100%;
            height: 100%;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e1;
        }

        .photo-placeholder i {
            font-size: 45px;
        }

        /* --- BODY INFO --- */
        .student-body {
            text-align: center;
            padding: 10px 15px 5px;
            flex-grow: 1;
        }

        .st-name {
            font-size: 13px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 2px;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .st-nis {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 5px;
        }

        .st-class {
            font-size: 9px;
            color: var(--accent);
            font-weight: 700;
            text-transform: uppercase;
        }

        .qr-area {
            background: white;
            padding: 8px;
            display: flex;
            justify-content: center;
            border-top: 1px dashed #cbd5e1;
            margin: 0 15px 15px;
            border-radius: 8px;
        }

        .card-footer-strip {
            height: 6px;
            background: var(--accent);
            width: 100%;
            margin-top: auto;
        }

        @media print {
            body { background: white; padding: 0; }
            .action-bar, .tips-text { display: none; }
            .id-card { box-shadow: none; border: 1px solid #eee; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <button class="btn btn-print" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak Kartu
        </button>
        <button class="btn btn-download" onclick="downloadCard()">
            <i class="bi bi-download"></i> Simpan Gambar
        </button>
    </div>

    

    <div id="captureArea">
        <div class="id-card">
            <div class="card-header">
                <div class="school-info">
                    <div class="school-name"><?= htmlspecialchars($set['nama_sekolah']) ?></div>
                    <div class="card-label"><?= $label_kartu ?></div>
                </div>
            </div>

            <div class="photo-wrapper">
                <?php if ($punya_foto): ?>
                    <img src="<?= $foto_path ?>?t=<?= time() ?>" alt="Foto">
                <?php else: ?>
                    <div class="photo-placeholder">
                        <i class="bi bi-person-fill"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="student-body">
                <div class="st-name"><?= $s['nama'] ?></div>
                <div class="st-nis"><?= $s['nis'] ?></div><br>
                <div class="st-class"><?= $s['kelas'] ?></div>
            </div>

            <div class="qr-area">
                <div id="qrcode"></div>
            </div>

            <div class="card-footer-strip"></div>
        </div>
    </div>

    <div class="tips-text" style="margin-top:15px; font-size:11px; color:#94a3b8;">
        Ukuran Standar ID Card • 5.5cm x 8.6cm
    </div>

    <script>
        var qrcode = new QRCode(document.getElementById("qrcode"), {
            text: "<?= $s['nis'] ?>",
            width: 70,
            height: 70,
            colorDark : "#1e293b",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        function downloadCard() {
            var element = document.getElementById("captureArea");
            html2canvas(element, {
                scale: 4, 
                useCORS: true,
                backgroundColor: null,
            }).then(canvas => {
                var link = document.createElement('a');
                link.download = 'ID_<?= $tipe ?>_<?= str_replace(" ", "_", $s['nama']) ?>.png';
                link.href = canvas.toDataURL("image/png");
                link.click();
            });
        }
    </script>
</body>
</html>