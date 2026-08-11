<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule license management tasks
Schedule::command('license:auto-expire')->daily();
Schedule::command('license:reminder')->daily();
Schedule::command('activation:purge-stale')->daily();