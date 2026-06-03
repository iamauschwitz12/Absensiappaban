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

// 1. PROTEKSI LOGIN
if (!isset($_SESSION['login'])) {
    header("location: login.php"); exit;
}

// 2. PROTEKSI ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: data_guru.php"); exit;
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM guru WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$g = $stmt->get_result()->fetch_assoc();

if (!$g) {
    echo "<script>alert('Guru tidak ditemukan!'); window.location='data_guru.php';</script>";
    exit;
}

// Cek apakah sudah ada data wajah
$sudah_ada_wajah = !empty($g['face_embedding']);

// Ambil pengaturan sekolah
$q_set = mysqli_query($conn, "SELECT logo_sekolah, nama_sekolah FROM pengaturan WHERE id=1");
$res_set = mysqli_fetch_assoc($q_set);
$nama_sekolah = $res_set['nama_sekolah'] ?? 'Sistem Absensi';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometrik Wajah - <?= xss($g['nama']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/human/dist/human.js"></script>

    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f0f3f9;
            background-image: radial-gradient(at 0% 0%, rgba(124, 58, 237, 0.08) 0px, transparent 50%);
            min-height: 100vh;
        }
        .navbar-custom { background: linear-gradient(90deg, #7c3aed 0%, #a855f7 100%); border: none; }
        
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

        .badge-guru {
            background: rgba(124, 58, 237, 0.1);
            color: #7c3aed;
            border: 1px solid rgba(124, 58, 237, 0.2);
            border-radius: 50px;
            padding: 4px 14px;
            font-size: 0.8rem;
            font-weight: 700;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container text-center">
        <a class="navbar-brand fw-800 mx-auto" href="#"><i class="bi bi-shield-lock me-2"></i>REGISTRASI BIOMETRIK GURU</a>
    </div>
</nav>

<div class="container py-2">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="glass-card p-4 text-center">
                <div class="mb-4">
                    <div class="mb-2">
                        <?php
                        $foto_path = "img/guru/" . $g['foto'];
                        if (!empty($g['foto']) && file_exists($foto_path)):
                        ?>
                        <img src="<?= $foto_path ?>" alt="<?= xss($g['nama']) ?>" 
                             style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid #7c3aed;">
                        <?php else: ?>
                        <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center" 
                             style="width:70px;height:70px;background:linear-gradient(135deg,#7c3aed,#a855f7);">
                            <i class="bi bi-person-badge-fill text-white fs-3"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h5 class="fw-800 mb-1"><?= xss($g['nama']) ?></h5>
                    <p class="text-muted small mb-1">NIP: <?= xss($g['nip']) ?></p>
                    <?php if(!empty($g['jabatan'])): ?>
                    <span class="badge-guru"><?= xss($g['jabatan']) ?></span>
                    <?php endif; ?>
                    <?php if($sudah_ada_wajah): ?>
                    <div class="mt-2"><span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-circle me-1"></i>Wajah sudah terdaftar</span></div>
                    <?php endif; ?>
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
                    <button id="btn-capture" class="btn btn-vibrant text-white" 
                            style="background: linear-gradient(135deg, #7c3aed, #a855f7);" disabled>
                        <i class="bi bi-camera-fill me-2"></i>DAFTARKAN WAJAH SEKARANG
                    </button>

                    <?php if($sudah_ada_wajah): ?>
                        <button id="btn-reset" class="btn btn-danger btn-vibrant bg-opacity-75">
                            <i class="bi bi-trash3-fill me-2"></i>HAPUS DATA WAJAH LAMA
                        </button>
                    <?php endif; ?>

                    <a href="data_guru.php" class="btn btn-light btn-vibrant mt-2">
                        <i class="bi bi-arrow-left me-2"></i>KEMBALI
                    </a>
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
    
    const humanConfig = {
        backend: 'webgl',
        modelBasePath: 'https://vladmandic.github.io/human/models/',
        face: {
            enabled: true,
            detector: { return: true, rotation: true },
            description: { enabled: true },
            mesh: { enabled: false },
            iris: { enabled: false }
        },
        body: { enabled: false },
        hand: { enabled: false }
    };

    const human = new Human.Human(humanConfig);

    async function setupCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
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
            statusBadge.className = "badge bg-danger status-float";
        }
    }

    btnCapture.addEventListener('click', async () => {
        try {
            btnCapture.disabled = true;
            instructionText.innerHTML = "<i class='bi bi-hourglass-split'></i> Menganalisa wajah... Mohon tunggu.";
            
            const result = await human.detect(video);

            if (!result.face || result.face.length === 0) {
                alert("Wajah tidak terdeteksi dengan jelas! Pastikan wajah terlihat penuh.");
                resetUI(); return;
            }

            const embedding = result.face[0].embedding;
            if (!embedding) {
                alert("AI Gagal mengekstrak fitur wajah. Coba posisi lain.");
                resetUI(); return;
            }

            const faceData = JSON.stringify(Array.from(embedding));
            
            const formData = new URLSearchParams();
            formData.append('id', '<?= $id ?>');
            formData.append('descriptor', faceData);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

            const response = await fetch('simpan_wajah_guru.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                alert("Pendaftaran Wajah Guru Berhasil!");
                window.location.href = 'data_guru.php';
            } else if (data.status === 'duplicate') {
                alert("GAGAL! Wajah ini sudah terdaftar atas nama: " + data.owner);
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

    const btnReset = document.getElementById('btn-reset');
    if (btnReset) {
        btnReset.addEventListener('click', async () => {
            if (!confirm("Apakah Anda yakin ingin menghapus data wajah guru ini?")) return;

            try {
                btnReset.disabled = true;
                btnReset.innerHTML = "<i class='bi bi-hourglass-split'></i> Menghapus...";

                const formData = new URLSearchParams();
                formData.append('id', '<?= $id ?>');
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

                const response = await fetch('hapus_wajah_guru.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success') {
                    alert("Data wajah guru berhasil dihapus!");
                    window.location.reload();
                } else {
                    alert("Gagal menghapus: " + data.message);
                    btnReset.disabled = false;
                    btnReset.innerHTML = "<i class='bi bi-trash3-fill me-2'></i>HAPUS DATA WAJAH LAMA";
                }
            } catch (error) {
                alert("Terjadi kesalahan koneksi.");
                btnReset.disabled = false;
            }
        });
    }

    window.onload = setupCamera;
</script>

</body>
</html>
