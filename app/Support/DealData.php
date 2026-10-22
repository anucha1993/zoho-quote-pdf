<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * ข้อมูล Deal (ใบงาน) สำหรับฟอร์ม "Deal Information & Tracking"
 *
 * API name ของ field อ่านจาก config('zoho.deal_fields') (ป้ายชื่อใน CRM เป็นภาษาไทย Zoho ตั้งชื่อ fieldN ให้)
 */
class DealData implements PrintableRecord
{
    protected array $f;

    public function __construct(
        public readonly array $deal,
        public readonly ?array $account = null,
        public readonly ?array $contact = null,
        public readonly ?array $employee = null,
    ) {
        $this->f = config('zoho.deal_fields', []);
    }

    /* ------------------------------------------------------------------ */
    /*  PrintableRecord */
    /* ------------------------------------------------------------------ */

    public function id(): string
    {
        return (string) ($this->deal['id'] ?? '');
    }

    /** เลขใบงาน = Job Code Auto Number (ถอยไป M5L Job Code / Deal Name) */
    public function number(): string
    {
        foreach ([$this->f['job_code'] ?? 'Job_Code_Auto_Number', $this->f['m5l_job_code'] ?? 'M5L_Job_Code', 'Deal_Name'] as $key) {
            $value = $this->deal[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $this->id();
    }

    public function modifiedTime(): string
    {
        return (string) ($this->deal['Modified_Time'] ?? '');
    }

    public function filename(): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|\r\n]+/u', '-', $this->number()) ?: $this->id();

        return trim($name).'.pdf';
    }

    public function serviceSubject(): string
    {
        return $this->dealName();
    }

    public function isoCode(): string
    {
        return $this->lookupName($this->f['iso'] ?? 'ISO') ?: (string) config('quote.form_code', '');
    }

    public function paymentTerms(): string
    {
        return '';
    }

    public function employeeRecord(): ?array
    {
        return $this->employee;
    }

    public function salesSignatureFileId(): ?string
    {
        $field = config('zoho.fields.custom.employee_signature', 'Image_Sing');
        $value = $this->employee[$field] ?? null;
        $first = is_array($value) ? ($value[0] ?? null) : null;
        $fileId = is_array($first) ? ($first['File_Id__s'] ?? null) : null;

        return is_string($fileId) && $fileId !== '' ? $fileId : null;
    }

    /* ------------------------------------------------------------------ */
    /*  ข้อมูลในฟอร์ม */
    /* ------------------------------------------------------------------ */

    public function dealName(): string
    {
        return (string) ($this->deal['Deal_Name'] ?? '');
    }

    public function customerName(): string
    {
        return (string) ($this->account['Account_Name'] ?? $this->deal['Account_Name']['name'] ?? '');
    }

    public function contactName(): string
    {
        return (string) ($this->contact['Full_Name'] ?? $this->deal['Contact_Name']['name'] ?? '');
    }

    /** «Contacts.Phone» */
    public function contactPhone(): string
    {
        return (string) ($this->contact['Phone'] ?? $this->contact['Mobile'] ?? '');
    }

    public function documentGroup(): string
    {
        return $this->text($this->f['document_group'] ?? 'Document_Group');
    }

    public function jobCode(): string
    {
        return $this->text($this->f['job_code'] ?? 'Job_Code_Auto_Number');
    }

    public function m5lJobCode(): string
    {
        return $this->text($this->f['m5l_job_code'] ?? 'M5L_Job_Code');
    }

    /** ประเภทใบงาน MOU (multi-select) */
    public function mouService(): string
    {
        return $this->text($this->f['mou_service'] ?? 'MOU_Service');
    }

    public function maleWorkers(): string
    {
        return $this->number_($this->f['male'] ?? 'field3');
    }

    public function femaleWorkers(): string
    {
        return $this->number_($this->f['female'] ?? 'field2');
    }

    public function totalWorkers(): string
    {
        return $this->number_($this->f['total_workers'] ?? 'field5');
    }

    public function nationality(): string
    {
        return $this->text($this->f['nationality'] ?? 'field48');
    }

    /** ระบุค่าใช้จ่ายแรงงาน ต่อ คน */
    public function workerCostPerPerson(): string
    {
        return $this->money($this->f['worker_cost'] ?? 'field55');
    }

