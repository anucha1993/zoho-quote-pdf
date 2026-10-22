<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * แปลง record จาก Zoho CRM (Quote + related) ให้เป็นโครงสร้างที่ Blade view ใช้งานง่าย
 *
 * ทุก key ของ field อ่านจาก config('zoho.fields') เพื่อให้แก้ API name ได้ที่เดียว
 * ชื่อ method อิงตาม merge field ในแบบฟอร์ม Excel ต้นแบบ (sheet "คนเมียวดี (เขียว-แดง)")
 */
class QuoteData implements PrintableRecord
{
    protected array $f;

    protected array $custom;

    public function __construct(
        public readonly array $quote,
        public readonly ?array $account = null,
        public readonly ?array $contact = null,
        public readonly ?array $owner = null,
        public readonly ?array $employee = null,
    ) {
        $this->f = config('zoho.fields');
        $this->custom = $this->f['custom'] ?? [];
    }

    /* ------------------------------------------------------------------ */
    /*  ข้อมูลหลักของเอกสาร */
    /* ------------------------------------------------------------------ */

    public function id(): string
    {
        return (string) ($this->quote['id'] ?? '');
    }

    /**
     * ${Quotes.Quote Number} — เลขที่เอกสาร (ใช้เป็นชื่อไฟล์ด้วย)
     * ใน org นี้คือ field "Quotation Number" (Quotation_Number) เช่น "IO2026-021613"
     * อ่านตาม config zoho.fields.document_number แล้วถอยไปยัง number_fallback และ record id ตามลำดับ
     */
    public function number(): string
    {
        $candidates = array_merge(
            [$this->f['document_number'] ?? $this->f['quote_number']],
            $this->f['number_fallback'] ?? [],
            [$this->f['quote_number']]
        );

        foreach ($candidates as $key) {
            $value = $this->quote[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $this->id();
    }

    public function subject(): string
    {
        return (string) ($this->quote[$this->f['subject']] ?? '');
    }

    /** ${Quotes.หัวข้อการให้บริการ} — ถ้าว่างจะเว้นว่างเหมือน mail merge ของต้นแบบ */
    public function serviceSubject(): string
    {
        return trim((string) $this->custom('service_subject', ''));
    }

    public function stage(): string
    {
        return (string) ($this->quote[$this->f['quote_stage']] ?? '');
    }

    /**
     * Quote อยู่ในสถานะอนุมัติแล้วหรือไม่ (เทียบกับ config quote.approved_stages ไม่สนตัวพิมพ์)
     * ใช้ตัดสินว่าจะพิมพ์วันที่ลงเอกสารหรือไม่
     */
    public function isApproved(): bool
    {
        $stage = mb_strtolower(trim($this->stage()));

        foreach ((array) config('quote.approved_stages', []) as $approved) {
            if ($stage !== '' && $stage === mb_strtolower(trim((string) $approved))) {
                return true;
            }
        }

        return false;
    }

    public function createdAt(): ?Carbon
    {
        return $this->date($this->quote[$this->f['created_time']] ?? null);
    }

    /** ${Quotes.วันที่เสนอราคา} — ถ้าว่างใช้ Created_Time */
    public function quoteDate(): ?Carbon
    {
        return $this->date($this->custom('quote_date')) ?? $this->createdAt();
    }

    public function modifiedTime(): string
    {
        return (string) ($this->quote[$this->f['modified_time']] ?? '');
    }

    public function validTill(): ?Carbon
    {
        return $this->date($this->quote[$this->f['valid_till']] ?? null);
    }

    /** ${Quotes.ISO} — รหัสแบบฟอร์ม ถ้าว่างใช้ config quote.form_code */
    public function isoCode(): string
    {
        return trim((string) ($this->custom('iso') ?: config('quote.form_code', '')));
    }

    /**
     * ชื่อไฟล์สำหรับดาวน์โหลด เช่น "P-PJ 2026-0688.pdf"
     * (ตัดอักขระที่ใช้ในชื่อไฟล์ไม่ได้ออก แต่คงช่องว่าง/ขีดไว้)
     */
    public function filename(): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|\r\n]+/u', '-', $this->number()) ?: $this->id();

        return trim($name).'.pdf';
    }

