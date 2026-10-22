<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| ลบไฟล์ PDF ที่เก่ากว่า config('quote.retention_days') ทุกวันตี 2
| (ต้องตั้ง cron: * * * * * php /path/artisan schedule:run >> /dev/null 2>&1)
*/
Schedule::command('quotes:purge')->dailyAt('02:00');
