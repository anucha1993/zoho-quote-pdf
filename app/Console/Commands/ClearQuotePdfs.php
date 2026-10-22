<?php

namespace App\Console\Commands;

use App\Services\QuotePdfService;
use Illuminate\Console\Command;

/**
 * ล้าง cache PDF ทั้งหมด (ใช้หลัง deploy หรือเมื่อต้องการบังคับสร้างใหม่ทุกใบ)
 *
 *   php artisan quotes:clear
 */
class ClearQuotePdfs extends Command
{
    protected $signature = 'quotes:clear';

    protected $description = 'ลบไฟล์ PDF ใบเสนอราคาที่ cache ไว้ทั้งหมด เพื่อบังคับสร้างใหม่จากข้อมูลล่าสุด';

    public function handle(QuotePdfService $pdf): int
    {
        $deleted = $pdf->clearAll();

        $this->info("Deleted {$deleted} cached PDF file(s). Template version: {$pdf->templateVersion()}");

        return self::SUCCESS;
    }
}
