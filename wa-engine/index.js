const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const express = require('express');
const axios = require('axios');

const app = express();
app.use(express.json());

const LARAVEL_WEBHOOK_URL = 'http://laravel-app:80/api/webhook/whatsapp';

const client = new Client({
    authStrategy: new LocalAuth(),
    puppeteer: {
        headless: true,
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    }
});
client.on('qr', (qr) => {
    console.log('Scan QR Code ini menggunakan WhatsApp Anda:');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    console.log('WhatsApp Bot is Ready!');
});

client.on('message', async msg => {
    const contact = await msg.getContact();
    const senderName = contact.pushname || contact.name || 'Pengguna';

    if (msg.hasMedia) {
        const media = await msg.downloadMedia();
        if (media && media.mimetype.includes('image')) {
            try {
                await axios.post(LARAVEL_WEBHOOK_URL, {
                    type: 'image', // Penanda jenis pesan
                    from: msg.from,
                    sender_name: senderName,
                    media_data: media.data
                });
                console.log(`Gambar dari ${senderName} diteruskan ke Laravel.`);
            } catch (error) {
                console.error('Gagal webhook gambar:', error.message);
            }
        }
    }
    else if (msg.body.startsWith('#')) {
        try {
            await axios.post(LARAVEL_WEBHOOK_URL, {
                type: 'text',
                from: msg.from,
                sender_name: senderName,
                body: msg.body
            });
            console.log(`Perintah bot dari ${senderName} diteruskan ke Laravel.`);
        } catch (error) {
            console.error('Gagal webhook teks:', error.message);
        }
    }
});

client.initialize();

app.post('/send-message', async (req, res) => {
    const { number, message } = req.body;

    if (!number || !message) {
        return res.status(400).json({ error: 'Number dan message wajib diisi' });
    }

    try {
        const formattedNumber = `${number}@c.us`;
        await client.sendMessage(formattedNumber, message);
        res.json({ success: true, message: 'Pesan berhasil dikirim' });
    } catch (error) {
        res.status(500).json({ error: 'Gagal mengirim pesan', details: error.message });
    }
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log(`WA Engine API berjalan di port ${PORT}`);
});