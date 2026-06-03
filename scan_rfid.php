<?php
session_start();
include 'koneksi.php';

// --- SECURITY ENGINE (TINGKAT DEWA) ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

if(!isset($_SESSION['login'])){ header("location: login.php"); exit; }

// Ambil Pengaturan
$stmt_set = $conn->prepare("SELECT * FROM pengaturan WHERE id = 1");
$stmt_set->execute();
$sett = $stmt_set->get_result()->fetch_assoc();

$nama_sekolah = $sett['nama_sekolah'] ?? "Asofa School";
$logo = $sett['logo_sekolah'] ?? "asofa.ico";
$timezone_aktif = $sett['timezone'] ?? 'Asia/Jakarta';

include 'header.php'; 
?>

<style>
    :root {
        --bg-main: #f0f3f9;
        --text-main: #1e293b;
        --card-clock: #1e293b;
        --accent: #6366f1;
    }
    body.dark-mode { --bg-main: #0f172a; --text-main: #f8fafc; --card-clock: #1e293b; }
    body { background-color: var(--bg-main) !important; transition: 0.4s; overflow: hidden; font-family: 'Plus Jakarta Sans', sans-serif; }
    
    .kiosk-container { display: flex; height: calc(100vh - 70px); width: 100%; }
    .main-display { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; }
    
    /* JAM CARD DESIGN */
    .clock-group { display: flex; gap: 15px; margin-bottom: 30px; }
    .clock-card {
        background: var(--card-clock); padding: 15px 20px; border-radius: 20px;
        min-width: 100px; display: flex; flex-direction: column; align-items: center;
        box-shadow: 0 15px 35px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);
        position: relative; overflow: hidden;
    }
    .clock-card::after { content: ""; position: absolute; top: 50%; left: 0; width: 100%; height: 1px; background: rgba(0,0,0,0.2); }
    .flip-num { font-family: 'Orbitron', sans-serif; font-size: 4rem; font-weight: 800; color: #fff; z-index: 2; }
    .clock-label { font-size: 0.65rem; color: rgba(255,255,255,0.4); font-weight: 800; text-transform: uppercase; margin-top: 5px; }

    /* SIDEBAR */
    .attendance-sidebar {
        width: 380px; background: rgba(255,255,255,0.4); backdrop-filter: blur(15px);
        border-left: 1px solid rgba(0,0,0,0.05); height: 100%; padding: 30px 20px; display: flex; flex-direction: column;
    }

    /* LOG ITEM DENGAN ICON CSS */
    .log-item { display: flex; align-items: center; gap: 15px; padding: 12px; background: white; border-radius: 18px; margin-bottom: 12px; border: 1px solid rgba(0,0,0,0.03); }
    .log-img { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; }
    .log-icon-css { 
        width: 48px; height: 48px; border-radius: 12px; background: #e2e8f0; 
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #64748b; 
    }
    .log-name { font-weight: 800; font-size: 0.85rem; color: #1e293b; line-height: 1.2; }
    .log-info { font-size: 0.7rem; color: #64748b; font-weight: 600; }
    .log-time { font-size: 0.75rem; font-weight: 800; padding: 3px 10px; border-radius: 8px; }

    /* RFID ANIMASI */
    .rfid-box {
        width: 120px; height: 120px; background: linear-gradient(135deg, var(--accent), #a855f7);
        border-radius: 35px; margin: 40px auto 20px; display: flex; align-items: center; justify-content: center;
        color: white; font-size: 3.5rem; position: relative; box-shadow: 0 15px 30px rgba(99, 102, 241, 0.3);
    }
    .pulse { position: absolute; width: 100%; height: 100%; border-radius: 35px; background: var(--accent); opacity: 0.4; animation: pulse-out 2s infinite; }
    @keyframes pulse-out { 100% { transform: scale(1.6); opacity: 0; } }

    #rfid-input { position: absolute; opacity: 0; pointer-events: none; top: -100px; }

    /* --- TAMBAHAN CSS UNTUK TOMBOL SUARA --- */
    .btn-sound-toggle {
        position: fixed; top: 15px; right: 80px; z-index: 9999;
        width: 40px; height: 40px; border-radius: 10px; border: none;
        background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; color: #64748b; transition: 0.3s;
    }
    .btn-sound-toggle.active { background: #ffc107; color: #000; }
</style>

<button id="toggle-suara" class="btn-sound-toggle" title="Aktif/Matikan Suara">
    <i id="icon-suara" class="bi bi-volume-up-fill"></i>
</button>

<div class="kiosk-container">
    <div class="main-display">
        <div class="text-center mb-5">
            <img src="img/<?= xss($logo) ?>" height="60" class="mb-2">
            <h6 class="fw-800 text-muted small text-uppercase" style="letter-spacing: 4px;"><?= xss($nama_sekolah) ?></h6>
        </div>

        <div class="clock-group">
            <div class="clock-card"><span class="flip-num" id="h">00</span></div>
            <div class="clock-card"><span class="flip-num" id="m">00</span></div>
            <div class="clock-card"><span class="flip-num" id="s">00</span></div>
        </div>
        
        <div id="digital-date" class="fw-800 text-muted text-uppercase small" style="letter-spacing: 2px;">Memuat...</div>

        <div class="rfid-box">
            <i class="bi bi-broadcast"></i>
            <div class="pulse"></div>
        </div>
        
        <h3 class="fw-800 mt-3" style="color: var(--text-main);">SILAKAN TAP KARTU</h3>
        <p class="text-muted small fw-bold">Scan RFID System - Secure Attendance</p>
        
        <form id="form-rfid">
            <input type="text" id="rfid-input" name="nis" autofocus autocomplete="off">
        </form>
    </div>

    <div class="attendance-sidebar">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h6 class="fw-800 m-0"><i class="bi bi-clock-history me-2 text-primary"></i> 5 ABSEN TERAKHIR</h6>
        </div>
        
        <div id="log-container" style="flex: 1; overflow-y: auto; scrollbar-width: none;"></div>
    </div>
</div>

<div class="modal fade" id="modalAbsen" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 40px;">
            <div class="modal-body p-5 text-center">
                <div id="loading-spinner">
                    <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;"></div>
                    <h5 class="fw-800">MEMPROSES...</h5>
                </div>
                <div id="result-content" style="display:none;">
                    <div id="m-foto-container" class="mb-4"></div>
                    <h2 id="m-nama" class="fw-800 mb-1"></h2>
                    <div id="m-kelas" class="badge bg-primary px-4 py-2 rounded-pill mb-4 fw-bold"></div>
                    <div class="alert py-3 fw-bold rounded-4" id="m-pesan"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<audio id="snd-success" src="https://assets.mixkit.co/sfx/preview/mixkit-correct-answer-tone-2870.mp3"></audio>
<audio id="snd-fail" src="https://assets.mixkit.co/sfx/preview/mixkit-wrong-answer-fail-notification-946.mp3"></audio>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // --- TAMBAHAN LOGIKA SUARA ---
    let isSoundOn = localStorage.getItem('kiosk-sound') !== 'off';
    const btnSuara = document.getElementById('toggle-suara');
    const iconSuara = document.getElementById('icon-suara');

    function updateSoundUI() {
        if(isSoundOn) {
            btnSuara.classList.add('active');
            iconSuara.className = 'bi bi-volume-up-fill';
        } else {
            btnSuara.classList.remove('active');
            iconSuara.className = 'bi bi-volume-mute-fill';
        }
    }
    updateSoundUI();

    btnSuara.addEventListener('click', () => {
        isSoundOn = !isSoundOn;
        localStorage.setItem('kiosk-sound', isSoundOn ? 'on' : 'off');
        updateSoundUI();
    });

    function bicara(teks) {
        if (!isSoundOn) return;
        window.speechSynthesis.cancel();
        const msg = new SpeechSynthesisUtterance(teks);
        msg.lang = 'id-ID';
        msg.rate = 1.1;
        window.speechSynthesis.speak(msg);
    }

    // --- LOGIKA JAM ---
    function updateClock() {
        const tz = '<?= $timezone_aktif ?>';
        const now = new Date();
        const options = { timeZone: tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
        const parts = new Intl.DateTimeFormat('id-ID', options).formatToParts(now);
        let h, m, s;
        parts.forEach(p => { if(p.type==='hour') h=p.value; if(p.type==='minute') m=p.value; if(p.type==='second') s=p.value; });
        $('#h').text(h); $('#m').text(m); $('#s').text(s);
        $('#digital-date').text(now.toLocaleDateString('id-ID', { timeZone: tz, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
    }
    setInterval(updateClock, 1000); updateClock();

    function loadLastAbsensi() {
        $.get('get_las_absensi_rfid.php', (data) => $('#log-container').html(data));
    }
    loadLastAbsensi();

    const rfidInput = document.getElementById('rfid-input');
    const modalAbsen = new bootstrap.Modal(document.getElementById('modalAbsen'));
    let isProcessing = false;

    document.addEventListener('click', () => { if(!isProcessing) rfidInput.focus(); });
    setInterval(() => { if(!$('#modalAbsen').hasClass('show') && !isProcessing) rfidInput.focus(); }, 1000);

    $('#form-rfid').on('submit', function(e) {
        e.preventDefault();
        const val = rfidInput.value.trim();
        if(val == "" || isProcessing) return;

        isProcessing = true;
        $('#loading-spinner').show(); $('#result-content').hide(); modalAbsen.show();

        $.ajax({
            url: 'proses_absen.php',
            type: 'POST',
            data: { nis: val },
            success: function(response) {
                try {
                    const d = JSON.parse(response);
                    $('#m-nama').text(d.nama || "Siswa");
                    $('#m-kelas').text(d.kelas || "-");
                    
                    if(d.foto && d.foto !== ""){
                        $('#m-foto-container').html('<img src="img/siswa/'+d.foto+'" class="rounded-circle border border-5 border-white shadow-lg" style="width:160px; height:160px; object-fit:cover;">');
                    } else {
                        $('#m-foto-container').html('<div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto shadow-lg" style="width:160px; height:160px; font-size: 5rem; color: #cbd5e1;"><i class="bi bi-person-fill"></i></div>');
                    }

                    $('#m-pesan').text(d.pesan).removeClass('alert-success alert-danger alert-warning');
                    
                    if(d.status === 'success') {
                        $('#m-pesan').addClass('alert-success');
                        if(isSoundOn) document.getElementById('snd-success').play();
                        // VOICE: BERHASIL
                        bicara("Absen berhasil. Terima kasih " + d.nama);
                    } else if(d.status === 'warning') {
                        $('#m-pesan').addClass('alert-warning');
                        if(isSoundOn) document.getElementById('snd-fail').play();
                        // VOICE: SUDAH ABSEN
                        bicara("Sudah absen. " + d.nama);
                    } else {
                        $('#m-pesan').addClass('alert-danger');
                        if(isSoundOn) document.getElementById('snd-fail').play();
                        // VOICE: GAGAL
                        bicara("Absen gagal.");
                    }

                    $('#loading-spinner').hide(); $('#result-content').fadeIn();
                    loadLastAbsensi();

                    setTimeout(() => { 
                        modalAbsen.hide(); 
                        rfidInput.value = ""; 
                        isProcessing = false; 
                    }, 1500);

                } catch (err) { modalAbsen.hide(); isProcessing = false; rfidInput.value = ""; }
            },
            error: function() { modalAbsen.hide(); isProcessing = false; rfidInput.value = ""; }
        });
    });
</script>