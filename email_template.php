<?php
function get_email_template($nama_siswa, $status, $waktu, $sekolah) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 15px; overflow: hidden;'>
        <div style='background: #0ea5e9; padding: 20px; text-align: center; color: white;'>
            <h2 style='margin: 0;'>Laporan Kehadiran Siswa</h2>
            <p style='margin: 5px 0 0;'>$sekolah</p>
        </div>
        <div style='padding: 30px; color: #334155;'>
            <p>Yth. Orang Tua/Wali dari <strong>$nama_siswa</strong>,</p>
            <p>Menginfokan bahwa putra/putri Anda telah tercatat melakukan absensi dengan detail sebagai berikut:</p>
            <table style='width: 100%; margin: 20px 0; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #f1f5f9; color: #64748b;'>Status</td>
                    <td style='padding: 10px; border-bottom: 1px solid #f1f5f9; font-weight: bold; color: #0ea5e9;'>$status</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #f1f5f9; color: #64748b;'>Waktu</td>
                    <td style='padding: 10px; border-bottom: 1px solid #f1f5f9; font-weight: bold;'>$waktu</td>
                </tr>
            </table>
            <p style='font-size: 0.9rem; color: #94a3b8;'>Pesan ini dikirim otomatis oleh sistem absensi sekolah. Tidak perlu membalas email ini.</p>
        </div>
        <div style='background: #f8fafc; padding: 15px; text-align: center; font-size: 0.8rem; color: #94a3b8;'>
            &copy; " . date('Y') . " $sekolah. All rights reserved.
        </div>
    </div>";
}