    public function workerCostTotal(): string
    {
        return $this->money($this->f['worker_cost_total'] ?? 'field57');
    }

    /** ระบุค่าใช้จ่ายนายจ้าง ต่อ คน */
    public function employerCostPerPerson(): string
    {
        return $this->money($this->f['employer_cost'] ?? 'field54');
    }

    public function employerCostTotal(): string
    {
        return $this->money($this->f['employer_cost_total'] ?? 'field56');
    }

    public function healthCheck(): string
    {
        return $this->text($this->f['health_check'] ?? 'field59');
    }

    public function healthInsurance(): string
    {
        return $this->text($this->f['health_insurance'] ?? 'field60');
    }

    /** หมายเหตุ: Description (ถ้าว่างใช้ บันทึกฝ่ายขาย) */
    public function remark(): string
    {
        return $this->text($this->f['remark'] ?? 'Description') ?: $this->text($this->f['remark_fallback'] ?? 'field58');
    }

    /**
     * ผู้เปิดใบงาน: «Deals.ชื่อจริง» «Deals.นามสกุล» «รหัสพนักงาน.ชื่อเล่น» (ถ้าว่างใช้ Owner)
     */
    public function openerName(): string
    {
        $nickname = (string) ($this->employee[config('zoho.fields.custom.employee_nickname', 'Nickname')] ?? '');
        $parts = array_filter([
            $this->text($this->f['opener_first_name'] ?? 'field50'),
            $this->text($this->f['opener_last_name'] ?? 'field51'),
            $nickname,
        ], fn ($v) => $v !== '');

        return $parts !== [] ? implode(' ', $parts) : (string) ($this->deal['Owner']['name'] ?? '');
    }

    public function startDate(): ?Carbon
    {
        return $this->date($this->deal[$this->f['start_date'] ?? 'Start_Date'] ?? null);
    }

    /**
     * แถวตาราง Tracking System
     *
     * @return array<int, array{no:int, job_status:string, tracking_status:string, expected_date:string,
     *   actual_date:string, estimated_days:string, actual_days:string, delay_reason:string}>
     */
    public function trackingRows(): array
    {
        $rows = $this->deal[$this->f['tracking_subform'] ?? 'Tracking_System'] ?? [];
        $out = [];

        foreach (array_values(is_array($rows) ? $rows : []) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            $out[] = [
                'no' => (int) ($row['LinkingModule5_Serial_Number'] ?? $i + 1),
                'job_status' => (string) ($row['Job_Status'] ?? ''),
                'tracking_status' => (string) ($row['Tracking_Status'] ?? ''),
                'expected_date' => QuoteData::dateFormat($this->date($row['Expected_Date'] ?? null)),
                'actual_date' => QuoteData::dateFormat($this->date($row['Actual_Date'] ?? null)),
                'estimated_days' => $this->scalar($row['Estimated_Days'] ?? null),
                'actual_days' => $this->scalar($row['Actual'] ?? null),
                'delay_reason' => (string) ($row['Delay_Reason'] ?? ''),
            ];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  helpers */
    /* ------------------------------------------------------------------ */

    /** ค่าข้อความ: รองรับ lookup (array name), multi-select (array ของ string) */
    protected function text(string $key): string
    {
        $value = $this->deal[$key] ?? null;

        if (is_array($value)) {
            if (isset($value['name'])) {
                return trim((string) $value['name']);
            }

            return implode(', ', array_filter(array_map(fn ($v) => is_array($v) ? ($v['name'] ?? '') : (string) $v, $value)));
        }

        return trim((string) ($value ?? ''));
    }

    protected function lookupName(string $key): string
    {
        $value = $this->deal[$key] ?? null;

        return is_array($value) ? (string) ($value['name'] ?? '') : (string) ($value ?? '');
    }

    protected function number_(string $key): string
    {
        $value = $this->deal[$key] ?? null;

        return is_numeric($value) ? QuoteData::qty((float) $value) : $this->scalar($value);
    }

    protected function money(string $key): string
    {
        $value = $this->deal[$key] ?? null;

        return is_numeric($value) ? QuoteData::money((float) $value, false) : $this->scalar($value);
    }

    protected function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

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
}
