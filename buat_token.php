<?php
session_start();
include 'koneksi.php';
include 'helper_token.php'; 

// --- SECURITY: ANTI-CLICKJACKING ---
header("X-Frame-Options: DENY");

// --- SECURITY: HELPER XSS ---
function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// 1. Cek Login (Role: Admin/Piket)
if(!isset($_SESSION['login']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'piket')){
    header("location: login.php"); 
    exit;
}

// SECURITY: Regenerasi ID Sesi secara berkala
if (!isset($_SESSION['last_regen'])) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

// 2. Logika Token (Menggunakan Helper)
$tokenData = getOrUpdateToken($conn);
$token = $tokenData['token'];
$expires = $tokenData['expires_at'];

// 3. Hitung Sisa Waktu
$sisa_waktu = strtotime($expires) - time();
if($sisa_waktu < 0) $sisa_waktu = 0;

include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Token Kiosk - Vibrant Glass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <meta http-equiv="refresh" content="<?= $sisa_waktu + 1 ?>">

    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f0f3f9;
            background-image: 
                radial-gradient(at 0% 0%, rgba(13, 110, 253, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(102, 126, 234, 0.1) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 40px;
            padding: 50px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.05);
        }

        .token-display {
            background: linear-gradient(135deg, #0d6efd, #00d2ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 6rem;
            font-weight: 800;
            letter-spacing: 12px;
            margin: 20px 0;
            filter: drop-shadow(0 5px 15px rgba(13, 110, 253, 0.2));
        }

        .timer-badge {
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            border: 1px solid rgba(13, 110, 253, 0.2);
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            font-size: 1.1rem;
        }

        .spin-icon {
            animation: rotate 2s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .info-pill {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 20px;
            margin-top: 30px;
        }

        .btn-back {
            background: white;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 15px;
            padding: 12px 30px;
            font-weight: 700;
            transition: 0.3s;
            color: #64748b;
        }

        .btn-back:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            color: #0d6efd;
        }

        @media (max-width: 576px) {
            .token-display { font-size: 3.5rem; letter-spacing: 5px; }
            .glass-card { padding: 30px 15px; border-radius: 30px; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                
                <div class="glass-card">
                    <h5 class="text-uppercase fw-800 text-muted mb-4" style="letter-spacing: 2px;">Akses Kiosk Kehadiran</h5>
                    
                    <div class="mb-2">
                        <span class="small fw-bold text-primary text-uppercase">Token Aktif</span>
                    </div>

                    <div class="token-display"><?= xss($token) ?></div>
                    
                    <div class="timer-badge mb-4">
                        <i class="bi bi-arrow-repeat me-2 spin-icon"></i>
                        GANTI DALAM: <span id="countdown" class="ms-2">--:--</span>
                    </div>

                    <div class="info-pill text-start d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                            <i class="bi bi-shield-check text-primary fs-4"></i>
                        </div>
                        <div>
                            <p class="mb-0 fw-bold text-dark small">Keamanan Dinamis Aktif</p>
                            <p class="mb-0 text-muted small">Token ini diperbarui otomatis setiap 5 menit untuk mencegah penyalahgunaan akses.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <a href="dashboard.php" class="btn btn-back shadow-sm">
                        <i class="bi bi-grid-fill me-2"></i> Kembali ke Dashboard
                    </a>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Sisa waktu dari PHP
        let timeLeft = <?= (int)$sisa_waktu ?>;
        
        function updateTimer() {
            const display = document.getElementById("countdown");
            
            if(timeLeft <= 0) {
                display.innerText = "REFRESHING...";
                display.classList.add("text-danger");
                // Refresh sedikit lebih lambat dari meta refresh untuk kepastian data DB sudah update
                setTimeout(function(){ window.location.reload(); }, 1500);
            } else {
                let minutes = Math.floor(timeLeft / 60);
                let seconds = timeLeft % 60;
                
                display.innerText = 
                    minutes.toString().padStart(2, '0') + ":" + 
                    seconds.toString().padStart(2, '0');
                
                // Beri warna merah jika waktu tinggal 10 detik
                if(timeLeft <= 10) {
                    display.style.color = "#ef4444";
                }
                
                timeLeft--;
            }
        }

        // Jalankan timer setiap detik
        setInterval(updateTimer, 1000);
        updateTimer();
    </script>
</body>
</html>