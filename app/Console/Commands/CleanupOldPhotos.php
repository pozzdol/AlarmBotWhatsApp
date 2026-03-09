<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanupOldPhotos extends Command
{
    protected $signature = 'wa:cleanup';
    protected $description = 'Menghapus foto bukti sholat yang usianya lebih dari 30 hari dari storage lokal';

    public function handle()
    {
        $this->info("Memulai proses pembersihan foto lama...");

        $disk = Storage::disk('public');
        $directory = 'bukti_sholat';

        if (!$disk->exists($directory)) {
            $this->warn("Folder {$directory} belum ada. Proses dibatalkan.");
            return;
        }

        $files = $disk->files($directory);
        $now = Carbon::now();
        $jumlahDihapus = 0;

        foreach ($files as $file) {
            // Ambil waktu file dengan menyamakan Timezone Laravel
            $lastModified = Carbon::createFromTimestamp($disk->lastModified($file))
                ->setTimezone(config('app.timezone'));

            // Perhitungan umur yang benar: (Waktu File) selisih hari ke (Waktu Saat Ini)
            $umurHari = $lastModified->diffInDays($now);

            // LOGIKA PRODUCTION: Hapus jika berumur 30 hari atau lebih
            if ($umurHari >= 30) {
                $disk->delete($file);
                $jumlahDihapus++;
                Log::info("Auto-Delete: Menghapus foto lama ({$umurHari} hari) -> {$file}");
            }
        }

        $pesan = "Pembersihan selesai! {$jumlahDihapus} foto lama berhasil dihapus.";
        $this->info($pesan);
        Log::info($pesan);
    }
}