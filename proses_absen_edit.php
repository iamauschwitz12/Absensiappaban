<?php
session_start();
include 'koneksi.php';

// Ambil data pengaturan sekolah
$querySetting = mysqli_query($conn, "SELECT nama_sekolah, timezone FROM pengaturan WHERE id=1");
$pengaturan = mysqli_fetch_assoc($querySetting);
$nama_sekolah = htmlspecialchars($pengaturan['nama_sekolah']);
$timezone_aktif = $pengaturan['timezone'] ?? 'Asia/Jakarta';

// Ambil data siswa yang memiliki face_descriptor
$query = mysqli_query($conn, "SELECT nis, nama, kelas, foto, face_descriptor FROM siswa WHERE face_descriptor IS NOT NULL AND face_descriptor != ''");
$db_faces = [];
while($row = mysqli_fetch_assoc($query)){
    $db_faces[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="img/asofa.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Recognition - <?= $nama_sekolah; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root { --bg-dark: #0f172a; --accent: #3b82f6; --success: #10b981; --warning: #f59e0b; }
        body {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            color: white; font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .main-card {
            background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 30px;
            padding: 30px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); width: 700px; max-width: 95%;
        }
        .video-wrapper {
            position: relative; width: 100%; aspect-ratio: 4/3; border-radius: 20px;
            overflow: hidden; background: #000; border: 4px solid rgba(255, 255, 255, 0.2); margin: 0 auto;
        }
        video { width: 100%; height: 100%; object-fit: cover; }
        .mirror { transform: scaleX(-1); }
        
        .scanner-line {
            position: absolute; width: 80%; height: 2px; background: var(--accent);
            left: 10%; top: 50%; box-shadow: 0 0 15px var(--accent);
            animation: scanMove 3s infinite ease-in-out; z-index: 6;
        }
        @keyframes scanMove { 0%, 100% { top: 20%; opacity: 0.2; } 50% { top: 80%; opacity: 1; } }

        .camera-select-box {
            background: rgba(0, 0, 0, 0.3); color: white; border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px; padding: 10px; margin-bottom: 15px; width: 100%;
        }
        .modal-content { border-radius: 25px; color: #334155; border: none; overflow: hidden; }
        .modal-body img { width: 140px; height: 140px; object-fit: cover; border-radius: 50%; border: 5px solid #f1f5f9; }
        .badge-blink { font-size: 0.7rem; padding: 5px 10px; border-radius: 50px; background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid #3b82f6; }
    </style>
</head>
<body>

    <div class="main-card">
        <h2 class="fw-bold mb-1 text-uppercase small"><i class="bi bi-person-bounding-box me-2 text-info"></i>Face Absensi</h2>
        
        <div class="mt-3 mb-2 text-start">
            <label class="small fw-bold opacity-75 mb-1 ms-2"><i class="bi bi-camera me-1"></i> Pilih Kamera:</label>
            <select id="camera-list" class="form-select camera-select-box shadow-none">
                <option value="">Mencari Kamera...</option>
            </select>
        </div>

        <div class="video-wrapper">
            <video id="video" autoplay muted playsinline class="mirror"></video>
            <div class="scanner-line"></div>
        </div>

        <div id="status-text" class="mt-4 fs-6 text-warning fw-bold">
            <span class="spinner-border spinner-border-sm me-2"></span> Menyiapkan Sistem...
        </div>

        <div class="mt-4 d-flex justify-content-center gap-2">
            <div class="bg-dark bg-opacity-50 px-3 py-1 rounded-pill small border border-secondary">
                <i class="bi bi-clock me-2 text-info"></i><span id="live-clock">00:00:00</span>
            </div>
            <a href="index.php" class="btn btn-outline-light rounded-pill px-4 btn-sm">Mode QR/RFID</a>
        </div>
    </div>

    <div class="modal fade" id="modalAbsen" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div id="modal-header" class="p-3 text-center fw-bold text-white fs-5">STATUS</div>
                <div class="modal-body text-center p-5 text-dark">
                    <img id="m-foto" src="" class="mb-3 shadow-sm">
                    <h3 id="m-nama" class="fw-bold mb-0 text-uppercase"></h3>
                    <div class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill mb-3" id="m-kelas"></div>
                    <div class="fw-bold fs-4" id="m-pesan"></div>
                </div>
            </div>
        </div>
    </div>

    <audio id="snd-success" src="https://assets.mixkit.co/sfx/preview/mixkit-correct-answer-tone-2870.mp3"></audio>
    <audio id="snd-fail" src="https://assets.mixkit.co/sfx/preview/mixkit-wrong-answer-fail-notification-946.mp3"></audio>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const video = document.getElementById('video');
        const cameraList = document.getElementById('camera-list');
        const statusText = document.getElementById('status-text');
        const modalAbsen = new bootstrap.Modal(document.getElementById('modalAbsen'));
        
        let currentStream = null;
        let isProcessing = false;
        let faceMatcher = null;
        let blinkDetected = false; // Flag Anti-Foto
        const labeledDescriptors = [];
        const rawDataSiswa = <?= json_encode($db_faces) ?>;

        // 1. SINKRONISASI JAM
        function updateClock() {
            const tz = '<?= $timezone_aktif ?>';
            const opt = { timeZone: tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
            document.getElementById('live-clock').textContent = new Intl.DateTimeFormat('en-US', opt).format(new Date());
        }
        setInterval(updateClock, 1000);

        // 2. HITUNG EYE ASPECT RATIO (EAR) UNTUK KEDIPAN
        function getEAR(eyePoints) {
            const p2_p6 = Math.sqrt(Math.pow(eyePoints[1].x - eyePoints[5].x, 2) + Math.pow(eyePoints[1].y - eyePoints[5].y, 2));
            const p3_p5 = Math.sqrt(Math.pow(eyePoints[2].x - eyePoints[4].x, 2) + Math.pow(eyePoints[2].y - eyePoints[4].y, 2));
            const p1_p4 = Math.sqrt(Math.pow(eyePoints[0].x - eyePoints[3].x, 2) + Math.pow(eyePoints[0].y - eyePoints[3].y, 2));
            return (p2_p6 + p3_p5) / (2.0 * p1_p4);
        }

        // 3. INIT AI
        async function initAI() {
            try {
                statusText.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memuat Model AI...';
                await Promise.all([
                    faceapi.nets.ssdMobilenetv1.loadFromUri('./models'),
                    faceapi.nets.faceLandmark68Net.loadFromUri('./models'),
                    faceapi.nets.faceRecognitionNet.loadFromUri('./models')
                ]);

                if (rawDataSiswa.length === 0) {
                    statusText.innerHTML = '<span class="text-danger">Belum ada data wajah terdaftar!</span>';
                    return;
                }

                rawDataSiswa.forEach(siswa => {
                    const desc = new Float32Array(JSON.parse(siswa.face_descriptor));
                    labeledDescriptors.push(new faceapi.LabeledFaceDescriptors(siswa.nis, [desc]));
                });

                faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.5);
                statusText.innerHTML = 'AI Siap. Mendeteksi Kamera...';
                loadCameras();
            } catch (err) {
                statusText.innerHTML = `<span class="text-danger">Error AI: ${err.message}</span>`;
            }
        }

        // 4. MANAJEMEN KAMERA
        async function loadCameras() {
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(device => device.kind === 'videoinput');
                cameraList.innerHTML = '';
                videoDevices.forEach((device, index) => {
                    const option = document.createElement('option');
                    option.value = device.deviceId;
                    option.text = device.label || `Kamera ${index + 1}`;
                    cameraList.appendChild(option);
                });
                startCamera(videoDevices[0].deviceId);
            } catch (err) { statusText.innerHTML = "Gagal memuat kamera."; }
        }

        async function startCamera(deviceId) {
            if (currentStream) currentStream.getTracks().forEach(track => track.stop());
            try {
                currentStream = await navigator.mediaDevices.getUserMedia({ video: { deviceId: deviceId } });
                video.srcObject = currentStream;
                statusText.innerHTML = '<i class="bi bi-eye text-info me-2"></i> Silakan Berkedip untuk Absensi';
                startDetection();
            } catch (err) { statusText.innerHTML = "Kamera tidak dapat diakses."; }
        }

        cameraList.addEventListener('change', () => startCamera(cameraList.value));

        // 5. DETEKSI & LIVENESS (ANTI-FOTO)
        async function startDetection() {
            setInterval(async () => {
                if (isProcessing || !faceMatcher || video.paused) return;

                const detection = await faceapi.detectSingleFace(video).withFaceLandmarks().withFaceDescriptor();

                if (detection) {
                    const landmarks = detection.landmarks;
                    const leftEAR = getEAR(landmarks.getLeftEye());
                    const rightEAR = getEAR(landmarks.getRightEye());
                    const avgEAR = (leftEAR + rightEAR) / 2;

                    // Deteksi Kedipan (EAR < 0.22 dianggap berkedip/manusia asli)
                    if (avgEAR < 0.22) {
                        blinkDetected = true;
                        statusText.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Kedipan Terdeteksi!</span>';
                    }

                    if (blinkDetected) {
                        const match = faceMatcher.findBestMatch(detection.descriptor);
                        if (match.label !== 'unknown' && match.distance < 0.45) {
                            isProcessing = true;
                            blinkDetected = false; // Reset
                            sendAttendanceData(match.label);
                        }
                    } else {
                        statusText.innerHTML = '<i class="bi bi-eye text-info me-2"></i> Kedipkan Mata Anda';
                    }
                }
            }, 500);
        }

        // 6. PROSES KE SERVER
        function sendAttendanceData(nis) {
            statusText.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Mengenali...';
            $.post('proses_absen.php', { nis: nis }, function(response) {
                try {
                    const data = JSON.parse(response);
                    $('#m-nama').text(data.nama);
                    $('#m-kelas').text(data.kelas);
                    $('#m-foto').attr('src', 'img/siswa/' + (data.foto || 'default.jpg'));
                    
                    if (data.status === 'success') {
                        $('#m-pesan').text("ABSEN BERHASIL").css('color', '#10b981');
                        $('#modal-header').text("BERHASIL").css('background-color', '#10b981');
                        document.getElementById('snd-success').play();
                    } else {
                        $('#m-pesan').text("SUDAH MELAKUKAN ABSENSI").css('color', '#f59e0b');
                        $('#modal-header').text("INFO / SUDAH ABSEN").css('background-color', '#f59e0b');
                        document.getElementById('snd-fail').play();
                    }

                    modalAbsen.show();
                    setTimeout(() => {
                        modalAbsen.hide();
                        isProcessing = false;
                        statusText.innerHTML = '<i class="bi bi-eye text-info me-2"></i> Silakan Berkedip...';
                    }, 3000);
                } catch (e) { isProcessing = false; }
            });
        }

        window.onload = initAI;
        setInterval(() => fetch('wa_worker.php'), 15000);
    </script>
</body>
</html>