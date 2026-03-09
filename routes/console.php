<?php

use Illuminate\Support\Facades\Schedule;

// Generate jadwal baru ke tab log setiap jam 00:01 pagi
Schedule::command('wa:generate')->dailyAt('00:01');

// Eksekusi pemeriksaan waktu setiap menit
Schedule::command('wa:alarm')->everyMinute();

Schedule::command('wa:cleanup')->dailyAt('01:00');
