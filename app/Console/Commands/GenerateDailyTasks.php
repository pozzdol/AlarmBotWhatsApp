<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Revolution\Google\Sheets\Facades\Sheets;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateDailyTasks extends Command
{
    protected $signature = 'wa:generate';
    protected $description = 'Generate jadwal sholat harian ke tab Log berdasarkan tab Master';

    public function handle()
    {
        $spreadsheetId = env('SPREADSHEET_ID');
        $hariIni = Carbon::now()->dayOfWeekIso; // 1 = Senin, 7 = Minggu
        $tanggalHariIni = Carbon::now()->format('Y-m-d');

        try {
            $rows = Sheets::spreadsheet($spreadsheetId)->sheet('Master')->get();
            if ($rows->count() <= 1) {
                $this->info('Tidak ada pengingat sholat di tab Master.');
                return;
            }

            $header = $rows->pull(0);
            $values = Sheets::collection($header, $rows);
            $tugasBaru = [];

            foreach ($values as $row) {
                // Lewati baris yang kosong (yang sudah dihapus)
                if (empty($row['id_alarm']))
                    continue;

                // Cek apakah hari ini termasuk dalam jadwal alarm ini
                $hariJadwal = explode(',', $row['hari']);
                if (in_array((string) $hariIni, $hariJadwal)) {

                    $idTugas = 'SESI-' . strtoupper(substr(uniqid(), -5));
                    $jadwalTugas = Carbon::parse($tanggalHariIni . ' ' . $row['jam_mulai'])->format('Y-m-d H:i:00');
                    $batasWaktu = Carbon::parse($tanggalHariIni . ' ' . $row['jam_batas'])->format('Y-m-d H:i:00');

                    $tugasBaru[] = [
                        $idTugas,
                        $row['id_alarm'],
                        $row['no_wa'],
                        $row['nama'],
                        $jadwalTugas,
                        $batasWaktu,
                        '' // Status kosong
                    ];
                }
            }

            if (!empty($tugasBaru)) {
                Sheets::spreadsheet($spreadsheetId)->sheet('Log')->append($tugasBaru);
                $this->info(count($tugasBaru) . ' sesi sholat berhasil digenerate untuk hari ini.');
            } else {
                $this->info('Tidak ada jadwal pengingat sholat untuk hari ini.');
            }

        } catch (\Exception $e) {
            $this->error("Error Generate Tugas: " . $e->getMessage());
        }
    }
}