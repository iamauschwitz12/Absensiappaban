<?php
session_start();

// 1. Hapus semua data session
$_SESSION = array();

// 2. Jika menggunakan cookie session, hapus juga cookienya
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Hancurkan session secara total
session_destroy();

// 4. Bersihkan sessionStorage di sisi browser (opsional tapi disarankan)
echo "<script>
    sessionStorage.clear();
    window.location.href = 'kiosk_login.php';
</script>";
exit;
?>