    /* ------------------------------------------------------------------ */
    /*  ลูกค้า */
    /* ------------------------------------------------------------------ */

    /** ${Quotes.Account Name} */
    public function customerName(): string
    {
        return (string) (
            $this->account['Account_Name']
            ?? $this->quote[$this->f['account']]['name']
            ?? ''
        );
    }

    /** ${Quotes.Contact Name} */
    public function contactName(): string
    {
        return (string) (
            $this->contact['Full_Name']
            ?? $this->quote[$this->f['contact']]['name']
            ?? ''
        );
    }

    /** ${Contacts.Mobile} — ถ้าไม่มีใช้ Phone */
    public function contactMobile(): string
    {
        return (string) ($this->contact['Mobile'] ?? $this->contact['Phone'] ?? $this->account['Phone'] ?? '');
    }

    public function contactEmail(): string
    {
        return (string) ($this->contact['Email'] ?? '');
    }

    /**
     * ที่อยู่บรรทัดที่ 1: ${Quotes.Billing Street} ${Quotes.Billing City}
     */
    public function billingLine1(): string
    {
        $b = $this->f['billing'];

        return $this->joinParts([$this->quote[$b['street']] ?? null, $this->quote[$b['city']] ?? null]);
    }

    /**
     * ที่อยู่บรรทัดที่ 2: ${Quotes.Billing State}  ${Quotes.Billing Code}
     */
    public function billingLine2(): string
    {
        $b = $this->f['billing'];

        return $this->joinParts([$this->quote[$b['state']] ?? null, $this->quote[$b['code']] ?? null], '  ');
    }

    /**
     * ที่อยู่รวมบรรทัดเดียว
     */
    public function billingAddress(): string
    {
        return trim($this->billingLine1().' '.$this->billingLine2());
    }

    /* ------------------------------------------------------------------ */
    /*  พนักงานขาย */
    /* ------------------------------------------------------------------ */

    public function ownerName(): string
    {
        return (string) ($this->owner['full_name'] ?? $this->quote[$this->f['owner']]['name'] ?? '');
    }

    public function ownerEmail(): string
    {
        return (string) ($this->owner['email'] ?? $this->quote[$this->f['owner']]['email'] ?? '');
    }

    public function ownerPhone(): string
    {
        return (string) ($this->owner['mobile'] ?? $this->owner['phone'] ?? '');
    }

    /**
     * ${Quotes.ชื่อจริง} ${Quotes.นามสกุล} (ไม่มีชื่อเล่น) — ใช้ในกล่องลงนามผู้เสนอขาย
     * ถ้าว่างใช้ชื่อ Owner
     */
    public function salesFullName(): string
    {
        $name = $this->joinParts([$this->custom('sales_first_name'), $this->custom('sales_last_name')]);

        return $name !== '' ? $name : $this->ownerName();
    }

    public function employeeRecord(): ?array
    {
        return $this->employee;
    }

