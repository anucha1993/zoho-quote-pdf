<?php

namespace App\Services;

use App\Exceptions\QuoteNotFoundException;
use App\Exceptions\ZohoApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service สำหรับติดต่อ Zoho CRM API v8 (US data center)
 *
 * - ขอ access token จาก refresh token และ cache ไว้ ~55 นาที
 * - refresh token ใหม่อัตโนมัติเมื่อได้ HTTP 401 (INVALID_TOKEN)
 * - log ทุก error ลง storage/logs/zoho.log (channel "zoho")
 */
class ZohoCrmService
{
    public function __construct(
        protected array $config,
    ) {}

    /* =====================================================================
     |  OAuth
     | ===================================================================*/

    /**
     * คืน access token ที่ใช้งานได้ (จาก cache หรือขอใหม่)
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        $cacheKey = $this->config['token_cache_key'];

        if (! $forceRefresh) {
            $token = Cache::get($cacheKey);
            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        $token = $this->requestAccessToken();

        Cache::put($cacheKey, $token, now()->addSeconds($this->config['token_cache_ttl']));

        return $token;
    }

    /**
     * ขอ access token ใหม่ด้วย refresh token
     */
    protected function requestAccessToken(): string
    {
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (empty($this->config[$key])) {
                throw new ZohoApiException("Zoho OAuth config [{$key}] is missing. Check your .env");
            }
        }

