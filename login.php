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

if(isset($_SESSION['login']) && isset($_SESSION['id'])){
    header("location: dashboard.php");
    exit;
}

// --- 2. DATA PENGATURAN ---
$stmt_set = $conn->prepare("SELECT nama_sekolah, logo_sekolah FROM pengaturan WHERE id=1");
$stmt_set->execute();
$sett = $stmt_set->get_result()->fetch_assoc();

$nama_sekolah = xss($sett['nama_sekolah'] ?? "SISTEM ABSENSI");
$logo_sekolah = xss($sett['logo_sekolah'] ?? "default.png");
$error_message = "";

// --- 3. LOGIKA LOGIN ---
if(isset($_POST['login'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Akses Ilegal Terdeteksi!";
    } else {
        $user = trim($_POST['username']);
        $pass = $_POST['password'];

        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows > 0){
            $d = $result->fetch_assoc();
            if(password_verify($pass, $d['password'])){
                session_regenerate_id(true);
                $_SESSION['login'] = true;
                $_SESSION['id'] = $d['id'];
                $_SESSION['username'] = $d['username'];
                $_SESSION['role'] = $d['role'];
                $_SESSION['nama'] = $d['nama_lengkap'] ?? $d['nama'] ?? $d['username'];
                $_SESSION['kelas_diampu'] = $d['kelas_diampu'] ?? '';
                
                header("location: dashboard.php");
                exit;
            } else {
                $error_message = "Username atau Password salah!";
            }
        } else {
            $error_message = "Username atau Password salah!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= $nama_sekolah ?></title>
    
    <!-- Link CSS (Sesuaikan path assets Anda) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f0f4f9;
            background-image: 
                radial-gradient(at 0% 0%, rgba(13, 110, 253, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(102, 16, 242, 0.1) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }

        .header-section {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }

        .sub-text {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 500;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 8px;
            margin-left: 5px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 1.5px solid #e2e8f0;
            border-radius: 15px;
            padding: 12px 18px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: #fff;
            border-color: #0d6efd;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #0d6efd, #0053ce);
            border: none;
            border-radius: 15px;
            padding: 14px;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.2);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(13, 110, 253, 0.3);
        }

        .error-msg {
            background: #fff1f1;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
            padding: 12px 15px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 25px;
        }

        .footer-action {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .btn-kiosk-sm {
            padding: 7px 15px;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 700;
            transition: 0.2s;
        }

        .btn-kiosk-sm:hover {
            background: #fff;
            color: #0d6efd;
            border-color: #0d6efd;
        }

        .powered-by {
            font-size: 0.8rem;
            color: #94a3b8;
            margin: 0;
            font-weight: 600;
        }

        .powered-by a {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="header-section">
        <div class="logo-wrapper">
            <img src="img/<?= $logo_sekolah ?>" alt="Logo">
        </div>
        <h1 class="brand-title"><?= $nama_sekolah ?></h1>
        <p class="sub-text">Portal Administrasi Sistem</p>
    </div>

    <?php if($error_message): ?>
        <div class="error-msg">
            <i class="bi bi-exclamation-circle-fill me-2"></i> <?= $error_message ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control shadow-none" placeholder="Masukkan username" required autofocus>
        </div>
        
        <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control shadow-none" placeholder="••••••••" required>
        </div>
        
        <button type="submit" name="login" class="btn btn-primary w-100">
            Masuk ke Panel <i class="bi bi-arrow-right-short ms-1"></i>
        </button>
    </form>

    <div class="footer-action">
        <a href="kiosk_login.php" class="btn-kiosk-sm">
            <i class="bi bi-display me-1"></i> Mode Kiosk
        </a>
        <p class="powered-by">
            Powered by <a href="https://lynk.id/sq-frh" target="_blank">Asofa</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>