    /**
     * File_Id__s ของรูปลายเซ็นพนักงานขาย (image upload field "Image Sign" ใน module Staffs ผ่าน lookup รหัสพนักงาน)
     * null = ไม่มีรูป
     */
    public function salesSignatureFileId(): ?string
    {
        $field = $this->custom['employee_signature'] ?? 'Image_Sing';
        $value = $this->employee[$field] ?? null;

        if (is_array($value)) {
            $first = $value[0] ?? null;
            $fileId = is_array($first) ? ($first['File_Id__s'] ?? null) : null;

            return is_string($fileId) && $fileId !== '' ? $fileId : null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * ${Quotes.ชื่อจริง} ${Quotes.นามสกุล} ${Lookup:รหัสพนักงาน.ชื่อเล่น}
     * ถ้า custom field ว่างทั้งหมด ใช้ชื่อ Owner
     */
    public function salesName(): string
    {
        $nickname = (string) ($this->employee[$this->custom['employee_nickname'] ?? 'Nickname'] ?? '');

        $name = $this->joinParts([
            $this->custom('sales_first_name'),
            $this->custom('sales_last_name'),
            $nickname,
        ]);

        return $name !== '' ? $name : $this->ownerName();
    }

    /* ------------------------------------------------------------------ */
    /*  รายการสินค้า (subform "QT Thailand") */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array{
     *   no:int, name:string, code:string, description:string, quantity:float,
     *   unit:string, list_price:float, discount:float, tax:float, total:float,
     *   recurring_amount:?float
     * }>
     */
    public function items(): array
    {
        $rows = $this->itemRows();
        $k = $this->f['item'];
        $items = [];

        foreach (array_values($rows) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            $product = $row[$k['product']] ?? [];

            // ${QT Thailand.Product Name} อาจเป็น lookup (array) หรือข้อความ
            $name = is_array($product) ? ($product['name'] ?? '') : (string) $product;

            $items[] = [
                // ${QT Thailand.S.NO} = Sequence_Number ของ subform
                'no' => (int) ($row['Sequence_Number'] ?? $row['S_NO'] ?? $i + 1),
                'name' => (string) $name,
                // ชื่อสินค้าภาษาจีน (field "Product Name (CN)" ใน subform) — ฟอร์ม FA-SA-CN แสดงใต้ชื่อไทย
                'name_cn' => trim((string) ($row['Product_Name_CN'] ?? '')),
                'code' => (string) (is_array($product) ? ($product['Product_Code'] ?? '') : ''),
                'description' => (string) ($row[$k['description']] ?? $row['Product_Description'] ?? ''),
                'quantity' => (float) ($row[$k['quantity']] ?? 0),
                'unit' => (string) ($row[$k['unit']] ?? ''),
                'list_price' => (float) ($row[$k['list_price']] ?? 0),
                'discount' => (float) ($row[$k['discount']] ?? 0),
                'tax' => (float) ($row[$k['tax']] ?? 0),
                // ${QT Thailand.Amount}: ใน subform ป้าย "Amount" คือ field Total (= ราคา × จำนวน ก่อนภาษี)
                'total' => (float) ($row[$k['total']] ?? $row['Amount'] ?? $row[$k['net_total']]
                    ?? ((float) ($row[$k['quantity']] ?? 0) * (float) ($row[$k['list_price']] ?? 0))),
                'recurring_amount' => isset($row[$k['recurring_amount']]) && $row[$k['recurring_amount']] !== null
                    ? (float) $row[$k['recurring_amount']]
                    : null,
            ];
        }

        return $items;
    }

    /**
     * หา subform รายการ: ใช้ชื่อใน config ก่อน แล้วลองชื่อสำรอง
     */
    protected function itemRows(): array
    {
        $candidates = array_merge([$this->f['items']], $this->f['items_fallback'] ?? []);

        foreach ($candidates as $key) {
            if (! empty($this->quote[$key]) && is_array($this->quote[$key])) {
                return $this->quote[$key];
            }
        }

        return [];
    }

    /* ------------------------------------------------------------------ */
    /*  สรุปยอด */
    /* ------------------------------------------------------------------ */

    public function subTotal(): float
    {
        return (float) ($this->quote[$this->f['sub_total']] ?? 0);
    }

    public function discount(): float
    {
        return (float) ($this->quote[$this->f['discount']] ?? 0);
    }

    public function tax(): float
    {
        return (float) ($this->quote[$this->f['tax']] ?? 0);
    }

    public function adjustment(): float
    {
        return (float) ($this->quote[$this->f['adjustment']] ?? 0);
    }

    /**
     * กล่องสรุปยอดท้ายตาราง (ตาม merge field «Quotes.QT …» ของฟอร์ม)
     * อ่าน field QT_* ก่อน ถ้าไม่มีถอยไปใช้ field มาตรฐานของ Zoho
     */
    public function totalAmount(): float
    {
        return $this->firstNumber(['QT_Total_Amount', 'Total_Amount', $this->f['sub_total']])
            ?? round(array_sum(array_column($this->items(), 'total')), 2);
    }

    public function totalDiscount(): float
    {
        return $this->firstNumber(['QT_Total_Discount', 'Total_Discount', $this->f['discount']]) ?? 0.0;
    }

    public function totalTax(): float
    {
        return $this->firstNumber(['QT_Total_Tax', 'Total_Tax', $this->f['tax']]) ?? 0.0;
    }

    /**
     * จำนวนเงินรวมทั้งสิ้น (รวม VAT) = «Quotes.QT Grand Total» — ใช้ทำ BahtText ด้วย
     */
    public function grandTotal(): float
    {
        return $this->firstNumber(['QT_Grand_Total', $this->f['grand_total']])
            ?? round($this->totalAmount() - $this->totalDiscount() + $this->totalTax(), 2);
    }

    /**
     * ชุดยอดสำหรับกล่องสรุปยอดของตาราง QT Thailand
     *
     * @return array{amount: float, discount: float, tax: float, grand: float}
     */
    public function totals(): array
    {
        return [
            'amount' => $this->totalAmount(),
            'discount' => $this->totalDiscount(),
            'tax' => $this->totalTax(),
            'grand' => $this->grandTotal(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  QT Destination (subform QT_Destination, field ลงท้าย _D) */
    /* ------------------------------------------------------------------ */

    /**
     * รายการค่าบริการปลายทาง — โครงสร้างเดียวกับ items() เพื่อใช้ partial ตารางร่วมกัน
     *
     * @return array<int, array{no:int, name:string, code:string, description:string, quantity:float, unit:string,
     *   list_price:float, discount:float, tax:float, total:float, recurring_amount:null}>
     */
    public function destinationItems(): array
    {
        $rows = $this->quote[$this->f['destination_items'] ?? 'QT_Destination'] ?? [];
        $items = [];

        foreach (array_values(is_array($rows) ? $rows : []) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            $product = $row['Product_Name_D'] ?? [];
            $name = is_array($product) ? ($product['name'] ?? '') : (string) $product;

            $items[] = [
                'no' => (int) ($row['LinkingModule1_Serial_Number'] ?? $i + 1),
                'name' => (string) $name,
                'code' => '',
                'description' => (string) ($row['Description_D'] ?? ''),
                'quantity' => (float) ($row['Quantity_D'] ?? 0),
                'unit' => '',
                'list_price' => (float) ($row['List_Price_D'] ?? 0),
                'discount' => (float) ($row['Discount_D'] ?? 0),
                'tax' => (float) ($row['Total_Tax_D'] ?? 0),
                'total' => (float) ($row['Amount_D'] ?? $row['Total_D']
                    ?? ((float) ($row['Quantity_D'] ?? 0) * (float) ($row['List_Price_D'] ?? 0))),
                'recurring_amount' => null,
            ];
        }

        return $items;
    }

    /**
     * ชุดยอดสำหรับกล่องสรุปยอดของตาราง QT Destination (field *_D ของ Quote)
     *
     * @return array{amount: float, discount: float, tax: float, grand: float}
     */
    public function destinationTotals(): array
    {
        $amount = $this->firstNumber(['Total_Amount_D', 'Sub_Total_D'])
            ?? round(array_sum(array_column($this->destinationItems(), 'total')), 2);
        $discount = $this->firstNumber(['Total_Discount_D']) ?? 0.0;
        $tax = $this->firstNumber(['Total_Tax_D']) ?? 0.0;

        return [
            'amount' => $amount,
            'discount' => $discount,
            'tax' => $tax,
            'grand' => $this->firstNumber(['Grand_Total_D']) ?? round($amount - $discount + $tax, 2),
        ];
    }

    /**
     * คืนค่าตัวเลขตัวแรกที่มีใน record ตามลำดับ key (null = ไม่มีเลย)
     */
    protected function firstNumber(array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $this->quote[$key] ?? null;
            if ($value !== null && $value !== '' && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    public function grandTotalText(): string
    {
        return BahtText::convert($this->grandTotal());
    }

    public function terms(): string
    {
        return (string) ($this->quote[$this->f['terms']] ?? '');
    }

    /** ${Quotes.เงื่อนไข และรายละเอียการชำระเงิน} — ถ้าว่างใช้ Terms_and_Conditions */
    public function paymentTerms(): string
    {
        return $this->mergeTokens(trim((string) ($this->custom('payment_terms') ?: $this->terms())));
    }

    /**
     * แทน merge token ในข้อความ: «Quotes.ป้ายชื่อ» หรือ ${Quotes.ป้ายชื่อ} → ค่าจาก field (ตาม config zoho.fields.merge_labels)
     * ถ้า field ไม่มีค่า หรือไม่รู้จัก token → แสดงป้ายชื่อแทน เช่น "เงื่อนไขการชำระเงิน" (ไม่โชว์โค้ด «» ในเอกสาร)
     */
    public function mergeTokens(string $text): string
    {
        if ($text === '' || ! preg_match('/«|\$\{/u', $text)) {
            return $text;
        }

        $labels = (array) ($this->f['merge_labels'] ?? []);

        return preg_replace_callback('/(?:«|\$\{)\s*Quotes\.\s*([^»}]+?)\s*(?:»|\})/u', function ($m) use ($labels) {
            $label = trim($m[1]);
            $api = $labels[$label] ?? null;

            if ($api === null) {
                // เทียบแบบไม่สนตัวพิมพ์/ช่องว่าง
                foreach ($labels as $l => $a) {
                    if (mb_strtolower(preg_replace('/\s+/u', '', $l)) === mb_strtolower(preg_replace('/\s+/u', '', $label))) {
                        $api = $a;
                        break;
                    }
                }
            }

            $value = $api ? ($this->quote[$api] ?? '') : '';

            if (is_array($value)) {
                $value = $value['name'] ?? '';
            }

            if ($api === ($this->f['custom']['quote_date'] ?? 'field2') && $value) {
                $value = self::dateFormat($this->date((string) $value));
            }

            $value = trim((string) $value);

            // picklist ที่ยังไม่เลือกจะเป็น "-None-" ให้ถือว่าว่าง
            if ($value === '' || $value === '-None-') {
                $value = $label;
            }

            return e($value);
        }, $text) ?? $text;
    }

    public function description(): string
    {
        return (string) ($this->quote[$this->f['description']] ?? '');
    }

    /**
     * ค่าของ custom field ระดับ Quote ตาม key ใน config('zoho.fields.custom')
     */
    public function custom(string $key, mixed $default = null): mixed
    {
        $api = $this->custom[$key] ?? null;

        if (! $api) {
            return $default;
        }

        $value = $this->quote[$api] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    /* ------------------------------------------------------------------ */
    /*  helpers */
    /* ------------------------------------------------------------------ */

    protected function date(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function joinParts(array $parts, string $glue = ' '): string
    {
        $parts = array_filter(array_map(
            fn ($v) => is_scalar($v) ? trim((string) $v) : '',
            $parts
        ), fn ($v) => $v !== '');

        return implode($glue, $parts);
    }

    /**
     * จัดรูปแบบตัวเลขเงิน เช่น "฿ 54,900.00"
     */
    public static function money(float|int|null $value, bool $withSymbol = true): string
    {
        $formatted = number_format((float) $value, 2, '.', ',');

        return $withSymbol ? config('quote.document.currency_symbol', '฿').' '.$formatted : $formatted;
    }

    /**
     * จัดรูปแบบจำนวน (ตัดทศนิยมถ้าเป็นจำนวนเต็ม) — ตรงกับ format #,##0 ในต้นแบบ
     */
    public static function qty(float|int|null $value): string
    {
        $value = (float) $value;

        return floor($value) == $value
            ? number_format($value, 0, '.', ',')
            : number_format($value, 2, '.', ',');
    }

    /**
     * จัดรูปแบบวันที่ dd/mm/yyyy
     */
    public static function dateFormat(?Carbon $date): string
    {
        return $date ? $date->format('d/m/Y') : '-';
    }

    /**
     * วันที่แบบไทย "d mmmm yyyy" พ.ศ. ตาม format [$-107041E] ของต้นแบบ เช่น "2 มีนาคม 2564"
     */
    public static function thaiDate(?Carbon $date): string
    {
        if (! $date) {
            return '-';
        }

        $months = [
            1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม',
        ];

        return $date->day.' '.$months[(int) $date->month].' '.($date->year + 543);
    }
}
