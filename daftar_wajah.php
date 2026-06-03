<?php
session_start();
include 'koneksi.php';

// --- SECURITY: INISIALISASI CSRF TOKEN & XSS ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// 1. PROTEKSI ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: data_siswa.php"); exit;
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();

if (!$s) {
    echo "<script>alert('Siswa tidak ditemukan!'); window.location='data_siswa.php';</script>";
    exit;
}

// Cek apakah sudah ada data wajah (asumsi kolomnya bernama face_embedding)
$sudah_ada_wajah = !empty($s['face_embedding']);

// Ambil pengaturan sekolah
$q_set = mysqli_query($conn, "SELECT logo_sekolah, nama_sekolah FROM pengaturan WHERE id=1");
$res_set = mysqli_fetch_assoc($q_set);
$logo = $res_set['logo_sekolah'] ?? 'logo.png';
$nama_sekolah = $res_set['nama_sekolah'] ?? 'Asofa School';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometrik Wajah - <?= xss($s['nama']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/human/dist/human.js"></script>

    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f0f3f9;
            background-image: radial-gradient(at 0% 0%, rgba(13, 110, 253, 0.08) 0px, transparent 50%);
            min-height: 100vh;
        }
        .navbar-custom { background: linear-gradient(90deg, #0d6efd 0%, #6610f2 100%); border: none; }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
        }

        .video-wrapper { 
            position: relative; 
            width: 100%; 
            max-width: 480px; 
            margin: auto; 
            border-radius: 25px; 
            overflow: hidden; 
            background: #000;
            border: 5px solid #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        video { width: 100%; transform: scaleX(-1); display: block; }
        
        canvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform: scaleX(-1); pointer-events: none; }

        .status-float {
            position: absolute; top: 15px; left: 15px; z-index: 10;
            padding: 8px 15px; border-radius: 50px; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;
        }

        .btn-vibrant { border-radius: 15px; padding: 12px 25px; font-weight: 700; transition: 0.3s; }
        .btn-vibrant:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container text-center">
        <a class="navbar-brand fw-800 mx-auto" href="#"><i class="bi bi-shield-lock me-2"></i>REGISTRASI BIOMETRIK</a>
    </div>
</nav>

<div class="container py-2">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="glass-card p-4 text-center">
                <div class="mb-4">
                    <h5 class="fw-800 mb-1"><?= xss($s['nama']) ?></h5>
                    <p class="text-muted small mb-0">NIS: <?= xss($s['nis']) ?> | Kelas: <?= xss($s['kelas']) ?></p>
                </div>

                <div class="video-wrapper mb-4">
                    <span id="status-badge" class="badge bg-warning status-float">Menyiapkan Kamera...</span>
                    <video id="video" autoplay muted playsinline></video>
                    <canvas id="canvas"></canvas>
                </div>

                <div id="instruction-text" class="alert alert-light border-0 small mb-4 shadow-sm">
                    <i class="bi bi-hourglass-split me-2"></i>Sedang memuat sistem AI...
                </div>

                <div class="d-grid gap-2">
                    <button id="btn-capture" class="btn btn-primary btn-vibrant" disabled>
                        <i class="bi bi-camera-fill me-2"></i>DAFTARKAN WAJAH SEKARANG
                    </button>

                    <?php if($sudah_ada_wajah): ?>
                        <button id="btn-reset" class="btn btn-danger btn-vibrant bg-opacity-75">
                            <i class="bi bi-trash3-fill me-2"></i>HAPUS DATA WAJAH LAMA
                        </button>
                    <?php endif; ?>

                    <a href="data_siswa.php" class="btn btn-light btn-vibrant mt-2">KEMBALI</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const video = document.getElementById('video');
    const btnCapture = document.getElementById('btn-capture');
    const statusBadge = document.getElementById('status-badge');
    const instructionText = document.getElementById('instruction-text');
    
    // Konfigurasi Human AI (Dioptimalkan agar ringan)
    const humanConfig = {
        backend: 'webgl',
        modelBasePath: 'https://vladmandic.github.io/human/models/',
        face: {
            enabled: true,
            detector: { return: true, rotation: true },
            description: { enabled: true }, // Penting untuk rekam data
            mesh: { enabled: false }, // Matikan mesh agar ringan
            iris: { enabled: false }
        },
        body: { enabled: false },
        hand: { enabled: false }
    };

    const human = new Human.Human(humanConfig);
    let isDetecting = false; // Flag agar tidak bentrok

    async function setupCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: 'user' } 
            });
            video.srcObject = stream;
            
            video.onloadedmetadata = async () => {
                video.play();
                statusBadge.innerText = "Memuat AI...";
                await human.load();
                await human.warmup();
                statusBadge.innerText = "Sistem Siap";
                statusBadge.className = "badge bg-success status-float";
                btnCapture.disabled = false;
                instructionText.innerHTML = "Wajah terdeteksi. Silakan klik tombol untuk mendaftar.";
            };
        } catch (err) {
            console.error(err);
            statusBadge.innerText = "Kamera Error";
        }
    }

    // Fungsi Capture yang diperbaiki
    btnCapture.addEventListener('click', async () => {
        try {
            btnCapture.disabled = true;
            instructionText.innerHTML = "<i class='bi bi-hourglass-split'></i> Menganalisa wajah... Mohon tunggu.";
            
            // Lakukan deteksi tunggal
            const result = await human.detect(video);
            
            console.log("Hasil AI:", result); // Cek di Console F12

            if (!result.face || result.face.length === 0) {
                alert("Wajah tidak terdeteksi dengan jelas! Pastikan wajah terlihat penuh.");
                resetUI();
                return;
            }

            // Ambil data embedding
            const embedding = result.face[0].embedding;
            if (!embedding) {
                alert("AI Gagal mengekstrak fitur wajah. Coba posisi lain.");
                resetUI();
                return;
            }

            const faceData = JSON.stringify(Array.from(embedding));
            
            // Kirim ke server
            const formData = new URLSearchParams();
            formData.append('id', '<?= $id ?>');
            formData.append('descriptor', faceData);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

            const response = await fetch('simpan_wajah.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            console.log("Respon Server:", data);

            if (data.status === 'success') {
                alert("Pendaftaran Wajah Berhasil!");
                window.location.href = 'data_siswa.php';
            } else if (data.status === 'duplicate') {
                alert("GAGAL! Wajah ini sudah milik: " + data.owner);
                resetUI();
            } else {
                alert("Gagal: " + data.message);
                resetUI();
            }

        } catch (error) {
            console.error("Client Error:", error);
            alert("Terjadi kesalahan sistem: " + error.message);
            resetUI();
        }
    });

    function resetUI() {
        btnCapture.disabled = false;
        instructionText.innerHTML = "Silakan coba lagi.";
    }

    // Ambil referensi tombol reset
    const btnReset = document.getElementById('btn-reset');

    // Tambahkan logika jika tombol tersebut ada di layar
    if (btnReset) {
        btnReset.addEventListener('click', async () => {
            // 1. Konfirmasi ke user
            if (!confirm("Apakah Anda yakin ingin menghapus data wajah siswa ini?")) {
                return;
            }

            try {
                btnReset.disabled = true;
                btnReset.innerHTML = "<i class='bi bi-hourglass-split'></i> Menghapus...";

                // 2. Kirim permintaan ke server
                const formData = new URLSearchParams();
                formData.append('id', '<?= $id ?>');
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

                const response = await fetch('hapus_wajah.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success') {
                    alert("Data wajah berhasil dihapus!");
                    // Muat ulang halaman agar UI berubah (tombol hapus hilang)
                    window.location.reload();
                } else {
                    alert("Gagal menghapus: " + data.message);
                    btnReset.disabled = false;
                    btnReset.innerHTML = "<i class='bi bi-trash3-fill me-2'></i>HAPUS DATA WAJAH LAMA";
                }
            } catch (error) {
                console.error("Error:", error);
                alert("Terjadi kesalahan koneksi.");
                btnReset.disabled = false;
            }
        });
    }
    window.onload = setupCamera;
</script>

</body>
</html>