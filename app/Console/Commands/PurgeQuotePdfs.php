<?php

namespace App\Console\Commands;

use App\Services\QuotePdfService;
use Illuminate\Console\Command;

/**
 * ลบไฟล์ PDF ใน storage/app/quotes ที่เก่ากว่าจำนวนวันที่กำหนด
 * (ตั้ง schedule ไว้ใน routes/console.php ให้รันทุกวัน)
 */
class PurgeQuotePdfs extends Command
{
    protected $signature = 'quotes:purge {--days= : ลบไฟล์ที่เก่ากว่า N วัน (ค่าเริ่มต้นจาก config quote.retention_days)}';

    protected $description = 'ลบไฟล์ PDF ใบเสนอราคาที่หมดอายุออกจาก storage';

    public function handle(QuotePdfService $pdf): int
    {
        $days = (int) ($this->option('days') ?: config('quote.retention_days', 7));

        $deleted = $pdf->purgeOlderThan($days);

        $this->info("Deleted {$deleted} PDF file(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
