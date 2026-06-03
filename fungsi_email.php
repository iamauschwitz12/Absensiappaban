<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'libs/PHPMailer/src/Exception.php';
require 'libs/PHPMailer/src/PHPMailer.php';
require 'libs/PHPMailer/src/SMTP.php';

function sendEmail($ke_email, $subjek, $isi_pesan, $config) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $config['smtp_pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['smtp_port'];

        $mail->setFrom($config['smtp_user'], $config['nama_sekolah']);
        $mail->addAddress($ke_email);
        $mail->isHTML(true);
        $mail->Subject = $subjek;
        $mail->Body    = $isi_pesan;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}