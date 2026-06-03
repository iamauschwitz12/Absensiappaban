<?php
session_start();
include 'koneksi.php';

if(!isset($_SESSION['login'])){
    header("location: login.php");
    exit;
}

$kelas = $_GET['kelas'] ?? '';
if(empty($kelas)) die("Kelas tidak ditentukan.");

// Ambil Data Siswa berdasarkan kelas
$query = mysqli_query($conn, "SELECT * FROM siswa WHERE kelas = '$kelas' ORDER BY nama ASC");

// Ambil Nama Sekolah
$set_sch = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_sekolah FROM pengaturan WHERE id=1"));
$nama_sekolah = $set_sch['nama_sekolah'] ?? 'Sistem Absensi';

// Warna Aksen (Default Biru untuk Siswa)
$aksen_warna = "#0ea5e9";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Generating ZIP - <?= htmlspecialchars($kelas) ?></title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #0f172a; 
            --accent: <?= $aksen_warna ?>; 
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f1f5f9; }

        /* Area kartu disembunyikan agar tidak mengganggu pandangan */
        #captureArea {
            position: absolute;
            left: -9999px;
            top: 0;
        }

        /* COPY DESAIN DARI cetak_kartu.php */
        .id-card {
            width: 5.5cm;
            height: 8.6cm;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px; /* Jarak antar kartu saat proses */
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

        .photo-wrapper {
            width: 85px; height: 85px;
            margin: -40px auto 0; 
            position: relative;
            z-index: 5;
            padding: 4px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .photo-wrapper img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .photo-placeholder {
            width: 100%; height: 100%;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e1;
        }

        .student-body {
            text-align: center;
            padding: 10px 15px 5px;
            flex-grow: 1;
        }

        .st-name {
            font-size: 13px;
            font-weight: 800;
            color: var(--primary);
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
            margin-top: 5px;
        }

        .st-class {
            font-size: 9px;
            color: var(--accent);
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 5px;
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
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div id="status-card" class="card border-0 shadow-sm rounded-4 p-4 text-center">
                <div id="loader" class="spinner-border text-primary mb-3"></div>
                <h4 class="fw-bold mb-1">Memproses Kartu...</h4>
                <p class="text-muted small">Kelas: <?= htmlspecialchars($kelas) ?></p>
                
                <div class="progress rounded-pill mb-3" style="height: 12px;">
                    <div id="prog-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                </div>
                <p class="small fw-bold text-primary" id="prog-text">0 / 0 Siswa</p>
            </div>
        </div>
    </div>
</div>

<div id="captureArea">
    <?php while($row = mysqli_fetch_assoc($query)): 
        $foto_path = "img/siswa/" . $row['foto'];
        $punya_foto = (!empty($row['foto']) && file_exists($foto_path));
    ?>
        <div class="id-card" id="card-<?= $row['id'] ?>" data-nama="<?= str_replace(' ', '_', $row['nama']) ?>" data-nis="<?= $row['nis'] ?>">
            <div class="card-header">
                <div class="school-info">
                    <div class="school-name"><?= htmlspecialchars($nama_sekolah) ?></div>
                    <div class="card-label">KARTU TANDA SISWA</div>
                </div>
            </div>

            <div class="photo-wrapper">
                <?php if ($punya_foto): ?>
                    <img src="<?= $foto_path ?>" alt="Foto">
                <?php else: ?>
                    <div class="photo-placeholder"><i class="bi bi-person-fill" style="font-size: 40px;"></i></div>
                <?php endif; ?>
            </div>

            <div class="student-body">
                <div class="st-name"><?= $row['nama'] ?></div>
                <div class="st-nis"><?= $row['nis'] ?></div><br>
                <div class="st-class"><?= $row['kelas'] ?></div>
            </div>

            <div class="qr-area">
                <div class="qrcode-box" id="qr-<?= $row['id'] ?>"></div>
            </div>

            <div class="card-footer-strip"></div>
        </div>
    <?php endwhile; ?>
</div>

<script>
    async function startProcess() {
        const zip = new JSZip();
        const cards = document.querySelectorAll('.id-card');
        const total = cards.length;
        
        if(total === 0) {
            alert("Tidak ada data siswa di kelas ini.");
            window.location.href = 'data_siswa.php';
            return;
        }

        document.getElementById('prog-text').innerText = `0 / ${total} Siswa`;

        // 1. Generate SEMUA QR Code Terlebih dahulu
        cards.forEach(card => {
            const id = card.id.replace('card-', '');
            const nis = card.getAttribute('data-nis');
            new QRCode(document.getElementById(`qr-${id}`), {
                text: nis,
                width: 70,
                height: 70,
                colorDark : "#1e293b",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        });

        // Tunggu sebentar agar QR code selesai di-render di DOM
        await new Promise(resolve => setTimeout(resolve, 1000));

        // 2. Loop Capture ke Gambar HD
        for (let i = 0; i < total; i++) {
            const card = cards[i];
            const namaSiswa = card.getAttribute('data-nama');
            
            const canvas = await html2canvas(card, {
                scale: 3, // HD Quality
                useCORS: true,
                backgroundColor: null
            });

            const imgData = canvas.toDataURL("image/png").split(',')[1];
            zip.file(`${namaSiswa}.png`, imgData, {base64: true});

            // Update UI
            const current = i + 1;
            const percent = (current / total) * 100;
            document.getElementById('prog-bar').style.width = percent + '%';
            document.getElementById('prog-text').innerText = `${current} / ${total} Siswa`;
        }

        // 3. Generate ZIP & Download
        zip.generateAsync({type:"blob"}).then(function(content) {
            saveAs(content, "ID_CARDS_KELAS_<?= $kelas ?>.zip");
            
            // Tampilan Selesai
            document.getElementById('status-card').innerHTML = `
                <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
                <h4 class="fw-bold">Proses Selesai!</h4>
                <p class="text-muted small">File ZIP berhasil dibuat.</p>
                <a href="data_siswa.php" class="btn btn-primary rounded-pill px-4">Kembali</a>
            `;
        });
    }

    // Jalankan otomatis saat halaman dimuat
    window.onload = startProcess;
</script>

</body>
</html>