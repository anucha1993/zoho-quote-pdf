<?php

namespace Tests\Feature;

use App\Services\QuotePdfService;
use App\Support\QuoteLink;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuotePdfTest extends TestCase
{
    protected const QUOTE_ID = '6123456000000123456';

    protected string $storageDir;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'quote.link_secret' => 'test-secret',
            'quote.signature_length' => 64,
            'quote.allow_unsigned' => true,
            'quote.base_url' => 'http://localhost',
            'quote.storage_dir' => 'quotes-test',
            'quote.company.logo' => null,
            'zoho.client_id' => 'cid',
            'zoho.client_secret' => 'csecret',
            'zoho.refresh_token' => 'rtoken',
        ]);

        $this->storageDir = storage_path('app/quotes-test');
        File::deleteDirectory($this->storageDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageDir);

        parent::tearDown();
    }

    /* ------------------------------------------------------------------ */

    public function test_invalid_signature_returns_404_without_calling_zoho(): void
    {
        Http::fake();

        $this->get('/quote/'.self::QUOTE_ID.'.'.str_repeat('0', 64))->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_malformed_signature_returns_404(): void
    {
        Http::fake();

        $this->get('/quote/'.self::QUOTE_ID.'.not-hex')->assertNotFound();
        $this->get('/quote/abc.'.str_repeat('a', 64))->assertNotFound();
    }

    public function test_valid_signature_renders_pdf_inline(): void
    {
        $this->fakeZoho();

        $response = $this->get($this->url());

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('P-PJ 2026-0688.pdf', $response->headers->get('Content-Disposition'));

        $pdf = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertStringStartsWith('%PDF-', $pdf);

        // token ถูกขอ 1 ครั้ง และดึง Quote 1 ครั้ง
        Http::assertSent(fn ($req) => str_contains($req->url(), '/oauth/v2/token'));
        Http::assertSent(fn ($req) => str_contains($req->url(), '/crm/v8/Quotes/'.self::QUOTE_ID));
    }

    public function test_download_flag_sets_attachment_with_quote_number_filename(): void
    {
        $this->fakeZoho();

        $response = $this->get($this->url().'?download=1');

        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertStringContainsString('filename="P-PJ 2026-0688.pdf"', $disposition);
    }

    public function test_missing_quote_returns_404(): void
    {
        $this->fakeZoho(quoteStatus: 204);

        $this->get($this->url())->assertNotFound();
    }

    public function test_cache_hit_serves_existing_file_without_rerendering(): void
    {
        $this->fakeZoho();

        $first = $this->get($this->url());
        $first->assertOk();

        $path = $first->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);

        // จำลองว่าไฟล์ cache มีอยู่แล้ว: เขียนเนื้อหาพิเศษลงไป ถ้า render ใหม่เนื้อหานี้จะหายไป
        file_put_contents($path, '%PDF-CACHED');

        $second = $this->get($this->url());
        $second->assertOk();

        $this->assertSame('%PDF-CACHED', file_get_contents($second->baseResponse->getFile()->getPathname()));
    }

    public function test_modified_quote_invalidates_cache(): void
    {
        // request แรกได้ Quote เวอร์ชันเดิม, request ที่สองได้เวอร์ชันที่ Modified_Time เปลี่ยน
        Http::fake([
            'accounts.zoho.com/*' => Http::response(['access_token' => 'tok'], 200),
            'www.zohoapis.com/crm/v8/Quotes/'.self::QUOTE_ID => Http::sequence()
                ->push(['data' => [$this->sampleQuote('2026-10-21T10:00:00+07:00')]], 200)
                ->push(['data' => [$this->sampleQuote('2026-10-22T15:00:00+07:00')]], 200),
            'www.zohoapis.com/*' => Http::response('', 204),
        ]);

        $first = $this->get($this->url())->assertOk();
        $firstPath = $first->baseResponse->getFile()->getPathname();

        $second = $this->get($this->url())->assertOk();
        $secondPath = $second->baseResponse->getFile()->getPathname();

        $this->assertNotSame($firstPath, $secondPath, 'Modified quote must produce a new cache file');
        $this->assertFileExists($firstPath);
        $this->assertFileExists($secondPath);
    }

    public function test_template_or_config_change_invalidates_cache(): void
    {
        $this->fakeZoho();
        $first = $this->get($this->url())->assertOk();
        $firstPath = $first->baseResponse->getFile()->getPathname();

        // เปลี่ยน config ของเอกสาร (เช่น ชื่อผู้อนุมัติ) โดย Quote ไม่ได้ถูกแก้ → ต้องได้ไฟล์ใหม่
        config(['quote.approver.name' => 'ผู้อนุมัติคนใหม่']);
        $second = $this->get($this->url())->assertOk();
        $secondPath = $second->baseResponse->getFile()->getPathname();

        $this->assertNotSame($firstPath, $secondPath);
    }

    public function test_truncated_rich_text_is_completed_via_fetch_full_data(): void
    {
        $quote = $this->sampleQuote();
        $quote['field7'] = '<b>ตัดแล้ว</b>';
        $quote['$has_more'] = ['field7' => true, 'field6' => false, 'Quoted_Items' => false];

        Http::fake([
            'accounts.zoho.com/*' => Http::response(['access_token' => 'tok'], 200),
            'www.zohoapis.com/crm/v8/Quotes/'.self::QUOTE_ID.'/actions/fetch_full_data*' => Http::response([
                'data' => [['id' => self::QUOTE_ID, 'field7' => '<b>ตัดแล้ว</b><ol><li>ข้อเต็ม 1</li><li>ข้อเต็ม 2</li></ol>']],
            ], 200),
            'www.zohoapis.com/crm/v8/Quotes/'.self::QUOTE_ID => Http::response(['data' => [$quote]], 200),
            'www.zohoapis.com/*' => Http::response('', 204),
        ]);

        $data = app(QuotePdfService::class)->load(self::QUOTE_ID);

        $this->assertStringContainsString('ข้อเต็ม 2', $data->paymentTerms());

        Http::assertSent(fn ($req) => str_contains($req->url(), '/actions/fetch_full_data')
            && str_contains($req->url(), 'fields=field7'));
    }

    public function test_zoho_outage_returns_502(): void
    {
        Http::fake([
            'accounts.zoho.com/*' => Http::response(['access_token' => 'tok'], 200),
            'www.zohoapis.com/*' => Http::response('oops', 500),
        ]);

        $this->get($this->url())->assertStatus(502);
    }

    public function test_expired_token_is_refreshed_and_request_retried(): void
    {
        Http::fake([
            'accounts.zoho.com/*' => Http::response(['access_token' => 'fresh-token'], 200),
            'www.zohoapis.com/crm/v8/Quotes/*' => Http::sequence()
                ->push(['code' => 'INVALID_TOKEN'], 401)
                ->push(['data' => [$this->sampleQuote()]], 200),
            'www.zohoapis.com/*' => Http::response('', 204),
        ]);

        $this->get($this->url())->assertOk();

        // token, quote(401), token(refresh), quote(200), account, contact, owner, staff(nickname)
        Http::assertSentCount(8);
        $this->assertSame(2, collect(Http::recorded())->filter(
            fn ($pair) => str_contains($pair[0]->url(), '/oauth/v2/token')
        )->count(), 'token must be requested twice (initial + refresh after 401)');
    }

    public function test_attach_endpoint_uploads_pdf_to_crm(): void
    {
        $this->fakeZoho();

        $response = $this->post($this->url().'/attach');

        $response->assertOk()->assertJson([
            'ok' => true,
            'quote_id' => self::QUOTE_ID,
            'filename' => 'P-PJ 2026-0688.pdf',
            'attachment_id' => '6123456000000999999',
        ]);

        Http::assertSent(function ($req) {
            return str_contains($req->url(), '/Quotes/'.self::QUOTE_ID.'/Attachments')
                && $req->hasFile('file', null, 'P-PJ 2026-0688.pdf');
        });
    }

    /* ------------------------------------------------------------------ */
    /*  helpers */
    /* ------------------------------------------------------------------ */

    /* ------------------------------------------------------------------ */
    /*  route แบบสั้น /{id} (QUOTE_ALLOW_UNSIGNED) */
    /* ------------------------------------------------------------------ */

    public function test_short_url_serves_pdf_when_unsigned_routes_enabled(): void
    {
        $this->fakeZoho();

        $response = $this->get('/'.self::QUOTE_ID);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('P-PJ 2026-0688.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_form_url_serves_pdf_for_named_form(): void
    {
        $this->fakeZoho();

        $this->get('/FA-SA-TH/'.self::QUOTE_ID)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Quote-Form', 'FA-SA-TH');

        // slug ไม่สนตัวพิมพ์
        $this->get('/fa-sa-th/'.self::QUOTE_ID)->assertOk()->assertHeader('X-Quote-Form', 'FA-SA-TH');
    }

    public function test_unknown_form_returns_404_without_calling_zoho(): void
    {
        Http::fake();

        $this->get('/FA-SA-XX/'.self::QUOTE_ID)->assertNotFound();
        $this->get('/quote/FA-SA-XX/'.self::QUOTE_ID.'.'.QuoteLink::sign(self::QUOTE_ID))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_signed_form_url_serves_pdf(): void
    {
        $this->fakeZoho();

        $this->get('/quote/FA-SA-TH/'.self::QUOTE_ID.'.'.QuoteLink::sign(self::QUOTE_ID))
            ->assertOk()
            ->assertHeader('X-Quote-Form', 'FA-SA-TH');
    }

    public function test_auto_form_is_resolved_from_quote_iso_field(): void
    {
        config(['quote.auto_form' => ['field' => 'ISO', 'map' => ['FA-SA-02-1*' => 'FA-SA-TH', 'XX-*' => 'NOPE']]]);
        $this->fakeZoho(); // sampleQuote มี ISO = FA-SA-02-103

        $this->get('/auto/'.self::QUOTE_ID)->assertOk()->assertHeader('X-Quote-Form', 'FA-SA-TH');
    }

    public function test_auto_form_falls_back_to_default_when_no_mapping(): void
    {
        config(['quote.auto_form' => ['field' => 'ISO', 'map' => ['ZZ-*' => 'FA-SA-TH']], 'quote.default_form' => 'FA-SA-TH']);
        $this->fakeZoho();

        $this->get('/AUTO/'.self::QUOTE_ID)->assertOk()->assertHeader('X-Quote-Form', 'FA-SA-TH');
    }

    public function test_form_picker_page_lists_available_forms(): void
    {
        $this->fakeZoho();

        $this->get('/'.self::QUOTE_ID.'/forms')
            ->assertOk()
            ->assertSee('FA-SA-TH')
            ->assertSee('P-PJ 2026-0688')
            ->assertSee('/FA-SA-TH/'.self::QUOTE_ID);
    }

    public function test_long_quote_spans_pages_and_closing_block_is_on_last_page(): void
    {
        $quote = $this->sampleQuote();
        $quote['Quoted_Items'] = array_map(fn ($i) => [
            'Sequence_Number' => (string) $i,
            'Product_Name' => ['name' => "รายการที่ {$i}", 'Product_Code' => "P{$i}"],
            'Description' => 'รายละเอียดบรรทัดที่สอง',
            'Quantity' => 1, 'List_Price' => 100, 'Total' => 100, 'Net_Total' => 100,
        ], range(1, 25));

        Http::fake([
            'accounts.zoho.com/*' => Http::response(['access_token' => 'tok'], 200),
            'www.zohoapis.com/crm/v8/Quotes/'.self::QUOTE_ID => Http::response(['data' => [$quote]], 200),
            'www.zohoapis.com/*' => Http::response('', 204),
        ]);

        $response = $this->get($this->url())->assertOk();
        $pdf = file_get_contents($response->baseResponse->getFile()->getPathname());

        $pages = preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
        $this->assertGreaterThanOrEqual(2, $pages, 'รายการ 25 แถวต้องล้นไปมากกว่า 1 หน้า');
    }

    public function test_short_url_rejects_non_numeric_or_short_ids(): void
    {
        Http::fake();

        $this->get('/abc')->assertNotFound();
        $this->get('/123')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_short_attach_requires_key_header(): void
    {
        $this->fakeZoho();

        $this->post('/'.self::QUOTE_ID.'/attach')->assertNotFound();
        $this->post('/'.self::QUOTE_ID.'/attach', [], ['X-Quote-Key' => 'wrong'])->assertNotFound();

        $this->post('/'.self::QUOTE_ID.'/attach', [], ['X-Quote-Key' => 'test-secret'])
            ->assertOk()
            ->assertJson(['ok' => true, 'attachment_id' => '6123456000000999999']);
    }

    protected function url(): string
    {
        return '/quote/'.self::QUOTE_ID.'.'.QuoteLink::sign(self::QUOTE_ID);
    }

    protected function fakeZoho(int $quoteStatus = 200, string $modifiedTime = '2026-10-21T10:00:00+07:00'): void
    {
        $quote = $this->sampleQuote($modifiedTime);

        Http::fake([
            'accounts.zoho.com/oauth/v2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            'www.zohoapis.com/crm/v8/Quotes/'.self::QUOTE_ID.'/Attachments' => Http::response([
                'data' => [[
                    'code' => 'SUCCESS',
                    'status' => 'success',
                    'message' => 'attachment uploaded successfully',
                    'details' => ['id' => '6123456000000999999'],
                ]],
            ], 200),
            'www.zohoapis.com/crm/v8/Quotes/'.self::QUOTE_ID => $quoteStatus === 200
                ? Http::response(['data' => [$quote]], 200)
                : Http::response('', $quoteStatus),
            'www.zohoapis.com/crm/v8/Accounts/*' => Http::response(['data' => [[
                'id' => '6123456000000222222',
                'Account_Name' => 'บริษัท ลูกค้าตัวอย่าง จำกัด',
                'Phone' => '02-123-4567',
            ]]], 200),
            'www.zohoapis.com/crm/v8/Contacts/*' => Http::response(['data' => [[
                'id' => '6123456000000333333',
                'Full_Name' => 'สมชาย ใจดี',
                'Email' => 'somchai@example.com',
                'Phone' => '081-234-5678',
            ]]], 200),
            'www.zohoapis.com/crm/v8/Staffs/*' => Http::response(['data' => [[
                'id' => '6274719000134262169',
                'Name' => '6905040',
                'Nickname' => 'นุช',
            ]]], 200),
            'www.zohoapis.com/crm/v8/users/*' => Http::response(['users' => [[
                'id' => '6123456000000111111',
                'full_name' => 'พนักงานขาย ทดสอบ',
                'email' => 'sales@example.com',
                'mobile' => '089-999-9999',
            ]]], 200),
        ]);
    }

    protected function sampleQuote(string $modifiedTime = '2026-10-21T10:00:00+07:00'): array
    {
        return [
            'id' => self::QUOTE_ID,
            // org นี้: Quotation_Number = เลขที่เอกสาร (${Quotes.Quote Number}), Quote_Number = ตัวเลขยาว
            'Quote_Number' => '6274719000183778842',
            'Quotation_Number' => 'P-PJ 2026-0688',
            'Subject' => 'P-PJ2026-0194',
            'field2' => '2026-10-21',
            'field6' => 'ระบบติดตามเอกสารด้วย QR Code',
            'field7' => "1. ราคานี้รวมภาษีมูลค่าเพิ่ม 7%\n2. ชำระเงิน 50% เมื่อสั่งซื้อ และ 50% เมื่อส่งมอบ",
            'First_Name' => 'ปิยนุช',
            'Last_Name' => 'จันทร์วงค์',
            'Sales_Code' => ['name' => '6905040', 'id' => '6274719000134262169'],
            'ISO' => 'FA-SA-02-103',
            'Quote_Stage' => 'Draft',
            'Valid_Till' => '2026-11-21',
            'Created_Time' => '2026-10-21T09:00:00+07:00',
            'Modified_Time' => $modifiedTime,
            'Owner' => ['id' => '6123456000000111111', 'name' => 'พนักงานขาย ทดสอบ', 'email' => 'sales@example.com'],
            'Account_Name' => ['id' => '6123456000000222222', 'name' => 'บริษัท ลูกค้าตัวอย่าง จำกัด'],
            'Contact_Name' => ['id' => '6123456000000333333', 'name' => 'สมชาย ใจดี'],
            'Billing_Street' => '123 ถนนสุขุมวิท แขวงคลองเตย',
            'Billing_City' => 'เขตคลองเตย',
            'Billing_State' => 'กรุงเทพมหานคร',
            'Billing_Code' => '10110',
            'Billing_Country' => 'ประเทศไทย',
            'Quoted_Items' => [
                [
                    'Product_Name' => ['id' => '1', 'name' => 'Tracking QR Platform (Enterprise)', 'Product_Code' => 'TRK-ENT'],
                    'Description' => "ระบบติดตามสถานะเอกสารแบบเรียลไทม์\nรองรับผู้ใช้งานไม่จำกัด",
                    'Quantity' => 1,
                    'List_Price' => 49000,
                    'Discount' => 0,
                    'Tax' => 3430,
                    'Total' => 49000,
                    'Net_Total' => 52430,
                    'Recurring_Amount' => 12000,
                    'Unit' => 'Credit/year',
                ],
                [
                    'Product_Name' => ['id' => '2', 'name' => 'Implementation Service', 'Product_Code' => 'SVC-IMP'],
                    'Description' => 'ติดตั้งและฝึกอบรม 1 ครั้ง',
                    'Quantity' => 2,
                    'List_Price' => 2500,
                    'Discount' => 0,
                    'Tax' => 350,
                    'Total' => 5000,
                    'Net_Total' => 5350,
                ],
            ],
            'Sub_Total' => 54000,
            'Discount' => 0,
            'Tax' => 3780,
            'Adjustment' => 0,
            'Grand_Total' => 57780,
            'Terms_and_Conditions' => "1. ราคานี้รวมภาษีมูลค่าเพิ่ม 7%\n2. ชำระเงิน 50% เมื่อสั่งซื้อ และ 50% เมื่อส่งมอบ",
            'Description' => 'กรุณาตรวจสอบรายละเอียดก่อนยืนยันการสั่งซื้อ',
        ];
    }
}
