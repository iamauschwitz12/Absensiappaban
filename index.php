<?php
session_start();
include 'koneksi.php';

// --- SECURITY: INISIALISASI CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// ========== CEK STATUS LOGIN ==========
$is_admin_login = isset($_SESSION['login']) && in_array($_SESSION['role'], ['admin', 'piket', 'walikelas']);
$is_kiosk_mode  = isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] === true;

if (!$is_admin_login && !$is_kiosk_mode) {
    header("location: kiosk_login.php");
    exit;
}

// Ambil data pengaturan
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id = 1");
$stmt_set->execute();
$pengaturan = $stmt_set->get_result()->fetch_assoc();

$nama_sekolah = xss($pengaturan['nama_sekolah'] ?? 'SISTEM ABSENSI');
$logo_sekolah = $pengaturan['logo_sekolah'] ?? 'default.png';
$timezone_aktif = $pengaturan['timezone'] ?? 'Asia/Jakarta';

date_default_timezone_set($timezone_aktif);

$label_waktu = ($timezone_aktif == 'Asia/Makassar') ? "WITA" : (($timezone_aktif == 'Asia/Jayapura') ? "WIT" : "WIB");

// Tanggal Indonesia
$daftar_hari = array('Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu');
$daftar_bulan = array('January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember');
$tgl_indo = $daftar_hari[date('l')] . ', ' . date('d ') . $daftar_bulan[date('F')] . date(' Y');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="img/<?= xss($logo_sekolah) ?>" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Pro - <?= $nama_sekolah; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    
    <style>
        :root {
            --bg-body: #f4f7fa;
            --text-main: #1e293b;
            --card-bg: rgba(255, 255, 255, 0.75);
            --border-glass: rgba(255, 255, 255, 0.4);
            --clock-bg: rgba(0, 0, 0, 0.1);
            --item-bg: #ffffff;
        }

        body.dark-mode {
            --bg-body: #020617;
            --text-main: #f1f5f9;
            --card-bg: rgba(15, 23, 42, 0.7);
            --border-glass: rgba(255, 255, 255, 0.1);
            --clock-bg: rgba(255, 255, 255, 0.15);
            --item-bg: rgba(255, 255, 255, 0.05);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        .navbar-modern { background: linear-gradient(90deg, #0d6efd 0%, #6610f2 100%); padding: 0.8rem 2rem; border:none; }
        .glass-card { background: var(--card-bg); backdrop-filter: blur(15px); border: 1px solid var(--border-glass); border-radius: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .clock-container { background: var(--clock-bg); border: 1px solid rgba(255, 255, 255, 0.2); color: white; padding: 8px 18px; border-radius: 50px; }
        #reader-wrapper { border-radius: 20px; overflow: hidden; border: 5px solid #0d6efd; background: #000; }

        .btn-nav { background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.2); color: white; width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .btn-nav:hover { background: white; color: #0d6efd; }
        .btn-nav.active-sound { background: #ffc107; color: #000; border-color: #ffc107; }

        .attendance-item {
            background: var(--item-bg);
            border: 1px solid var(--border-glass);
            border-radius: 18px;
            padding: 12px 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.2s ease;
        }
        .attendance-item:hover { transform: scale(1.02); }
        .avatar-img { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; border: 2px solid #0d6efd; }
        .avatar-icon-css { width: 48px; height: 48px; border-radius: 12px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #94a3b8; }
        .student-info { flex: 1; }
        .student-name { font-weight: 800; font-size: 0.85rem; margin: 0; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .student-class { font-size: 0.7rem; color: #64748b; font-weight: 600; margin: 0; }
        .badge-status { font-size: 0.6rem; font-weight: 800; padding: 3px 10px; border-radius: 8px; text-transform: uppercase; }
        .attendance-time { font-size: 0.8rem; font-weight: 800; color: var(--text-main); text-align: right; }

        /* INPUT HIDDEN ANTI KEYBOARD */
        #rfid_field { 
            position: absolute; 
            opacity: 0; 
            top: 0; left: 0;
            width: 1px; height: 1px;
        }
    </style>
</head>
<body>

    <input type="text" id="rfid_field" autofocus autocomplete="off" inputmode="none">

    <nav class="navbar navbar-dark navbar-modern sticky-top mb-4">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-3" href="#">
                <img src="img/<?= xss($logo_sekolah) ?>" alt="Logo" width="45" class="bg-white rounded-circle p-1">
                <div>
                    <span class="d-block fw-bold fs-5 lh-1"><?= $nama_sekolah ?></span>
                    <span class="small opacity-75">Kiosk Digital</span>
                </div>
            </a>
            
            <div class="d-flex align-items-center gap-2">
                <div class="clock-container d-none d-md-block me-2">
                    <span id="live-clock" class="fw-bold">00:00:00</span>
                    <small class="ms-1 fw-bold opacity-75"><?= $label_waktu ?></small>
                </div>
                <button id="sound-btn" class="btn-nav"><i id="sound-icon" class="bi bi-volume-up-fill"></i></button>
                <button id="theme-btn" class="btn-nav"><i id="theme-icon" class="bi bi-moon-stars-fill"></i></button>
                <a href="dashboard.php" class="btn-nav"><i class="bi bi-grid-fill"></i></a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="text-center mb-4">
            <h5 class="fw-bold text-primary text-uppercase"><?= $tgl_indo ?></h5>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="glass-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold text-primary small">SCAN AREA</span>
                        <select id="camera-list" class="form-select form-select-sm w-auto"></select>
                    </div>
                    <div id="reader-wrapper"><div id="reader"></div></div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="footer-credit">
                            <p class="mb-0 small" style="font-size: 0.75rem; color: #64748b;">
                                Powered by <a href="https://lynk.id/sq-frh" target="_blank" class="text-decoration-none fw-bold text-primary">Asofa</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-5">
                <div class="glass-card p-4 h-100">
                    <h6 class="fw-bold small mb-4 text-muted"><i class="bi bi-clock-history me-2 text-primary"></i> 5 ABSEN TERAKHIR</h6>
                    <div id="last-absensi-container" style="min-height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalRes" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 25px;">
                <div id="m-head" class="p-3 text-center fw-bold text-white">STATUS</div>
                <div class="modal-body text-center p-5">
                    <div id="m-foto-box" class="mx-auto mb-3" style="width:140px; height:140px; border-radius:20px; overflow:hidden; border:4px solid #eee;">
                         <img id="m-foto" src="" style="width:100%; height:100%; object-fit:cover; display:none;">
                         <i id="m-foto-icon" class="bi bi-person-circle" style="font-size: 90px; color: #ddd;"></i>
                    </div>
                    <h2 id="m-nama" class="fw-bold text-primary"></h2>
                    <p id="m-kelas" class="fw-bold text-muted"></p>
                    <div id="m-pesan" class="fw-bold fs-4 p-3 rounded-4 bg-light text-dark"></div>
                </div>
            </div>
        </div>
    </div>

    <audio id="snd-ok" src="https://assets.mixkit.co/sfx/preview/mixkit-correct-answer-tone-2870.mp3"></audio>
    <audio id="snd-no" src="https://assets.mixkit.co/sfx/preview/mixkit-wrong-answer-fail-notification-946.mp3"></audio>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const modalRes = new bootstrap.Modal(document.getElementById('modalRes'));
        const rfidInput = document.getElementById('rfid_field');
        let isProcessing = false;
        let html5QrCode;

        // --- SOUND & THEME ---
        const soundBtn = document.getElementById('sound-btn');
        const soundIcon = document.getElementById('sound-icon');
        let isSoundEnabled = localStorage.getItem('kiosk-sound') !== 'off';

        function updateSoundUI() {
            soundIcon.className = isSoundEnabled ? 'bi bi-volume-up-fill' : 'bi bi-volume-mute-fill';
            isSoundEnabled ? soundBtn.classList.add('active-sound') : soundBtn.classList.remove('active-sound');
        }
        soundBtn.addEventListener('click', () => { isSoundEnabled = !isSoundEnabled; localStorage.setItem('kiosk-sound', isSoundEnabled ? 'on' : 'off'); updateSoundUI(); });
        updateSoundUI();

        function suaraPemberitahuan(teks) {
            if (!isSoundEnabled) return;
            window.speechSynthesis.cancel();
            const ucapan = new SpeechSynthesisUtterance(teks);
            ucapan.lang = 'id-ID';
            window.speechSynthesis.speak(ucapan);
        }

        const themeBtn = document.getElementById('theme-btn');
        themeBtn.addEventListener('click', () => { document.body.classList.toggle('dark-mode'); localStorage.setItem('kiosk-theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light'); });
        if(localStorage.getItem('kiosk-theme') === 'dark') document.body.classList.add('dark-mode');

        // --- CAMERA ---
        function loadCameras() {
            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length) {
                    const cameraList = document.getElementById('camera-list');
                    devices.forEach((device, index) => {
                        const option = document.createElement('option');
                        option.value = device.id;
                        option.text = device.label || `Kamera ${index + 1}`;
                        cameraList.appendChild(option);
                    });
                    startCamera(devices[0].id);
                    cameraList.addEventListener('change', (e) => startCamera(e.target.value));
                }
            });
        }

        async function startCamera(cameraId) {
            if (html5QrCode) await html5QrCode.stop().catch(() => {});
            html5QrCode = new Html5Qrcode("reader");
            html5QrCode.start(cameraId, { fps: 20, qrbox: 250 }, (text) => { if (!isProcessing) prosesAbsen(text); });
        }

        function prosesAbsen(nis) {
            isProcessing = true;
            $.post('proses_absen.php', { nis: nis, csrf_token: '<?= $_SESSION['csrf_token'] ?>' }, function(res) {
                const d = JSON.parse(res);
                $('#m-nama').text(d.nama);
                $('#m-kelas').text(d.kelas);
                $('#m-pesan').html(d.pesan);
                if (d.foto) { $('#m-foto').attr('src', 'img/siswa/' + d.foto).show(); $('#m-foto-icon').hide(); } 
                else { $('#m-foto').hide(); $('#m-foto-icon').show(); }

                if (d.status === 'success') {
                    $('#m-head').text("PRESENSI DITERIMA").css('background', '#10b981');
                    document.getElementById('snd-ok').play();
                    suaraPemberitahuan("Absen berhasil. Terima kasih " + d.nama);
                    updateLastAbsensi();
                } else if (d.status === 'warning') {
                    $('#m-head').text("SUDAH ABSEN").css('background', '#f59e0b');
                    document.getElementById('snd-no').play();
                    suaraPemberitahuan("Sudah absen. " + d.nama);
                } else {
                    $('#m-head').text("PRESENSI DITOLAK").css('background', '#ef4444');
                    document.getElementById('snd-no').play();
                    suaraPemberitahuan("Absen gagal.");
                }
                modalRes.show();
                setTimeout(() => { modalRes.hide(); isProcessing = false; focusRFID(); }, 1000);
            });
        }

        // --- FOCUS ENGINE (LOGIKA DIPERBAIKI) ---
        function focusRFID() {
            // JANGAN fokus jika modal sedang terbuka atau jika user sedang berinteraksi dengan dropdown kamera
            if (!$('#modalRes').hasClass('show') && document.activeElement.id !== 'camera-list') {
                rfidInput.focus();
            }
        }

        function updateLastAbsensi() {
            $.get('get_last_absensi.php', (data) => $('#last-absensi-container').html(data));
        }

        function updateClock() {
            const tz = '<?= $timezone_aktif ?>';
            const opt = { timeZone: tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
            document.getElementById('live-clock').textContent = new Intl.DateTimeFormat('id-ID', opt).format(new Date());
        }

        $(document).ready(() => {
            loadCameras();
            updateLastAbsensi();
            setInterval(updateClock, 1000); 
            
            // Interval fokus setiap 1 detik, tapi dicek dulu kondisinya
            setInterval(focusRFID, 1000);
            
            // Klik di mana saja fokus ke RFID, KECUALI klik dropdown kamera
            document.addEventListener('click', (e) => {
                if(e.target.id !== 'camera-list') {
                    focusRFID();
                }
            });
        });
        
        rfidInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !isProcessing) { prosesAbsen(rfidInput.value.trim()); rfidInput.value = ''; }
        });
    </script>
</body>
</html>