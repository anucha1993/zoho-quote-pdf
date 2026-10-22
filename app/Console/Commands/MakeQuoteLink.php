<?php

namespace App\Console\Commands;

use App\Support\QuoteForm;
use App\Support\QuoteLink;
use Illuminate\Console\Command;

/**
 * สร้างลิงก์/signature ของ Quote สำหรับทดสอบ หรือเทียบค่ากับฝั่ง Deluge
 *
 *   php artisan quotes:link 6274719000184443042
 *   php artisan quotes:link 6274719000184443042 --form=FA-SA-TH
 */
class MakeQuoteLink extends Command
{
    protected $signature = 'quotes:link {id : Zoho Quote record ID} {--form= : slug ของแบบฟอร์ม (ค่าเริ่มต้นจาก config quote.default_form)}';

    protected $description = 'แสดง signature และ URL ของใบเสนอราคาตาม record ID และแบบฟอร์ม';

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        $form = QuoteForm::find($this->option('form') ?: null);

        if (! $form) {
            $this->error('ไม่พบแบบฟอร์ม "'.$this->option('form').'" — ดูรายการใน config/quote.php หรือ resources/views/pdf/forms/');

            return self::FAILURE;
        }

        $this->table(['Key', 'Value'], [
            ['Record ID', $id],
            ['Form', $form->slug.' ('.$form->view.')'],
            ['Signature', QuoteLink::sign($id)],
            ['Signed URL', QuoteLink::url($id, form: $form->slug)],
            ['Signed download', QuoteLink::url($id, download: true, form: $form->slug)],
            ['Short URL', config('quote.allow_unsigned') ? QuoteLink::shortUrl($id, form: $form->slug) : '(ปิดอยู่ — ตั้ง QUOTE_ALLOW_UNSIGNED=true)'],
        ]);

        return self::SUCCESS;
    }
}
