<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Revolution\Google\Sheets\Facades\Sheets;

class TestSheets extends Command
{
    protected $signature = 'test:sheets';
    protected $description = 'Test koneksi ke Google Sheets';

    public function handle()
    {
        $this->info('Mencoba terhubung ke Google Sheets...');

        try {
            // Ganti 'Sheet1' dengan nama tab di Google Sheets Anda (biasanya Sheet1 atau Form Responses 1)
            $sheetName = 'Sheet1';

            // Mengambil data dari sheet
            $data = Sheets::spreadsheet(env('SPREADSHEET_ID'))->sheet($sheetName)->get();

            if ($data->isEmpty()) {
                $this->warn('Koneksi berhasil, tetapi Sheet kosong.');
            } else {
                $this->info('Koneksi BERHASIL! Berikut data baris pertama (Header):');
                // Menampilkan baris pertama (header)
                $this->line(json_encode($data->first()));
            }

        } catch (\Exception $e) {
            $this->error('Gagal terhubung: ' . $e->getMessage());
        }
    }
}