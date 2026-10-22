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

    public function test_grand_total_is_sum_of_item_amounts_before_vat(): void
    {
        $q = new QuoteData([
            'Grand_Total' => 20544,
            'Sub_Total' => 19200,
            'Quoted_Items' => [
                ['Sequence_Number' => '1', 'Product_Name' => ['name' => 'A'], 'Quantity' => 3, 'List_Price' => 1900, 'Total' => 5700, 'Net_Total' => 5700],
                ['Sequence_Number' => '2', 'Product_Name' => ['name' => 'B'], 'Quantity' => 3, 'List_Price' => 4500, 'Total' => 13500, 'Net_Total' => 13500],
            ],
        ]);

        $this->assertSame(19200.0, $q->grandTotal());
        $this->assertSame([1, 2], array_column($q->items(), 'no'));
    }
}
