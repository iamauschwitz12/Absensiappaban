<?php
$token = "AAF7i3UnoMjHIBMPo8BagRDn_8LCywO6RL8";


$url_webhook = "URL_PUBLIK_ANDA/bot_proses.php"; 

$url = "https://api.telegram.org/bot$token/setWebhook?url=$url_webhook";

$response = file_get_contents($url);
echo "Hasil Pendaftaran Webhook: " . $response;
?>