<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Revolution\Google\Sheets\Facades\Sheets;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWaAlarm extends Command
{
    protected $signature = 'wa:alarm';
    protected $description = 'Mengecek Log dan spam pengingat sholat WA setiap 10 menit';

    public function handle()
    {
        $spreadsheetId = env('SPREADSHEET_ID');
        $now = Carbon::now()->startOfMinute(); // Waktu saat ini (detik di-set 00)

        $this->info("Pengecekan Pengingat Waktu Server: " . $now->format('Y-m-d H:i'));

        try {
            $rows = Sheets::spreadsheet($spreadsheetId)->sheet('Log')->get();
            if ($rows->count() <= 1)
                return;

            $header = $rows->pull(0);
            $values = Sheets::collection($header, $rows);

            $rowIndex = 1;

            foreach ($values as $row) {
                $rowIndex++;

                // Abaikan baris kosong atau yang sudah berstatus TRUE/FALSE
                if (empty($row['id_tugas']) || !empty($row['status']))
                    continue;

                $waktuMulai = Carbon::parse($row['jadwal_tugas']);
                $batasWaktu = Carbon::parse($row['batas_waktu']);

                // 1. CEK WAKTU HABIS
                if ($now->greaterThan($batasWaktu)) {
                    // Update status jadi FALSE
                    Sheets::spreadsheet($spreadsheetId)->sheet('Log')->range("G{$rowIndex}")->update([['FALSE']]);

                    $pesan = "❌ *WAKTU HABIS* ❌\n{$row['nama']}, Anda mengabaikan panggilan sholat *{$row['id_tugas']}* hingga melewati batas akhir ({$batasWaktu->format('H:i')}).\nSesi ini telah dikunci dengan status GAGAL.";
                    $this->kirimPesanWa($row['no_wa'], $pesan);
                    $this->info("-> Sesi sholat {$row['id_tugas']} kedaluwarsa (FALSE).");
                    continue;
                }

                // 2. CEK RENTANG WAKTU & SPAM 10 MENIT
                if ($now->greaterThanOrEqualTo($waktuMulai) && $now->lessThanOrEqualTo($batasWaktu)) {

                    // Hitung selisih menit dari waktu mulai
                    $selisihMenit = $now->diffInMinutes($waktuMulai);

                    // Bunyikan alarm pada menit ke-0, 10, 20, 30, dst (selama rentang waktu)
                    if ($selisihMenit % 10 === 0) {

                        // Pesan berbeda untuk peringatan pertama (menit 0)
                        if ($selisihMenit == 0) {
                            $pesan = "⏰ *PENGINGAT SHOLAT DIMULAI* ⏰\n\nHalo {$row['nama']},\nSaatnya melaksanakan sholat untuk sesi *{$row['id_tugas']}*.\n\nBatas waktu Anda sampai jam *{$batasWaktu->format('H:i')}*.\nSilakan kirimkan foto validasi sekarang juga.";
                        } else {
                            $pesan = "🚨 *PENGINGAT (SPAM) - MENIT KE-{$selisihMenit}* 🚨\n\n{$row['nama']}, Anda belum mengirimkan foto untuk sesi *{$row['id_tugas']}*!\nSegera kirimkan foto sebelum batas waktu pukul {$batasWaktu->format('H:i')}.";
                        }

                        $this->kirimPesanWa($row['no_wa'], $pesan);
                        $this->info("-> Pengingat terkirim ke {$row['nama']} (Menit ke-{$selisihMenit})");
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error("Error WA Alarm: " . $e->getMessage());
        }
    }

    private function kirimPesanWa($to, $message)
    {
        try {
            Http::post('http://wa-engine:3000/send-message', ['number' => $to, 'message' => $message]);
        } catch (\Exception $e) {
            Log::error("Gagal mengirim spam: " . $e->getMessage());
        }
    }
}