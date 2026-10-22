<?php

namespace Tests\Unit;

use App\Support\QuoteData;
use Tests\TestCase;

class QuoteDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['quote.approved_stages' => ['อนุมัติ', 'Confirmed']]);
    }

    public function test_is_approved_matches_configured_stages_case_insensitively(): void
    {
        $this->assertTrue((new QuoteData(['Quote_Stage' => 'อนุมัติ']))->isApproved());
        $this->assertTrue((new QuoteData(['Quote_Stage' => 'confirmed']))->isApproved());
        $this->assertFalse((new QuoteData(['Quote_Stage' => 'ขออนุมัติ']))->isApproved());
        $this->assertFalse((new QuoteData(['Quote_Stage' => 'ฉบับร่าง']))->isApproved());
        $this->assertFalse((new QuoteData([]))->isApproved());
    }

    public function test_merge_tokens_in_terms_are_replaced_with_field_values(): void
    {
        $q = new QuoteData([
            'field1' => 'ชำระเต็มจำนวน ณ วันที่ตกลงดำเนินการ',
            'Account_Name' => ['id' => '1', 'name' => 'บริษัท ทดสอบ จำกัด'],
            'field7' => '<b>«Quotes.เงื่อนไขการชำระเงิน»</b><br>ลูกค้า: ${Quotes.Account Name} / «Quotes.ไม่มีฟิลด์นี้»',
        ]);

        $this->assertSame(
            '<b>ชำระเต็มจำนวน ณ วันที่ตกลงดำเนินการ</b><br>ลูกค้า: บริษัท ทดสอบ จำกัด / ไม่มีฟิลด์นี้',
            $q->paymentTerms()
        );
    }

    public function test_merge_token_falls_back_to_label_when_field_is_empty(): void
    {
        $q = new QuoteData([
            'field1' => '-None-',
            'field7' => '«Quotes.เงื่อนไขการชำระเงิน»<br>«Quotes.ชื่อจริง»',
        ]);

        $this->assertSame('เงื่อนไขการชำระเงิน<br>ชื่อจริง', $q->paymentTerms());
    }

    public function test_date_format_is_dd_mm_yyyy_gregorian(): void
    {
        $q = new QuoteData(['field2' => '2026-10-22', 'Quote_Stage' => 'อนุมัติ']);

        $this->assertSame('22/10/2026', QuoteData::dateFormat($q->quoteDate()));
    }

    public function test_document_number_prefers_quotation_number(): void
    {
        $q = new QuoteData([
            'id' => '1',
            'Quote_Number' => '6274719000184443050',
            'Quotation_Number' => 'IO2026-021617',
            'Subject' => 'IO10-0001',
        ]);

        $this->assertSame('IO2026-021617', $q->number());
        $this->assertSame('IO2026-021617.pdf', $q->filename());
    }

    public function test_totals_box_uses_qt_fields_with_fallbacks(): void
    {
        $q = new QuoteData([
            'QT_Total_Amount' => 19200,
            'QT_Total_Discount' => 0,
            'QT_Total_Tax' => 1344,
            'QT_Grand_Total' => 20544,
            'Grand_Total' => 99999,
            'Quoted_Items' => [
                ['Sequence_Number' => '1', 'Product_Name' => ['name' => 'A'], 'Quantity' => 3, 'List_Price' => 1900, 'Total' => 5700, 'Net_Total' => 5700],
                ['Sequence_Number' => '2', 'Product_Name' => ['name' => 'B'], 'Quantity' => 3, 'List_Price' => 4500, 'Total' => 13500, 'Net_Total' => 13500],
            ],
        ]);

        $this->assertSame(19200.0, $q->totalAmount());
        $this->assertSame(1344.0, $q->totalTax());
        $this->assertSame(20544.0, $q->grandTotal());
        $this->assertSame([1, 2], array_column($q->items(), 'no'));

        // ไม่มี field QT_* → ถอยไปใช้ field มาตรฐาน / คำนวณจากรายการ
        $fallback = new QuoteData([
            'Sub_Total' => 100, 'Discount' => 10, 'Tax' => 6.3,
            'Quoted_Items' => [['Product_Name' => ['name' => 'A'], 'Quantity' => 1, 'List_Price' => 100, 'Total' => 100]],
        ]);
        $this->assertSame(100.0, $fallback->totalAmount());
        $this->assertSame(10.0, $fallback->totalDiscount());
        $this->assertSame(96.3, $fallback->grandTotal());
    }
}
