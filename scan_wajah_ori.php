<?php
session_start();
include 'koneksi.php';

// --- AMBIL PENGATURAN SEKOLAH ---
$querySetting = mysqli_query($conn, "SELECT nama_sekolah, logo_sekolah FROM pengaturan WHERE id=1");
$pengaturan = mysqli_fetch_assoc($querySetting);
$nama_sekolah = htmlspecialchars($pengaturan['nama_sekolah'] ?? 'Asofa School');
$logo = $pengaturan['logo_sekolah'] ?? 'asofa.ico';

// --- AMBIL DATA WAJAH DARI DATABASE ---
$query = mysqli_query($conn, "SELECT nis, nama, kelas, foto, face_embedding FROM siswa WHERE face_embedding IS NOT NULL AND face_embedding != ''");
$db_faces = [];
while($row = mysqli_fetch_assoc($query)){
    $row['face_descriptor'] = json_decode($row['face_embedding']); 
    $db_faces[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="img/<?= $logo ?>" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Face Scanner - <?= $nama_sekolah ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/human/dist/human.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root {
            --bg-body: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.8);
            --text-main: #1e293b;
            --accent: #0f172a;
            --primary-glow: rgba(15, 23, 42, 0.1);
            --border: rgba(0, 0, 0, 0.05);
            --glass-blur: blur(12px);
        }

        body.dark-mode {
            --bg-body: #020617;
            --card-bg: rgba(15, 23, 42, 0.7);
            --text-main: #f1f5f9;
            --accent: #38bdf8;
            --primary-glow: rgba(56, 189, 248, 0.15);
            --border: rgba(255, 255, 255, 0.1);
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin: 0;
            overflow-x: hidden;
        }

        .main-container { width: 100%; max-width: 1150px; padding: 20px; z-index: 10; }
        .glass-card { background: var(--card-bg); backdrop-filter: var(--glass-blur); border: 1px solid var(--border); border-radius: 40px; padding: 30px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); }

        .camera-box { position: relative; width: 100%; aspect-ratio: 4/3; margin: 20px auto; border-radius: 25px; background: #000; overflow: hidden; box-shadow: 0 0 0 8px var(--primary-glow); border: 1px solid var(--border); }
        video { width: 100%; height: 100%; object-fit: cover; }
        .mirror { transform: scaleX(-1); }

        .scanner-line { position: absolute; width: 100%; height: 4px; background: linear-gradient(to bottom, transparent, var(--accent)); opacity: 0.5; top: 0; z-index: 5; animation: scanLoop 3s infinite ease-in-out; }
        @keyframes scanLoop { 0% { top: 0% } 50% { top: 100% } 100% { top: 0% } }

        /* SIDEBAR (TAMPILAN ASLI) */
        .attendance-sidebar { background: var(--card-bg); backdrop-filter: var(--glass-blur); border-radius: 32px; padding: 25px; border: 1px solid var(--border); height: 100%; display: flex; flex-direction: column; }
        .log-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255,255,255,0.4); border-radius: 18px; margin-bottom: 12px; border: 1px solid var(--border); }
        .dark-mode .log-item { background: rgba(0,0,0,0.2); }
        .log-img { width: 45px; height: 45px; border-radius: 10px; object-fit: cover; }
        .log-icon-css { width: 45px; height: 45px; border-radius: 10px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #94a3b8; }
        .log-name { font-weight: 700; font-size: 0.85rem; color: var(--text-main); line-height: 1.2; }
        .log-info { font-size: 0.7rem; color: #64748b; }
        .log-time { font-size: 0.75rem; font-weight: 800; background: var(--primary-glow); padding: 2px 8px; border-radius: 6px; }

        .app-title { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 1.2rem; }
        .status-badge { background: var(--primary-glow); color: var(--text-main); padding: 8px 20px; border-radius: 100px; font-size: 0.75rem; font-weight: 700; border: 1px solid var(--border); display: inline-flex; align-items: center; gap: 8px; }

        .theme-toggle, .sound-toggle { width: 40px; height: 40px; border-radius: 12px; background: var(--primary-glow); border: 1px solid var(--border); color: var(--text-main); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; }
        .sound-toggle.active { background: #ffc107; color: #000; border-color: #ffc107; }

        .btn-nav { padding: 10px 20px; border-radius: 14px; background: var(--accent); color: white !important; font-weight: 600; text-decoration: none; font-size: 0.9rem; }
        .modal-content { background: var(--card-bg); backdrop-filter: var(--glass-blur); border-radius: 35px; border: 1px solid var(--border); color: var(--text-main); }
        .modal-img { width: 130px; height: 130px; border-radius: 30px; object-fit: cover; border: 4px solid var(--accent); }
    </style>
</head>
<body>

<div class="main-container">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-3">
                        <img src="img/<?= $logo ?>" height="35">
                        <div class="app-title"><?= $nama_sekolah ?></div>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="sound-toggle" id="sound-btn" title="Aktif/Matikan Suara">
                            <i class="bi bi-volume-up-fill" id="sound-icon"></i>
                        </div>
                        <div class="theme-toggle" id="theme-btn">
                            <i class="bi bi-moon-stars-fill" id="theme-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <div class="status-badge mt-3" id="status-text">
                        <span class="spinner-border spinner-border-sm"></span> LOADING AI...
                    </div>
                </div>

                <div class="camera-box">
                    <div class="scanner-line"></div>
                    <video id="video" autoplay muted playsinline class="mirror"></video>
                </div>

                <div class="row align-items-center g-3">
                    <div class="col-md-6">
                        <select id="camera-list" class="form-select border-0 shadow-none rounded-4 p-2 px-3" style="background: var(--primary-glow); color: var(--text-main); font-weight: 600;"></select>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <a href="dashboard.php" class="btn-nav"><i class="bi bi-house-door"></i></a>
                        <a href="index.php" class="btn-nav ms-1" style="background: #64748b;"><i class="bi bi-qr-code-scan"></i> QR Scan</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="attendance-sidebar">
                <h6 class="fw-800 mb-4"><i class="bi bi-clock-history me-2 text-primary"></i> 5 ABSEN TERAKHIR</h6>
                <div id="log-container" style="flex: 1; overflow-y: auto; scrollbar-width: none;">
                    </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAbsen" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4">
            <div class="modal-body text-center">
                <div class="mb-4"><img id="m-foto" src="" class="modal-img"></div>
                <h2 id="m-nama" class="fw-bold text-uppercase mb-1" style="font-family: 'Space Grotesk';"></h2>
                <div id="m-kelas" class="badge bg-secondary px-3 py-2 rounded-pill mb-4 opacity-75"></div>
                <div class="p-4 rounded-4" style="background: var(--primary-glow); border: 1px solid var(--border);">
                    <h5 class="mb-0 fw-600" id="m-pesan"></h5>
                </div>
            </div>
        </div>
    </div>
</div>

<audio id="snd-success" src="https://assets.mixkit.co/sfx/preview/mixkit-correct-answer-tone-2870.mp3"></audio>
<audio id="snd-fail" src="https://assets.mixkit.co/sfx/preview/mixkit-wrong-answer-fail-notification-946.mp3"></audio>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const body = document.body;
    const themeBtn = document.getElementById('theme-btn');
    const themeIcon = document.getElementById('theme-icon');
    const soundBtn = document.getElementById('sound-btn');
    const soundIcon = document.getElementById('sound-icon');
    const video = document.getElementById('video');
    const statusText = document.getElementById('status-text');
    const cameraList = document.getElementById('camera-list');
    const modalAbsen = new bootstrap.Modal(document.getElementById('modalAbsen'));
    
    let currentStream = null;
    let isProcessing = false;
    let unknownTimeout = null;
    let isSoundEnabled = localStorage.getItem('kiosk-sound') !== 'off';
    const dbFaces = <?= json_encode($db_faces) ?>;

    const human = new Human.Human({
        backend: 'webgl',
        modelBasePath: 'https://vladmandic.github.io/human/models/',
        face: { enabled: true, detector: { rotation: true, return: true }, description: { enabled: true } },
        body: { enabled: false }, hand: { enabled: false }
    });

    // --- MANAJEMEN SUARA ---
    function updateSoundUI() {
        if(isSoundEnabled) {
            soundBtn.classList.add('active');
            soundIcon.className = 'bi bi-volume-up-fill';
        } else {
            soundBtn.classList.remove('active');
            soundIcon.className = 'bi bi-volume-mute-fill';
        }
    }
    updateSoundUI();

    soundBtn.addEventListener('click', () => {
        isSoundEnabled = !isSoundEnabled;
        localStorage.setItem('kiosk-sound', isSoundEnabled ? 'on' : 'off');
        updateSoundUI();
    });

    function bicara(teks) {
        if (!isSoundEnabled) return;
        window.speechSynthesis.cancel();
        const msg = new SpeechSynthesisUtterance(teks);
        msg.lang = 'id-ID';
        msg.rate = 1.1;
        window.speechSynthesis.speak(msg);
    }

    // --- TEMA ---
    themeBtn.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        themeIcon.className = isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        localStorage.setItem('absensi-theme', isDark ? 'dark' : 'light');
    });

    // --- AI INIT ---
    async function init() {
        try {
            await human.load();
            await human.warmup();
            statusText.innerHTML = '<i class="bi bi-shield-check text-success"></i> SCANNER READY';
            loadCameras();
            loadLastAbsensi();
        } catch (err) {
            statusText.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> AI FAILED';
        }
    }

    async function loadCameras() {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(d => d.kind === 'videoinput');
        cameraList.innerHTML = videoDevices.map((d, i) => `<option value="${d.deviceId}">${d.label || 'Kamera '+(i+1)}</option>`).join('');
        let backCam = videoDevices.find(d => d.label.toLowerCase().includes('back')) || videoDevices[0];
        if(backCam) startCamera(backCam.deviceId);
    }

    async function startCamera(id) {
        if (currentStream) currentStream.getTracks().forEach(t => t.stop());
        currentStream = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: id }, width: 640 } });
        video.srcObject = currentStream;
        video.onplay = () => detectFrame();
    }

    async function detectFrame() {
        if (video.paused || video.ended || isProcessing) return;
        const result = await human.detect(video);
        if (result.face && result.face.length > 0) {
            matchFace(result.face[0].embedding);
        } else {
            if (!isProcessing) statusText.innerHTML = '<i class="bi bi-shield-check text-success"></i> SCANNER READY';
        }
        requestAnimationFrame(detectFrame);
    }

    function matchFace(embedding) {
        let best = { sim: 0, s: null };
        dbFaces.forEach(s => {
            const sim = human.match.similarity(embedding, s.face_descriptor);
            if (sim > best.sim) best = { sim, s };
        });

        if (best.sim > 0.62) {
            isProcessing = true;
            processAttendance(best.s);
        } else {
            showUnknownStatus();
        }
    }

    function showUnknownStatus() {
        statusText.innerHTML = '<i class="bi bi-person-x-fill text-danger"></i> WAJAH TIDAK DIKENAL';
        clearTimeout(unknownTimeout);
        unknownTimeout = setTimeout(() => {
            if (!isProcessing) statusText.innerHTML = '<i class="bi bi-shield-check text-success"></i> SCANNER READY';
        }, 1500);
    }

    function processAttendance(siswa) {
        statusText.innerHTML = '<span class="spinner-border spinner-border-sm"></span> VERIFYING...';
        $.post('proses_absen.php', { nis: siswa.nis }, function(res) {
            try {
                const d = JSON.parse(res);
                $('#m-nama').text(d.nama);
                $('#m-kelas').text(d.kelas);
                $('#m-foto').attr('src', 'img/siswa/' + (d.foto || 'default.png'));
                $('#m-pesan').text(d.pesan);
                
                // LOGIKA SUARA
                if (d.status === 'success') {
                    if(isSoundEnabled) document.getElementById('snd-success').play();
                    bicara("Absen berhasil. Terima kasih " + d.nama);
                } else if (d.status === 'warning') {
                    if(isSoundEnabled) document.getElementById('snd-fail').play();
                    bicara("Sudah absen. " + d.nama);
                } else {
                    if(isSoundEnabled) document.getElementById('snd-fail').play();
                    bicara("Absen gagal.");
                }

                modalAbsen.show();
                loadLastAbsensi();

                setTimeout(() => {
                    modalAbsen.hide();
                    isProcessing = false;
                    statusText.innerHTML = '<i class="bi bi-shield-check text-success"></i> SCANNER READY';
                    detectFrame();
                }, 2000);
            } catch (e) { 
                isProcessing = false; 
                detectFrame();
            }
        });
    }

    function loadLastAbsensi() {
        $.get('get_las_absensi_rfid.php', (data) => $('#log-container').html(data));
    }

    cameraList.addEventListener('change', () => startCamera(cameraList.value));
    window.onload = init;
</script>

</body>
</html>