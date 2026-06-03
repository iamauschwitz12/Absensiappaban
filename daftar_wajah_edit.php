<?php
session_start();
include 'koneksi.php';

// Proteksi ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('ID Siswa tidak ditemukan! Kembali ke Data Siswa.'); window.location='data_siswa.php';</script>";
    exit;
}

$id = mysqli_real_escape_string($conn, $_GET['id']);
$query = mysqli_query($conn, "SELECT * FROM siswa WHERE id = '$id'");
$s = mysqli_fetch_assoc($query);

$q_set = mysqli_query($conn, "SELECT logo_sekolah, timezone, nama_sekolah FROM pengaturan WHERE id=1");
$res_set = mysqli_fetch_assoc($q_set);
$logo = $res_set['logo_sekolah'] ?? 'logo.png';
$tz = $res_set['timezone'] ?? 'Asia/Jakarta';
$nama_sekolah = $res_set['nama_sekolah'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Wajah - <?= $s['nama'] ?></title>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #0f172a; color: white; font-family: sans-serif; }
        .navbar { background: #1e293b; border-bottom: 2px solid #3b82f6; }
        .main-card { background: rgba(255,255,255,0.05); border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); padding: 20px; }
        .video-box { position: relative; width: 100%; max-width: 500px; aspect-ratio: 4/3; margin: 0 auto; background: #000; border-radius: 15px; overflow: hidden; border: 3px solid #334155; }
        video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        select { background: #1e293b !important; color: #38bdf8 !important; border: 1px solid #334155 !important; }
        .instruction-badge { font-size: 0.8rem; background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid #3b82f6; border-radius: 50px; padding: 5px 15px; display: inline-block; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark p-3 mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <img src="img/<?= $logo ?>" width="30" height="30" class="bg-white rounded p-1">
            <span class="fw-bold">REGISTRASI WAJAH</span>
        </div>
        <div class="badge bg-dark border border-secondary p-2">
            <i class="bi bi-clock text-info me-2"></i><span id="live-clock">--:--:--</span>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="main-card shadow-lg">
                <h4 class="text-info fw-bold mb-1"><?= $s['nama'] ?></h4>
                <div class="instruction-badge mb-3" id="instruction">Menyiapkan AI...</div>

                <div class="mb-3 text-start">
                    <label class="small fw-bold text-info mb-1"><i class="bi bi-camera me-1"></i> Pilih Kamera:</label>
                    <select id="camera-list" class="form-select shadow-none">
                        <option value="">Mengambil daftar kamera...</option>
                    </select>
                </div>

                <div class="video-box mb-4">
                    <video id="video" autoplay muted playsinline></video>
                </div>

                <div id="status-text" class="alert alert-dark border-secondary text-info py-2 small fw-bold mb-4">
                    <span class="spinner-border spinner-border-sm me-2"></span> Mengaktifkan Kamera...
                </div>

                <div class="d-flex gap-2">
                    <button id="capture" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow" disabled>
                        <i class="bi bi-person-check-fill me-2"></i>DAFTARKAN WAJAH
                    </button>
                    <a href="data_siswa.php" class="btn btn-outline-light w-50 py-3 fw-bold rounded-pill">BATAL</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const video = document.getElementById('video');
    const cameraList = document.getElementById('camera-list');
    const statusText = document.getElementById('status-text');
    const instruction = document.getElementById('instruction');
    const btnCapture = document.getElementById('capture');
    let currentStream = null;
    let isLive = false; // Flag untuk verifikasi kedipan

    // --- 1. JAM ---
    function updateClock() {
        const tz = '<?= $tz ?>';
        try {
            const now = new Intl.DateTimeFormat('en-US', {
                timeZone: tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
            }).format(new Date());
            document.getElementById('live-clock').textContent = now;
        } catch (e) { document.getElementById('live-clock').textContent = new Date().toLocaleTimeString(); }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // --- 2. HITUNG EAR (Eye Aspect Ratio) ---
    function getEAR(eyePoints) {
        const p2_p6 = Math.sqrt(Math.pow(eyePoints[1].x - eyePoints[5].x, 2) + Math.pow(eyePoints[1].y - eyePoints[5].y, 2));
        const p3_p5 = Math.sqrt(Math.pow(eyePoints[2].x - eyePoints[4].x, 2) + Math.pow(eyePoints[2].y - eyePoints[4].y, 2));
        const p1_p4 = Math.sqrt(Math.pow(eyePoints[0].x - eyePoints[3].x, 2) + Math.pow(eyePoints[0].y - eyePoints[3].y, 2));
        return (p2_p6 + p3_p5) / (2.0 * p1_p4);
    }

    // --- 3. KAMERA ---
    async function initKamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            stream.getTracks().forEach(t => t.stop());

            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(device => device.kind === 'videoinput');
            
            cameraList.innerHTML = '';
            videoDevices.forEach((device, index) => {
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.text = device.label || `Kamera ${index + 1}`;
                cameraList.appendChild(option);
            });

            if (videoDevices.length > 0) {
                startStream(videoDevices[0].deviceId);
                initAI();
            }
        } catch (err) {
            statusText.innerHTML = "<span class='text-danger'>Akses Kamera Ditolak!</span>";
        }
    }

    async function startStream(id) {
        if (currentStream) currentStream.getTracks().forEach(t => t.stop());
        try {
            currentStream = await navigator.mediaDevices.getUserMedia({ video: { deviceId: id } });
            video.srcObject = currentStream;
        } catch (e) { console.error(e); }
    }

    // --- 4. AI & LIVENESS DETECTION ---
    async function initAI() {
        try {
            statusText.innerHTML = "<span class='spinner-border spinner-border-sm me-2'></span> Memuat AI...";
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri('./models'),
                faceapi.nets.faceLandmark68Net.loadFromUri('./models'),
                faceapi.nets.faceRecognitionNet.loadFromUri('./models')
            ]);
            statusText.innerHTML = "Sistem Siap.";
            instruction.innerHTML = "Silakan Berkedip untuk Verifikasi";
            startLivenessMonitor();
        } catch (err) {
            statusText.innerHTML = "<span class='text-danger'>Gagal muat model.</span>";
        }
    }

    function startLivenessMonitor() {
        setInterval(async () => {
            if (isLive || video.paused) return;

            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                                           .withFaceLandmarks();

            if (detection) {
                const landmarks = detection.landmarks;
                const leftEAR = getEAR(landmarks.getLeftEye());
                const rightEAR = getEAR(landmarks.getRightEye());
                const avgEAR = (leftEAR + rightEAR) / 2;

                // Jika mata tertutup (berkedip)
                if (avgEAR < 0.22) {
                    isLive = true;
                    btnCapture.disabled = false;
                    instruction.innerHTML = "<i class='bi bi-check-circle-fill text-success'></i> Verifikasi Berhasil! Silakan Klik Daftar";
                    statusText.innerHTML = "Wajah Manusia Asli Terdeteksi.";
                }
            }
        }, 150); // Scan cepat untuk menangkap kedipan
    }

    btnCapture.addEventListener('click', async () => {
        btnCapture.disabled = true;
        statusText.innerHTML = "Menganalisa & Menyimpan...";
        
        // Ambil descriptor wajah final
        const finalDetection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                                            .withFaceLandmarks()
                                            .withFaceDescriptor();
        
        if (!finalDetection) {
            alert("Wajah hilang! Ulangi verifikasi.");
            isLive = false;
            instruction.innerHTML = "Silakan Berkedip Kembali";
            return;
        }

        const faceData = JSON.stringify(Array.from(finalDetection.descriptor));
        fetch('simpan_wajah.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=<?= $id ?>&descriptor=${encodeURIComponent(faceData)}`
        })
        .then(res => res.text())
        .then(result => {
            if (result.includes("success")) {
                alert("Pendaftaran Berhasil!"); 
                window.location.href = 'data_siswa.php';
            } else { 
                alert(result); 
                btnCapture.disabled = false; 
            }
        });
    });

    cameraList.addEventListener('change', () => {
        isLive = false;
        btnCapture.disabled = true;
        instruction.innerHTML = "Silakan Berkedip Kembali";
        startStream(cameraList.value);
    });

    window.onload = initKamera;
</script>
</body>
</html>