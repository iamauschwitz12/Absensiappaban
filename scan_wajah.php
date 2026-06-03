<?php
session_start();
include 'koneksi.php';

// --- AMBIL PENGATURAN SEKOLAH ---
$querySetting = mysqli_query($conn, "SELECT nama_sekolah, logo_sekolah FROM pengaturan WHERE id=1");
$pengaturan = mysqli_fetch_assoc($querySetting);
$nama_sekolah = htmlspecialchars($pengaturan['nama_sekolah'] ?? 'Asofa School');
$logo = $pengaturan['logo_sekolah'] ?? 'asofa.ico';

// --- AMBIL DATA WAJAH SISWA DARI DATABASE ---
$query_siswa = mysqli_query($conn, "SELECT nis, nama, kelas, foto, face_embedding FROM siswa WHERE face_embedding IS NOT NULL AND face_embedding != ''");
$db_siswa_faces = [];
while($row = mysqli_fetch_assoc($query_siswa)){
    $row['face_descriptor'] = json_decode($row['face_embedding']);
    $row['tipe'] = 'siswa';
    $db_siswa_faces[] = $row;
}

// --- AMBIL DATA WAJAH GURU DARI DATABASE ---
$query_guru = mysqli_query($conn, "SELECT nip, nama, jabatan AS kelas, foto, face_embedding FROM guru WHERE face_embedding IS NOT NULL AND face_embedding != ''");
$db_guru_faces = [];
while($row = mysqli_fetch_assoc($query_guru)){
    $row['face_descriptor'] = json_decode($row['face_embedding']);
    $row['tipe'] = 'guru';
    // Seragamkan key nis = nip agar matchFace bisa pakai satu key
    $row['nis'] = $row['nip'];
    $db_guru_faces[] = $row;
}

