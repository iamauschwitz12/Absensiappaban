<?php
session_start();
include 'koneksi.php';

// --- 1. SECURITY ENGINE ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function xss($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// --- 2. AMBIL PENGATURAN DASAR ---
$stmt_set = $conn->prepare("SELECT nama_sekolah, logo_sekolah FROM pengaturan WHERE id=1");
$stmt_set->execute();
$sett = $stmt_set->get_result()->fetch_assoc();

$nama_sekolah = xss($sett['nama_sekolah'] ?? "SISTEM ABSENSI");
$logo_sekolah = xss($sett['logo_sekolah'] ?? "default.png");

$error = '';

// --- 3. LOGIKA LOGIN KIOSK (TANPA CEK WAKTU) ---
if (isset($_POST['submit_token'])) {
    
    // Validasi CSRF
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Akses Ilegal Terdeteksi!");
    }

    $input_token = trim($_POST['token']);

    // PERBAIKAN: Query hanya mengecek ID dan kecocokan Token saja
    // Kita hapus bagian "AND expires_at > NOW()"
    $stmt = $conn->prepare("SELECT * FROM kiosk_tokens WHERE id = 1 AND token = ?");
    $stmt->bind_param("s", $input_token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // --- SUKSES LOGIN ---
        session_regenerate_id(true);
        $_SESSION['kiosk_mode'] = true;     
        $_SESSION['fresh_login'] = true;    
        
        header("Location: index.php"); 
        exit;
    } else {
        // --- GAGAL LOGIN ---
        sleep(1); // Mencegah bot penebak password
        $error = "Token tidak valid! Pastikan kode yang Anda masukkan benar.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="img/<?= $logo_sekolah ?>" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kiosk | <?= $nama_sekolah ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root { --primary-color: #0d6efd; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f0f3f9;
            background-image: radial-gradient(at 10% 10%, rgba(13, 110, 253, 0.15) 0px, transparent 50%), radial-gradient(at 90% 10%, rgba(102, 16, 242, 0.1) 0px, transparent 50%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center; margin: 0;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.6); 
            backdrop-filter: blur(20px); 
            border-radius: 30px;
            padding: 3rem 2.5rem;
            width: 100%; max-width: 420px;
            box-shadow: 0 15px 35px rgba(31, 38, 135, 0.1);
            text-align: center;
        }
        .logo-container {
            width: 80px; height: 80px;
            background: white; border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .logo-container img { max-width: 80%; }
        .token-input {
            border-radius: 16px; padding: 1rem;
            font-size: 1.5rem; letter-spacing: 8px;
            text-align: center; text-transform: uppercase;
        }
        .btn-kiosk {
            background: linear-gradient(135deg, #0d6efd, #00d2ff);
            color: white; border: none; border-radius: 16px;
            padding: 1rem; font-weight: 800; width: 100%;
        }
        .error-box {
            background-color: #fee2e2; color: #dc2626;
            border-radius: 12px; padding: 0.8rem; margin-bottom: 1.5rem;
        }

        .btn-admin-custom {
        display: block;
        margin-top: 2rem;
        padding: 0.7rem;
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(13, 110, 253, 0.2);
        border-radius: 12px;
        color: #64748b;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .btn-admin-custom:hover {
        background: #ffffff;
        color: #0d6efd;
        border-color: #0d6efd;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    </style>
</head>
<body>

<div class="glass-card">
    <div class="logo-container">
        <img src="img/<?= $logo_sekolah ?>" alt="Logo">
    </div>
    
    <h1 class="brand-name" style="font-size: 1.5rem; font-weight: 800;">Mode Kiosk</h1>
    <p class="sub-title" style="color: #64748b; margin-bottom: 2rem;"><?= $nama_sekolah ?></p>

    <?php if($error): ?>
        <div class="error-box">
            <i class="bi bi-shield-x me-1"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <div class="mb-3">
            <input type="text" name="token" class="form-control text-center fw-bold token-input" placeholder="Masukan Token" maxlength="10" autocomplete="off" required autofocus>
        </div>
        
        <button type="submit" name="submit_token" class="btn btn-kiosk w-100">
            <i class="bi bi-qr-code-scan me-2"></i> Buka Scanner
        </button>
    </form>
   
    <a href="login.php" class="btn-admin-custom">
        <i class="bi bi-person-lock me-1"></i> Login Administrator
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>