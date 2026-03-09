<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Revolution\Google\Sheets\Facades\Sheets;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class WhatsappWebhookController extends Controller
{
    private $spreadsheetId;

    public function __construct()
    {
        $this->spreadsheetId = env('SPREADSHEET_ID');
    }

    public function handle(Request $request)
    {
        $type = $request->input('type');
        $fromNumber = str_replace('@c.us', '', $request->input('from'));
        $senderName = $request->input('sender_name', 'Pengguna');
        $body = trim($request->input('body', ''));

        // ==========================================
        // 1. PENANGANAN PESAN TEKS (PERINTAH BOT)
        // ==========================================
        if ($type === 'text') {
            $pesanUpper = strtoupper($body);

            if (str_starts_with($pesanUpper, '#BUAT_PENGINGAT')) {
                return $this->buatPengingat($fromNumber, $senderName, $body);
            } elseif (str_starts_with($pesanUpper, '#LIST_PENGINGAT')) {
                return $this->listPengingat($fromNumber);
            } elseif (str_starts_with($pesanUpper, '#EDIT_PENGINGAT')) {
                return $this->editPengingat($fromNumber, $body);
            } elseif (str_starts_with($pesanUpper, '#HAPUS_PENGINGAT')) { // <--- TAMBAHAN BARU
                return $this->hapusPengingat($fromNumber, $body);
            }

            // Abaikan chat biasa
            return response()->json(['status' => 'Ignored']);
        }

        // ==========================================
        // 2. PENANGANAN GAMBAR (VALIDASI TUGAS)
        // ==========================================
        $base64Image = $request->input('media_data');
        if (!$base64Image)
            return response()->json(['status' => 'Abaikan'], 200);

        try {
            $imageData = base64_decode($base64Image);
            $imageAnnotator = new ImageAnnotatorClient(['credentials' => storage_path('app/credentials.json')]);

            // Setup request ke Google Vision
            $image = new \Google\Cloud\Vision\V1\Image();
            $image->setContent($imageData);
            $feature = new \Google\Cloud\Vision\V1\Feature();
            $feature->setType(\Google\Cloud\Vision\V1\Feature\Type::TEXT_DETECTION);
            $requestObj = new \Google\Cloud\Vision\V1\AnnotateImageRequest();
            $requestObj->setImage($image);
            $requestObj->setFeatures([$feature]);
            $batchRequest = new \Google\Cloud\Vision\V1\BatchAnnotateImagesRequest();
            $batchRequest->setRequests([$requestObj]);

            $response = $imageAnnotator->batchAnnotateImages($batchRequest);
            $texts = $response->getResponses()[0]->getTextAnnotations();
            $imageAnnotator->close();

            if (count($texts) > 0) {
                $detectedText = $texts[0]->getDescription();
                $pattern = '/\d{1,2}\s+[a-zA-Z]+\s+\d{4}\s+at\s+\d{2}\.\d{2}\.\d{2}/i';

                if (preg_match($pattern, $detectedText, $matches)) {
                    $cleanTime = str_replace(['at ', '.'], ['', ':'], $matches[0]);
                    $waktuFoto = Carbon::parse($cleanTime);

                    // Lakukan validasi ke tab 'Log'
                    $this->validasiFoto($fromNumber, $waktuFoto, $imageData);
                } else {
                    $this->kirimPesanWa($fromNumber, "❌ Format waktu tidak ditemukan di foto watermark Anda.");
                }
            } else {
                $this->kirimPesanWa($fromNumber, "❌ Tidak ada teks terdeteksi pada gambar.");
            }

            return response()->json(['status' => 'Berhasil']);

        } catch (\Exception $e) {
            Log::error("Webhook Image Error: " . $e->getMessage());
            return response()->json(['status' => 'Error'], 500);
        }
    }

    // --- FUNGSI: MEMBUAT PENGINGAT (Simpan ke Tab Master) ---
    private function buatPengingat($noWa, $nama, $pesan)
    {
        // Format: #BUAT_PENGINGAT 1,2,3,4,5,6,7 12:00 | 12:30
        $text = trim(str_ireplace('#BUAT_PENGINGAT', '', $pesan));

        // Memecah berdasarkan spasi untuk mendapatkan array: [hari, jam_mulai, |, jam_batas]
        // Menggunakan regex agar lebih aman memisahkan pemisah '|'
        if (preg_match('/^([\d,]+)\s+(\d{2}:\d{2})\s*\|\s*(\d{2}:\d{2})$/', $text, $matches)) {
            $hari = $matches[1];
            $jamMulai = $matches[2];
            $jamBatas = $matches[3];

            $idAlarm = 'SHLT-' . strtoupper(substr(uniqid(), -4));

            Sheets::spreadsheet($this->spreadsheetId)->sheet('Master')->append([
                [$idAlarm, $noWa, $nama, $hari, $jamMulai, $jamBatas]
            ]);

            $balasan = "✅ *PENGINGAT SHOLAT BARU TERSIMPAN*\n\nID: *{$idAlarm}*\nHari: {$hari} (1=Sen, 7=Min)\nJam Mulai: {$jamMulai}\nBatas Waktu: {$jamBatas}\n\nSistem akan otomatis menjadwalkan pengingat sholat ini pada hari-hari tersebut.";
            $this->kirimPesanWa($noWa, $balasan);
        } else {
            $this->kirimPesanWa($noWa, "❌ *Format Salah*\n\nContoh yang benar:\n*#BUAT_PENGINGAT 1,2,3,4,5,6,7 12:00 | 12:30*");
        }
        return response()->json(['status' => 'OK']);
    }

    // --- FUNGSI: LIST PENGINGAT ---
    private function listPengingat($noWa)
    {
        $rows = Sheets::spreadsheet($this->spreadsheetId)->sheet('Master')->get();
        if ($rows->count() <= 1) {
            $this->kirimPesanWa($noWa, "📂 Anda belum memiliki pengingat sholat yang terdaftar.");
            return response()->json(['status' => 'OK']);
        }

        $header = $rows->pull(0);
        $values = Sheets::collection($header, $rows);

        $pesan = "📂 *DAFTAR PENGINGAT SHOLAT ANDA*\n\n";
        $ada = false;

        foreach ($values as $row) {
            if (!empty($row['id_alarm']) && $row['no_wa'] == $noWa) {
                $ada = true;
                $pesan .= "🔹 *{$row['id_alarm']}*\nHari: {$row['hari']}\nJam: {$row['jam_mulai']} s/d {$row['jam_batas']}\n\n";
            }
        }

        if (!$ada)
            $pesan = "📂 Anda belum memiliki pengingat sholat yang terdaftar.";

        $this->kirimPesanWa($noWa, $pesan);
        return response()->json(['status' => 'OK']);
    }

    // --- FUNGSI: EDIT PENGINGAT ---
    private function editPengingat($noWa, $pesan)
    {
        // Format: #EDIT_PENGINGAT SHLT-1234 1,2 09:00 | 11:00
        $text = trim(str_ireplace('#EDIT_PENGINGAT', '', $pesan));

        if (preg_match('/^(SHLT-\w{4})\s+([\d,]+)\s+(\d{2}:\d{2})\s*\|\s*(\d{2}:\d{2})$/i', $text, $matches)) {
            $idAlarmCari = strtoupper($matches[1]);
            $hariBaru = $matches[2];
            $jamMulaiBaru = $matches[3];
            $jamBatasBaru = $matches[4];

            $rows = Sheets::spreadsheet($this->spreadsheetId)->sheet('Master')->get();
            $header = $rows->pull(0);
            $values = Sheets::collection($header, $rows);

            $rowIndex = 1;
            $ditemukan = false;

            foreach ($values as $row) {
                $rowIndex++;
                if ($row['id_alarm'] == $idAlarmCari && $row['no_wa'] == $noWa) {
                    $ditemukan = true;
                    // Update kolom Hari, Jam Mulai, Jam Batas (Kolom D, E, F)
                    Sheets::spreadsheet($this->spreadsheetId)->sheet('Master')
                        ->range("D{$rowIndex}:F{$rowIndex}")
                        ->update([[$hariBaru, $jamMulaiBaru, $jamBatasBaru]]);

                    $this->kirimPesanWa($noWa, "✅ Pengingat *{$idAlarmCari}* berhasil diperbarui.");
                    break;
                }
            }

            if (!$ditemukan)
                $this->kirimPesanWa($noWa, "❌ Pengingat dengan ID *{$idAlarmCari}* tidak ditemukan atau bukan milik Anda.");
        } else {
            $this->kirimPesanWa($noWa, "❌ *Format Edit Salah*\n\nContoh yang benar:\n*#EDIT_PENGINGAT SHLT-ABCD 1,2 09:00 | 11:00*");
        }
        return response()->json(['status' => 'OK']);
    }

    // --- FUNGSI: VALIDASI FOTO KE TAB LOG ---
    // --- FUNGSI: VALIDASI FOTO KE TAB LOG ---
    private function validasiFoto($noWa, Carbon $waktuFoto, $imageData)
    {
        $rows = Sheets::spreadsheet($this->spreadsheetId)->sheet('Log')->get();
        if ($rows->isEmpty() || $rows->count() == 1) {
            $this->kirimPesanWa($noWa, "⚠ Tidak ada jadwal sholat yang aktif di tab Log untuk saat ini.");
            return;
        }

        $header = $rows->pull(0);
        $values = Sheets::collection($header, $rows);

        $rowIndex = 1;
        $tugasDitemukan = false;

        foreach ($values as $row) {
            $rowIndex++;

            if ($row['no_wa'] == $noWa && empty($row['status'])) {
                $tugasDitemukan = true;
                $batasWaktu = Carbon::parse($row['batas_waktu']);

                if ($waktuFoto->lessThanOrEqualTo($batasWaktu)) {
                    $statusAkhir = 'TRUE';

                    $this->kirimPesanWa($noWa, "⏳ Foto valid. Sedang menyimpan bukti sholat...");

                    // Proses Simpan ke Server Lokal (VPS/Localhost)
                    $linkFoto = $this->simpanKeLokal($row['id_tugas'], $row['nama'], $imageData);

                    // Update Kolom 'status' (G) dan 'bukti_foto' (H)
                    Sheets::spreadsheet($this->spreadsheetId)->sheet('Log')
                        ->range("G{$rowIndex}:H{$rowIndex}")
                        ->update([[$statusAkhir, $linkFoto]]);

                    $pesan = "✅ *BERHASIL*\nSesi sholat id *{$row['id_tugas']}* telah diselesaikan.\n🔗 Link Bukti: {$linkFoto}\nTerima kasih, {$row['nama']}.";
                } else {
                    $statusAkhir = 'FALSE';
                    // Update Kolom 'status' saja (G)
                    Sheets::spreadsheet($this->spreadsheetId)->sheet('Log')
                        ->range("G{$rowIndex}")->update([[$statusAkhir]]);

                    $pesan = "❌ *GAGAL*\nAnda mengirimkan foto melewati batas waktu yang ditentukan ({$batasWaktu->format('H:i')}). Status ditandai FALSE.";
                }

                $this->kirimPesanWa($noWa, $pesan);
                break;
            }
        }

        if (!$tugasDitemukan)
            $this->kirimPesanWa($noWa, "⚠ Semua sesi sholat Anda hari ini sudah diselesaikan atau sudah kedaluwarsa.");
    }

    // --- FUNGSI BARU: SIMPAN GAMBAR KE SERVER LOKAL ---
    private function simpanKeLokal($idTugas, $nama, $imageData)
    {
        try {
            $uuid = \Illuminate\Support\Str::uuid()->toString();
            $namaFile = "bukti_tugas/Bukti_{$idTugas}_{$uuid}.jpg";

            // Simpan ke folder storage/app/public/bukti_tugas
            Storage::disk('public')->put($namaFile, $imageData);

            // Mengembalikan URL publik
            return asset('storage/' . $namaFile);

        } catch (\Exception $e) {
            Log::error("Local Upload Error: " . $e->getMessage());
            return "Gagal menyimpan foto sholat.";
        }
    }

    private function hapusPengingat($noWa, $pesan)
    {
        // Format: #HAPUS_PENGINGAT SHLT-1234
        $text = trim(str_ireplace('#HAPUS_PENGINGAT', '', $pesan));
        $idAlarmCari = strtoupper(trim($text));

        if (empty($idAlarmCari)) {
            $this->kirimPesanWa($noWa, "❌ *Format Salah*\n\nContoh yang benar:\n*#HAPUS_PENGINGAT SHLT-ABCD*");
            return response()->json(['status' => 'OK']);
        }

        $rows = Sheets::spreadsheet($this->spreadsheetId)->sheet('Master')->get();
        if ($rows->count() <= 1) {
            $this->kirimPesanWa($noWa, "📂 Anda belum memiliki pengingat sholat.");
            return response()->json(['status' => 'OK']);
        }

        $header = $rows->pull(0);
        $values = Sheets::collection($header, $rows);

        $rowIndex = 1;
        $ditemukan = false;

        foreach ($values as $row) {
            $rowIndex++;

            // Pastikan ID cocok dan nomor WA sesuai agar tidak bisa hapus pengingat orang lain
            if (isset($row['id_alarm']) && $row['id_alarm'] == $idAlarmCari && $row['no_wa'] == $noWa) {
                $ditemukan = true;

                // Menimpa baris tersebut dengan data kosong (A sampai F)
                Sheets::spreadsheet($this->spreadsheetId)->sheet('Master')
                    ->range("A{$rowIndex}:F{$rowIndex}")
                    ->update([['', '', '', '', '', '']]);

                $this->kirimPesanWa($noWa, "✅ Pengingat sholat *{$idAlarmCari}* berhasil dihapus secara permanen.");
                break;
            }
        }

        if (!$ditemukan) {
            $this->kirimPesanWa($noWa, "❌ Pengingat sholat dengan ID *{$idAlarmCari}* tidak ditemukan atau bukan milik Anda.");
        }

        return response()->json(['status' => 'OK']);
    }

    private function kirimPesanWa($to, $message)
    {
        try {
            Http::post('http://wa-engine:3000/send-message', ['number' => $to, 'message' => $message]);
        } catch (\Exception $e) {
            Log::error("Gagal mengirim WA: " . $e->getMessage());
        }
    }
}