// Gabungkan semua data wajah
$db_faces = array_merge($db_siswa_faces, $db_guru_faces);
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

        /* OVERLAY FACE BOX */
        #face-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 5; }

        /* FRAME STABILIZER INDICATOR */
        .stab-bar-wrap { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); z-index: 10; width: 60%; background: rgba(0,0,0,0.4); border-radius: 10px; height: 8px; overflow: hidden; display: none; }
        .stab-bar { height: 100%; width: 0%; background: linear-gradient(90deg, #22c55e, #86efac); border-radius: 10px; transition: width 0.2s; }

        .scanner-line { position: absolute; width: 100%; height: 4px; background: linear-gradient(to bottom, transparent, var(--accent)); opacity: 0.5; top: 0; z-index: 5; animation: scanLoop 3s infinite ease-in-out; }
        @keyframes scanLoop { 0% { top: 0% } 50% { top: 100% } 100% { top: 0% } }

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

        /* Confidence meter */
        #conf-meter { position: absolute; top: 12px; right: 12px; z-index: 10; background: rgba(0,0,0,0.55); color: #fff; font-size: 0.65rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: none; }

        /* Banner info loading AI */
        .banner-ai-info {
            background: rgba(251,191,36,0.12);
            border: 1px solid rgba(251,191,36,0.45);
            border-radius: 16px;
            padding: 10px 16px;
            font-size: 0.78rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            margin-bottom: 4px;
        }
        .banner-ai-info i { font-size: 1rem; color: #f59e0b; flex-shrink: 0; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-card">
                <!-- Header -->
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

                <!-- Banner info loading AI -->
                <div class="banner-ai-info" id="banner-ai">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Sistem sedang menyiapkan AI — membutuhkan beberapa detik saat pertama kali dibuka.</span>
                </div>

                <!-- Status -->
                <div class="text-center">
                    <div class="status-badge mt-3" id="status-text">
                        <span class="spinner-border spinner-border-sm"></span> LOADING AI...
                    </div>
                </div>

                <!-- Kamera -->
                <div class="camera-box">
                    <div class="scanner-line"></div>
                    <video id="video" autoplay muted playsinline class="mirror"></video>
                    <canvas id="face-overlay"></canvas>
                    <div id="conf-meter">SIM: -</div>
                    <div class="stab-bar-wrap" id="stab-wrap">
                        <div class="stab-bar" id="stab-bar"></div>
                    </div>
                </div>

                <!-- Pilih kamera & navigasi -->
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

        <!-- Sidebar log absensi -->
        <div class="col-lg-4">
            <div class="attendance-sidebar">
                <h6 class="fw-800 mb-4"><i class="bi bi-clock-history me-2 text-primary"></i> 5 ABSEN TERAKHIR</h6>
                <div id="log-container" style="flex: 1; overflow-y: auto; scrollbar-width: none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal konfirmasi absen -->
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

<!-- Audio feedback -->
<audio id="snd-success" src="https://assets.mixkit.co/sfx/preview/mixkit-correct-answer-tone-2870.mp3"></audio>
<audio id="snd-fail" src="https://assets.mixkit.co/sfx/preview/mixkit-wrong-answer-fail-notification-946.mp3"></audio>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ================================================================
    // KONFIGURASI AKURASI — sesuaikan jika perlu
    // ================================================================
    const CONFIG = {
        // Batas minimum similarity untuk dianggap cocok (0.0 – 1.0)
        MATCH_THRESHOLD: 0.75,

        // Jumlah frame berturut-turut dengan hasil SAMA sebelum absen diproses
        STABLE_FRAMES_NEEDED: 3,

        // Batas minimum confidence detector wajah (0.0 – 1.0)
        MIN_FACE_SCORE: 0.80,

        // Cooldown per-NIS (detik) — mencegah 1 orang absen 2x sekaligus
        COOLDOWN_SECONDS: 30,

        // Interval antar frame deteksi (ms)
        DETECT_INTERVAL_MS: 200,
    };
    // ================================================================

    const body        = document.body;
    const themeBtn    = document.getElementById('theme-btn');
    const themeIcon   = document.getElementById('theme-icon');
    const soundBtn    = document.getElementById('sound-btn');
    const soundIcon   = document.getElementById('sound-icon');
    const video       = document.getElementById('video');
    const statusText  = document.getElementById('status-text');
    const cameraList  = document.getElementById('camera-list');
    const bannerAI    = document.getElementById('banner-ai');
    const confMeter   = document.getElementById('conf-meter');
    const stabWrap    = document.getElementById('stab-wrap');
    const stabBar     = document.getElementById('stab-bar');
    const faceCanvas  = document.getElementById('face-overlay');
    const faceCtx     = faceCanvas.getContext('2d');
    const modalAbsen  = new bootstrap.Modal(document.getElementById('modalAbsen'));

    let currentStream  = null;
    let isProcessing   = false;
    let isSoundEnabled = localStorage.getItem('kiosk-sound') !== 'off';
    let detectTimer    = null;

    // Stabilizer
    let stableCandidate = null;
    let stableCount     = 0;

    // Cooldown map: { nis => timestamp_terakhir_absen }
    const cooldownMap = {};

    // Data wajah dari DB (di-inject PHP) — siswa + guru
    const dbFaces = <?= json_encode($db_faces) ?>;

    // ----------------------------------------------------------------
    // Deteksi apakah perangkat mobile
    // ----------------------------------------------------------------
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

    // ----------------------------------------------------------------
    // Restore tema
    // ----------------------------------------------------------------
    if (localStorage.getItem('absensi-theme') === 'dark') {
        body.classList.add('dark-mode');
        themeIcon.className = 'bi bi-sun-fill';
    }

    // ----------------------------------------------------------------
    // SUARA
    // ----------------------------------------------------------------
    function updateSoundUI() {
        if (isSoundEnabled) {
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
        const msg  = new SpeechSynthesisUtterance(teks);
        msg.lang   = 'id-ID';
        msg.rate   = 1.1;
        window.speechSynthesis.speak(msg);
    }

    // ----------------------------------------------------------------
    // TEMA
    // ----------------------------------------------------------------
    themeBtn.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        themeIcon.className = isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        localStorage.setItem('absensi-theme', isDark ? 'dark' : 'light');
    });

    // ----------------------------------------------------------------
    // INISIALISASI AI
    // ----------------------------------------------------------------
    const human = new Human.Human({
        backend: 'webgl',
        modelBasePath: 'https://vladmandic.github.io/human/models/',
        face: {
            enabled: true,
            detector: { rotation: true, return: true, minConfidence: CONFIG.MIN_FACE_SCORE },
            description: { enabled: true },
            mesh: { enabled: false },
            iris: { enabled: false }
        },
        body:   { enabled: false },
        hand:   { enabled: false },
        object: { enabled: false }
    });

    async function init() {
        try {
            statusText.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memuat model AI...';
            await human.load();
            await human.warmup();

            // Sembunyikan banner setelah AI siap
            bannerAI.style.display = 'none';
            statusText.innerHTML = '<i class="bi bi-shield-check text-success"></i> SCANNER READY';

            loadCameras();
            loadLastAbsensi();
        } catch (err) {
            console.error(err);
            statusText.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> AI FAILED';
        }
    }

    // ----------------------------------------------------------------
    // KAMERA — kamera depan diutamakan
    // ----------------------------------------------------------------
    async function loadCameras() {
        try {
            // Minta izin kamera dulu agar label tersedia
            const tempStream = await navigator.mediaDevices.getUserMedia({ video: true });
            tempStream.getTracks().forEach(t => t.stop());
        } catch(e) { /* lanjut meski ditolak */ }

        const devices      = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(d => d.kind === 'videoinput');

        cameraList.innerHTML = videoDevices.map((d, i) =>
            `<option value="${d.deviceId}">${d.label || 'Kamera ' + (i + 1)}</option>`
        ).join('');

        // Cari kamera depan berdasarkan label
        const frontCam = videoDevices.find(d => {
            const label = d.label.toLowerCase();
            return label.includes('front')  ||
                   label.includes('depan')  ||
                   label.includes('user')   ||
                   label.includes('selfie') ||
                   label.includes('facetime');
        });

        // Gunakan kamera depan jika ketemu, atau fallback ke index 0
        const targetCam = frontCam || videoDevices[0];
        if (targetCam) {
            cameraList.value = targetCam.deviceId;
            startCamera(targetCam.deviceId);
        }
    }

    async function startCamera(deviceId) {
        if (currentStream) currentStream.getTracks().forEach(t => t.stop());
        clearTimeout(detectTimer);
        stableCandidate = null;
        stableCount     = 0;

        try {
            let constraints;

            if (isMobile) {
                // Di HP: prioritaskan facingMode 'user' (kamera depan)
                // deviceId tetap dikirim sebagai ideal (bukan exact) agar
                // browser bisa memilih kamera depan jika deviceId tidak cocok
                constraints = {
                    video: {
                        facingMode: { ideal: 'user' },
                        deviceId:   deviceId ? { ideal: deviceId } : undefined,
                        width:  { ideal: 640 },
                        height: { ideal: 480 }
                    }
                };
            } else {
                // Di desktop: pakai deviceId exact seperti semula
                constraints = {
                    video: {
                        deviceId: deviceId ? { exact: deviceId } : undefined,
                        width:  640,
                        height: 480
                    }
                };
            }

            currentStream      = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject    = currentStream;
            video.onloadedmetadata = () => video.play();
            video.onplay       = () => scheduleDetect();

        } catch (err) {
            console.warn('Constraints gagal, coba fallback facingMode user:', err);
            // Fallback 1: hanya facingMode user tanpa deviceId
            try {
                currentStream   = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' }
                });
                video.srcObject = currentStream;
                video.onloadedmetadata = () => video.play();
                video.onplay    = () => scheduleDetect();
            } catch (err2) {
                console.warn('Fallback facingMode gagal, coba tanpa constraint:', err2);
                // Fallback 2: buka kamera apapun yang tersedia
                try {
                    currentStream   = await navigator.mediaDevices.getUserMedia({ video: true });
                    video.srcObject = currentStream;
                    video.onloadedmetadata = () => video.play();
                    video.onplay    = () => scheduleDetect();
                } catch (err3) {
                    statusText.innerHTML = '<i class="bi bi-camera-video-off text-danger"></i> Kamera gagal dibuka';
                }
            }
        }
    }

    cameraList.addEventListener('change', () => startCamera(cameraList.value));

    // ----------------------------------------------------------------
    // LOOP DETEKSI
    // ----------------------------------------------------------------
    function scheduleDetect() {
        clearTimeout(detectTimer);
        if (video.paused || video.ended) return;
        detectTimer = setTimeout(runDetect, CONFIG.DETECT_INTERVAL_MS);
    }

    async function runDetect() {
        if (isProcessing) { scheduleDetect(); return; }

        try {
            const result = await human.detect(video);
            drawFaceBox(result);

            if (!result.face || result.face.length === 0) {
                resetStabilizer();
                confMeter.style.display = 'none';
                if (!isProcessing) statusText.innerHTML = '<i class="bi bi-shield-check text-success"></i> SCANNER READY';
                scheduleDetect();
                return;
            }

            // Ambil wajah dengan score tertinggi
            const bestFace = result.face.reduce((a, b) => (a.score > b.score ? a : b));

            // Filter 1: confidence wajah
            if (bestFace.score < CONFIG.MIN_FACE_SCORE) {
                confMeter.style.display  = 'block';
                confMeter.textContent    = `CONF: ${(bestFace.score * 100).toFixed(0)}% (rendah)`;
                confMeter.style.background = 'rgba(239,68,68,0.7)';
                resetStabilizer();
                scheduleDetect();
                return;
            }

            if (!bestFace.embedding) {
                resetStabilizer();
                scheduleDetect();
                return;
            }

            // Matching
            const matchResult = matchFace(bestFace.embedding);

            confMeter.style.display = 'block';
            confMeter.textContent   = matchResult
                ? `SIM: ${(matchResult.sim * 100).toFixed(1)}% — ${matchResult.s.nama.split(' ')[0]}`
                : 'Wajah tidak dikenal';
            confMeter.style.background = matchResult
                ? 'rgba(34,197,94,0.75)'
                : 'rgba(239,68,68,0.65)';

            if (matchResult) {
                const nis = matchResult.s.nis;

                // Filter 2: stabilizer — harus cocok N frame berturut-turut
                if (stableCandidate === nis) {
                    stableCount++;
                } else {
                    stableCandidate = nis;
                    stableCount     = 1;
                }

                stabWrap.style.display = 'block';
                const pct = Math.min((stableCount / CONFIG.STABLE_FRAMES_NEEDED) * 100, 100);
                stabBar.style.width    = pct + '%';
                statusText.innerHTML   = `<i class="bi bi-person-bounding-box text-warning"></i> Memverifikasi... (${stableCount}/${CONFIG.STABLE_FRAMES_NEEDED})`;

                if (stableCount >= CONFIG.STABLE_FRAMES_NEEDED) {
                    // Filter 3: cooldown per-siswa
                    const now      = Date.now();
                    const lastAbsen = cooldownMap[nis] || 0;
                    if ((now - lastAbsen) < CONFIG.COOLDOWN_SECONDS * 1000) {
                        const sisaDetik = Math.ceil((CONFIG.COOLDOWN_SECONDS * 1000 - (now - lastAbsen)) / 1000);
                        statusText.innerHTML = `<i class="bi bi-hourglass-split text-warning"></i> Sudah tercatat. Tunggu ${sisaDetik}s`;
                        resetStabilizer();
                        scheduleDetect();
                        return;
                    }

                    // Semua filter lolos — proses absensi
                    isProcessing        = true;
                    cooldownMap[nis]    = now;
                    resetStabilizer();
                    processAttendance(matchResult.s);
                }
            } else {
                resetStabilizer();
                statusText.innerHTML = '<i class="bi bi-person-x-fill text-danger"></i> WAJAH TIDAK DIKENAL';
            }

        } catch (err) {
            console.error('Detect error:', err);
        }

        scheduleDetect();
    }

    function resetStabilizer() {
        stableCandidate    = null;
        stableCount        = 0;
        stabWrap.style.display = 'none';
        stabBar.style.width    = '0%';
    }

    // ----------------------------------------------------------------
    // MATCHING — cosine similarity
    // ----------------------------------------------------------------
    function matchFace(embedding) {
        let best = { sim: 0, s: null };
        for (const s of dbFaces) {
            if (!s.face_descriptor) continue;
            const sim = cosineSimilarity(embedding, s.face_descriptor);
            if (sim > best.sim) best = { sim, s };
        }
        return best.sim >= CONFIG.MATCH_THRESHOLD ? best : null;
    }

    function cosineSimilarity(a, b) {
        if (!a || !b || a.length !== b.length) return 0;
        let dot = 0, na = 0, nb = 0;
        for (let i = 0; i < a.length; i++) {
            dot += a[i] * b[i];
            na  += a[i] * a[i];
            nb  += b[i] * b[i];
        }
        const denom = Math.sqrt(na) * Math.sqrt(nb);
        return denom === 0 ? 0 : dot / denom;
    }

    // ----------------------------------------------------------------
    // GAMBAR KOTAK WAJAH DI CANVAS
    // ----------------------------------------------------------------
    function drawFaceBox(result) {
        faceCanvas.width  = video.videoWidth  || video.offsetWidth;
        faceCanvas.height = video.videoHeight || video.offsetHeight;
        faceCtx.clearRect(0, 0, faceCanvas.width, faceCanvas.height);

        if (!result.face) return;
        result.face.forEach(face => {
            if (!face.box) return;
            const [x, y, w, h] = face.box;
            const color = face.score >= CONFIG.MIN_FACE_SCORE ? '#22c55e' : '#ef4444';
            faceCtx.strokeStyle = color;
            faceCtx.lineWidth   = 3;
            // Flip X karena video di-mirror
            const flippedX = faceCanvas.width - x - w;
            faceCtx.strokeRect(flippedX, y, w, h);
        });
    }

    // ----------------------------------------------------------------
    // PROSES ABSENSI KE SERVER
    // ----------------------------------------------------------------
    function processAttendance(orang) {
        statusText.innerHTML = '<span class="spinner-border spinner-border-sm"></span> VERIFYING...';

        const isGuru   = (orang.tipe === 'guru');
        const endpoint = isGuru ? 'proses_absen_guru.php' : 'proses_absen.php';
        const payload  = isGuru ? { nip: orang.nip } : { nis: orang.nis };
        const fotoDir  = isGuru ? 'img/guru/' : 'img/siswa/';

        $.post(endpoint, payload, function(res) {
            try {
                const d = JSON.parse(res);
                $('#m-nama').text(d.nama);
                $('#m-kelas').text(d.kelas || d.jabatan || '');
                const fotoSrc = (d.foto && d.foto !== 'null') ? fotoDir + d.foto : '';
                if (fotoSrc) {
                    $('#m-foto').attr('src', fotoSrc).show();
                } else {
                    // Tampilkan avatar placeholder jika tidak ada foto
                    $('#m-foto').attr('src', 'https://ui-avatars.com/api/?name=' + encodeURIComponent(d.nama) + '&background=7c3aed&color=fff&size=200').show();
                }
                $('#m-pesan').text(d.pesan);

                if (d.status === 'success') {
                    if (isSoundEnabled) document.getElementById('snd-success').play();
                    bicara('Absen berhasil. Terima kasih ' + d.nama);
                } else if (d.status === 'warning') {
                    if (isSoundEnabled) document.getElementById('snd-fail').play();
                    bicara('Sudah absen. ' + d.nama);
                } else {
                    if (isSoundEnabled) document.getElementById('snd-fail').play();
                    bicara('Absen gagal.');
                }

                modalAbsen.show();
                loadLastAbsensi();

                setTimeout(() => {
                    modalAbsen.hide();

                    // Guru berhasil/warning → redirect ke halaman monitoring absensi guru
                    if (isGuru && d.status !== 'error') {
                        window.location.href = 'absensi_guru.php';
                        return;
                    }

                    // Siswa → lanjut scan seperti biasa
                    isProcessing         = false;
                    statusText.innerHTML = '<i class="bi bi-shield-check text-success"></i> SCANNER READY';
                    scheduleDetect();
                }, 1500);

            } catch (e) {
                console.error('Parse error:', e);
                isProcessing = false;
                scheduleDetect();
            }
        }).fail(() => {
            isProcessing = false;
            scheduleDetect();
        });
    }

    function loadLastAbsensi() {
        $.get('get_las_absensi_rfid.php', (data) => $('#log-container').html(data));
    }

    window.onload = init;
</script>

</body>
</html>