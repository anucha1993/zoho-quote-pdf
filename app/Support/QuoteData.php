<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * แปลง record จาก Zoho CRM (Quote + related) ให้เป็นโครงสร้างที่ Blade view ใช้งานง่าย
 *
 * ทุก key ของ field อ่านจาก config('zoho.fields') เพื่อให้แก้ API name ได้ที่เดียว
 * ชื่อ method อิงตาม merge field ในแบบฟอร์ม Excel ต้นแบบ (sheet "คนเมียวดี (เขียว-แดง)")
 */
class QuoteData
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
     * ใน org นี้ Subject เก็บเลขที่แบบ "PRE-MOU2026-0194" จึงอ่านตาม config zoho.fields.document_number
     * แล้วถอยไปยัง number_fallback (Quotation_Number, Quote_Number) และ record id ตามลำดับ
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
     * ยอดรวมทั้งหมด — ต้นแบบคือ =SUM(ช่อง Amount) จึงรวมจากรายการ (= Sub_Total ก่อนภาษี)
     * ไม่ใช้ Grand_Total ของ Zoho ซึ่งรวม VAT แล้ว
     */
    public function grandTotal(): float
    {
        $items = $this->items();

        if ($items !== []) {
            return round(array_sum(array_column($items, 'total')), 2);
        }

        return $this->subTotal() ?: (float) ($this->quote[$this->f['grand_total']] ?? 0);
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
        return trim((string) ($this->custom('payment_terms') ?: $this->terms()));
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
