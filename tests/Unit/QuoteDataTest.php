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

    public function test_sales_name_puts_nickname_in_parentheses(): void
    {
        $quote = ['First_Name' => 'มณฑิรา', 'Last_Name' => 'ทองฤทธิ์', 'Owner' => ['name' => 'Owner X']];

        $this->assertSame('มณฑิรา ทองฤทธิ์ (โบว์)', (new QuoteData($quote, null, null, null, ['Nickname' => 'โบว์']))->salesName());
        $this->assertSame('มณฑิรา ทองฤทธิ์', (new QuoteData($quote))->salesName());
        $this->assertSame('Owner X', (new QuoteData(['Owner' => ['name' => 'Owner X']], null, null, null, ['Nickname' => 'โบว์']))->salesName());
    }

    public function test_billing_address_prefers_account_then_falls_back_to_quote(): void
    {
        $account = ['Billing_Street' => '89/1 หมู่ 4 ต.หลักสาม', 'Billing_City' => 'อ.บ้านแพ้ว', 'Billing_State' => 'จ.สมุทรสาคร', 'Billing_Code' => '74120'];

        // ตาม template «Accounts.Billing …» → ใช้ที่อยู่ของ Account ก่อน แม้ Quote จะมีที่อยู่เอง
        $own = new QuoteData(['Billing_Street' => '1 ถนนทดสอบ', 'Billing_City' => 'เขตทดสอบ'], $account);
        $this->assertSame('89/1 หมู่ 4 ต.หลักสาม อ.บ้านแพ้ว', $own->billingLine1());
        $this->assertSame('จ.สมุทรสาคร  74120', $own->billingLine2());
        $this->assertSame('89/1 หมู่ 4 ต.หลักสาม อ.บ้านแพ้ว จ.สมุทรสาคร  74120', $own->billingAddress());

        // Account ไม่มีที่อยู่ → ใช้ Billing ของ Quote
        $noAccount = new QuoteData(['Billing_Street' => '1 ถนนทดสอบ', 'Billing_City' => 'เขตทดสอบ'], ['Billing_Street' => null]);
        $this->assertSame('1 ถนนทดสอบ เขตทดสอบ', $noAccount->billingLine1());
        $this->assertSame('', $noAccount->billingLine2());
    }

    public function test_sales_phone_uses_custom_field_then_owner(): void
    {
        $this->assertSame('0954599817', (new QuoteData(['Sales_Mobile' => '0954599817']))->salesPhone());
        $this->assertSame('081-000-0000', (new QuoteData(['Sales_Mobile' => ''], null, null, ['mobile' => '081-000-0000']))->salesPhone());
    }

    public function test_items_include_chinese_product_name_when_present(): void
    {
        $q = new QuoteData([
            'Quoted_Items' => [
                ['Product_Name' => ['name' => 'ค่าบริการ'], 'Product_Name_CN' => '服务费用', 'Quantity' => 1, 'List_Price' => 100, 'Total' => 100],
                ['Product_Name' => ['name' => 'ค่าเอกสาร'], 'Product_Name_CN' => null, 'Quantity' => 1, 'List_Price' => 50, 'Total' => 50],
            ],
        ]);

        $items = $q->items();
        $this->assertSame('服务费用', $items[0]['name_cn']);
        $this->assertSame('', $items[1]['name_cn']);
    }

    public function test_destination_items_and_totals_map_the_d_fields(): void
    {
        $q = new QuoteData([
            'Total_Amount_D' => 400,
            'Total_Discount_D' => null,
            'Total_Tax_D' => 0,
            'Grand_Total_D' => 400,
            'QT_Destination' => [
                ['LinkingModule1_Serial_Number' => '1', 'Product_Name_D' => ['name' => 'ค่าเซ็นต์สัญญา'], 'Quantity_D' => 1, 'List_Price_D' => 300, 'Amount_D' => 300, 'Total_D' => 300, 'Description_D' => 'x'],
                ['LinkingModule1_Serial_Number' => '2', 'Product_Name_D' => ['name' => 'ค่าบัตร OWIC'], 'Quantity_D' => 1, 'List_Price_D' => 100, 'Amount_D' => 100, 'Total_D' => 100],
            ],
        ]);

        $items = $q->destinationItems();
        $this->assertCount(2, $items);
        $this->assertSame([1, 2], array_column($items, 'no'));
        $this->assertSame('ค่าเซ็นต์สัญญา', $items[0]['name']);
        $this->assertSame(300.0, $items[0]['total']);
        $this->assertSame(['amount' => 400.0, 'discount' => 0.0, 'tax' => 0.0, 'grand' => 400.0], $q->destinationTotals());

        $this->assertSame([], (new QuoteData([]))->destinationItems());
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

    public function test_table_subtotal_is_thailand_only_while_totals_box_is_combined(): void
    {
        // QT_* ใน CRM = ยอดรวมทั้ง 2 ตาราง (Thailand 9,200 + Destination 6,300)
        // แถว "ยอดรวม" ท้ายตาราง Thailand = Sub_Total (9,200) ส่วนกล่องสรุปยอด = QT_* (15,500)
        $q = new QuoteData([
            'QT_Total_Amount' => 15500,
            'QT_Total_Tax' => 0,
            'QT_Grand_Total' => 15500,
            'Sub_Total' => 9200,
            'Tax' => 0,
            'Grand_Total' => 9200,
            'Total_Amount_D' => 6300,
            'Grand_Total_D' => 6300,
            'Quoted_Items' => [
                ['Sequence_Number' => '1', 'Product_Name' => ['name' => 'A'], 'Quantity' => 1, 'List_Price' => 1900, 'Total' => 1900, 'Net_Total' => 1900],
                ['Sequence_Number' => '2', 'Product_Name' => ['name' => 'B'], 'Quantity' => 1, 'List_Price' => 7300, 'Total' => 7300, 'Net_Total' => 7300],
            ],
        ]);

        $this->assertSame(9200.0, $q->tableSubtotal());
        $this->assertSame(15500.0, $q->totalAmount());
        $this->assertSame(0.0, $q->totalTax());
        $this->assertSame(15500.0, $q->grandTotal());
        $this->assertSame(6300.0, $q->destinationTotals()['amount']);
        $this->assertSame([1, 2], array_column($q->items(), 'no'));

        // ไม่มี field QT_* → กล่องสรุปยอดถอยไปใช้ field มาตรฐาน, ไม่มี Sub_Total → ยอดรวมท้ายตารางรวมจากแถว
        $std = new QuoteData(['Sub_Total' => 500, 'Tax' => 35, 'Grand_Total' => 535]);
        $this->assertSame(500.0, $std->totalAmount());
        $this->assertSame(535.0, $std->grandTotal());
        $rows = new QuoteData(['Quoted_Items' => [['Product_Name' => ['name' => 'A'], 'Quantity' => 2, 'List_Price' => 50, 'Total' => 100]]]);
        $this->assertSame(100.0, $rows->tableSubtotal());

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
