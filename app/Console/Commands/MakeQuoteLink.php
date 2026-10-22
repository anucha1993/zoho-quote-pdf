<?php

namespace App\Console\Commands;

use App\Support\QuoteLink;
use Illuminate\Console\Command;

/**
 * สร้างลิงก์/signature ของ Quote สำหรับทดสอบ หรือเทียบค่ากับฝั่ง Deluge
 *
 *   php artisan quotes:link 6123456000000123456
 */
class MakeQuoteLink extends Command
{
    protected $signature = 'quotes:link {id : Zoho Quote record ID}';

    protected $description = 'แสดง signature และ URL ของใบเสนอราคาตาม record ID';

    public function handle(): int
    {
        $id = (string) $this->argument('id');

        $this->table(['Key', 'Value'], [
            ['Record ID', $id],
            ['Signature', QuoteLink::sign($id)],
            ['View URL', QuoteLink::url($id)],
            ['Download URL', QuoteLink::url($id, download: true)],
        ]);

        return self::SUCCESS;
    }
}