        try {
            $response = Http::asForm()
                ->timeout($this->config['timeout'])
                ->post(rtrim($this->config['accounts_url'], '/').'/oauth/v2/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'refresh_token' => $this->config['refresh_token'],
                ]);
        } catch (ConnectionException $e) {
            $this->log('error', 'Token request connection error', ['message' => $e->getMessage()]);
            throw new ZohoApiException('Cannot connect to Zoho accounts server', 0, $e);
        }

        $data = $response->json();

        if (! $response->successful() || empty($data['access_token'])) {
            $this->log('error', 'Token request failed', [
                'status' => $response->status(),
                'body' => $this->safeBody($response),
            ]);
            throw new ZohoApiException('Zoho token request failed: '.($data['error'] ?? 'unknown error'));
        }

        $this->log('info', 'Access token refreshed');

        return $data['access_token'];
    }

    /* =====================================================================
     |  Records
     | ===================================================================*/

    /**
     * ดึง Quote 1 รายการ (subform Quoted_Items ถูกส่งมาในตัว record อยู่แล้ว)
     *
     * @return array<string, mixed>
     *
     * @throws QuoteNotFoundException|ZohoApiException
     */
    public function getQuote(string $id): array
    {
        $record = $this->getRecord('Quotes', $id);

        if ($record === null) {
            throw new QuoteNotFoundException("Quote {$id} not found");
        }

        return $this->fillTruncatedRichText('Quotes', $record);
    }

    /**
     * Zoho ตัด rich text field ให้เหลือ ~500 ตัวอักษรใน Get Record และส่ง $has_more.{field} = true
     * → ดึงเนื้อหาเต็มผ่าน Rich Text Fields API (/actions/fetch_full_data) แล้วแทนค่าลงใน record
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function fillTruncatedRichText(string $module, array $record): array
    {
        $hasMore = $record['$has_more'] ?? [];

        if (! is_array($hasMore) || empty($record['id'])) {
            return $record;
        }

        // เฉพาะ field ที่ถูกตัดและเป็นข้อความ (subform/lookup ก็มี flag นี้ แต่ไม่เกี่ยว)
        $truncated = array_keys(array_filter(
            $hasMore,
            fn ($flag, $field) => $flag === true && is_string($record[$field] ?? null),
            ARRAY_FILTER_USE_BOTH
        ));

        if ($truncated === []) {
            return $record;
        }

        $full = $this->tryGet(fn () => $this->getRichTextFields($module, (string) $record['id'], $truncated)) ?? [];

        foreach ($truncated as $field) {
            if (isset($full[$field]) && is_string($full[$field])) {
                $record[$field] = $full[$field];
            }
        }

        return $record;
    }

    /**
     * Rich Text Fields API: GET /{module}/{id}/actions/fetch_full_data?fields=a,b
     *
     * @param  string[]  $fields
     * @return array<string, mixed>|null
     */
    public function getRichTextFields(string $module, string $id, array $fields): ?array
    {
        $response = $this->request(
            'get',
            "/crm/{$this->version()}/{$module}/{$id}/actions/fetch_full_data",
            $fields !== [] ? ['fields' => implode(',', $fields)] : []
        );

        if (in_array($response->status(), [204, 404], true)) {
            return null;
        }

        $this->assertSuccessful($response, "GET {$module}/{$id}/actions/fetch_full_data");

        $data = $response->json('data.0');

        return is_array($data) ? $data : null;
    }

    /**
     * ดึง record ตาม module/id — คืน null เมื่อไม่พบ (Zoho ตอบ 204 หรือ 404)
     *
     * @return array<string, mixed>|null
     */
    public function getRecord(string $module, string $id): ?array
    {
        $response = $this->request('get', "/crm/{$this->version()}/{$module}/{$id}");

        if (in_array($response->status(), [204, 404], true)) {
            return null;
        }

        $this->assertSuccessful($response, "GET {$module}/{$id}");

        $data = $response->json('data.0');

        return is_array($data) ? $data : null;
    }

    /**
     * ดึงข้อมูลผู้ใช้ (Owner) เพื่อนำชื่อ/อีเมล/เบอร์โทรพนักงานขายมาแสดง
     *
     * @return array<string, mixed>|null
     */
    public function getUser(string $id): ?array
    {
        $response = $this->request('get', "/crm/{$this->version()}/users/{$id}");

        if (in_array($response->status(), [204, 404], true)) {
            return null;
        }

        $this->assertSuccessful($response, "GET users/{$id}");

        $data = $response->json('users.0');

        return is_array($data) ? $data : null;
    }

    /**
     * ดึง Quote พร้อมข้อมูลที่เกี่ยวข้อง (Account / Contact / Owner) ตาม config
     *
     * @return array{quote: array, account: ?array, contact: ?array, owner: ?array}
     */
    public function getQuoteWithRelations(string $id): array
    {
        $quote = $this->getQuote($id);
        $fields = $this->config['fields'];
        $fetch = $this->config['fetch_related'];

        $account = $contact = $owner = null;

        // ดึงเพิ่มแบบ best-effort: ถ้า related ล้มเหลวให้ใช้ข้อมูลเท่าที่มีใน Quote
        if (($fetch['account'] ?? false) && ! empty($quote[$fields['account']]['id'])) {
            $account = $this->tryGet(fn () => $this->getRecord('Accounts', $quote[$fields['account']]['id']));
        }

        if (($fetch['contact'] ?? false) && ! empty($quote[$fields['contact']]['id'])) {
            $contact = $this->tryGet(fn () => $this->getRecord('Contacts', $quote[$fields['contact']]['id']));
        }

        if (($fetch['owner'] ?? false) && ! empty($quote[$fields['owner']]['id'])) {
            $owner = $this->tryGet(fn () => $this->getUser($quote[$fields['owner']]['id']));
        }

        // lookup "รหัสพนักงาน" → module พนักงาน (ใช้ดึงชื่อเล่นมาต่อท้ายชื่อผู้เสนอราคา)
        $employee = null;
        $lookupField = $fields['custom']['employee_lookup'] ?? null;
        $employeeModule = $fields['custom']['employee_module'] ?? null;

        if (($fetch['employee'] ?? false) && $lookupField && $employeeModule && ! empty($quote[$lookupField]['id'])) {
            $employee = $this->tryGet(fn () => $this->getRecord($employeeModule, $quote[$lookupField]['id']));
        }

        return compact('quote', 'account', 'contact', 'owner', 'employee');
    }

    /**
     * ดาวน์โหลดไฟล์จาก Files API ด้วย File_Id__s (เช่น รูปใน image upload field "Image Sign" ของ Staffs)
     * ต้องมี scope ZohoCRM.Files.READ — คืน null ถ้าดาวน์โหลดไม่ได้ (log ไว้ ไม่ทำให้เอกสารล้ม)
     *
     * @return array{body: string, type: string}|null
     */
    public function downloadFile(string $fileId): ?array
    {
        if ($fileId === '') {
            return null;
        }

        try {
            $response = $this->request('get', "/crm/{$this->version()}/files", ['id' => $fileId]);
        } catch (ZohoApiException $e) {
            return null;
        }

        $type = (string) $response->header('Content-Type');

        if (! $response->successful() || $response->body() === '' || str_starts_with($type, 'application/json')) {
            $this->log('warning', 'File download failed', [
                'file_id' => $fileId,
                'status' => $response->status(),
                'body' => $this->safeBody($response),
            ]);

            return null;
        }

        return ['body' => $response->body(), 'type' => $type];
    }

    /**
     * แนบไฟล์ PDF กลับเข้า Quote เป็น Attachment
     *
     * @return array<string, mixed> ข้อมูล attachment ที่สร้าง (id ฯลฯ)
     */
    public function uploadAttachment(string $quoteId, string $pdfBinary, string $filename): array
    {
        $response = $this->request(
            'post',
            "/crm/{$this->version()}/Quotes/{$quoteId}/Attachments",
            [],
            fn (PendingRequest $req) => $req->attach('file', $pdfBinary, $filename, ['Content-Type' => 'application/pdf'])
        );

        $this->assertSuccessful($response, "POST Quotes/{$quoteId}/Attachments");

        $result = $response->json('data.0');

        if (($result['status'] ?? '') !== 'success') {
            $this->log('error', 'Attachment upload rejected', ['quote' => $quoteId, 'body' => $this->safeBody($response)]);
            throw new ZohoApiException('Zoho rejected the attachment: '.($result['message'] ?? 'unknown'));
        }

        $this->log('info', 'Attachment uploaded', [
            'quote' => $quoteId,
            'file' => $filename,
            'attachment_id' => $result['details']['id'] ?? null,
        ]);

        return $result;
    }

    /* =====================================================================
     |  HTTP core
     | ===================================================================*/

    /**
     * ส่ง request ไปยัง Zoho พร้อม Authorization header
     * ถ้าได้ 401 จะขอ token ใหม่แล้วลองซ้ำ 1 ครั้ง
     *
     * @param  callable(PendingRequest): PendingRequest|null  $configure  ปรับแต่ง request เพิ่ม (เช่น attach file)
     */
    protected function request(string $method, string $path, array $query = [], ?callable $configure = null): Response
    {
        $url = rtrim($this->config['api_url'], '/').$path;

        $send = function (string $token) use ($method, $url, $query, $configure): Response {
            $req = Http::withToken($token, 'Zoho-oauthtoken')
                ->timeout($this->config['timeout'])
                ->acceptJson();

            if ($configure) {
                $req = $configure($req);
            }

            return $method === 'get'
                ? $req->get($url, $query)
                : $req->{$method}($url, $query);
        };

        try {
            $response = $send($this->getAccessToken());

            // token หมดอายุ/ถูกเพิกถอน → ขอใหม่แล้วลองอีกครั้ง
            if ($response->status() === 401) {
                $this->log('warning', 'Received 401, refreshing token and retrying', ['path' => $path]);
                $response = $send($this->getAccessToken(forceRefresh: true));
            }
        } catch (ConnectionException $e) {
            $this->log('error', 'Connection error', ['path' => $path, 'message' => $e->getMessage()]);
            throw new ZohoApiException('Cannot connect to Zoho CRM API', 0, $e);
        }

        return $response;
    }

    protected function assertSuccessful(Response $response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        $this->log('error', 'API error', [
            'context' => $context,
            'status' => $response->status(),
            'body' => $this->safeBody($response),
        ]);

        throw new ZohoApiException("Zoho API error on {$context} (HTTP {$response->status()})");
    }

    /**
     * เรียก closure แบบไม่ให้ error ของ related record ทำให้ทั้งเอกสารล้มเหลว
     */
    protected function tryGet(callable $fn): ?array
    {
        try {
            return $fn();
        } catch (ZohoApiException $e) {
            $this->log('warning', 'Related record fetch failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    protected function version(): string
    {
        return $this->config['api_version'] ?? 'v8';
    }

    protected function safeBody(Response $response): string
    {
        return mb_substr($response->body(), 0, 2000);
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        Log::channel('zoho')->{$level}($message, $context + ['org' => $this->config['org_id'] ?? null]);
    }
}
