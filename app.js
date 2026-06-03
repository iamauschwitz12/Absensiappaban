const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const express = require('express');
const bodyParser = require('body-parser');

const app = express();
const port = 3000; // Node.js berjalan di port 3000

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// 1. Konfigurasi Client WhatsApp
const client = new Client({
    authStrategy: new LocalAuth() // Menyimpan sesi login agar tidak scan QR terus
});

client.on('qr', (qr) => {
    qrcode.generate(qr, { small: true });
    console.log('SCAN QR CODE INI DENGAN WA ANDA SEKARANG!');
});

client.on('ready', () => {
    console.log('Client WhatsApp Siap!');
});

client.initialize();

// 2. Membuat API untuk menerima request dari PHP
app.post('/kirim-pesan', (req, res) => {
    const number = req.body.number; // No HP dari PHP
    const message = req.body.message; // Pesan dari PHP

    // Format nomor (tambah @c.us untuk whatsapp-web.js)
    const chatId = number + "@c.us";

    client.sendMessage(chatId, message).then(response => {
        res.status(200).json({ status: 'sukses', response: response });
    }).catch(err => {
        res.status(500).json({ status: 'gagal', error: err });
    });
});

app.listen(port, () => {
    console.log(`Server WA berjalan di https://localhost:${port}`);
});