<?php

namespace App\Support;

/**
 * ข้อมูล record ที่พิมพ์เป็น PDF ได้ (Quote, Deal ฯลฯ) — สิ่งที่ QuotePdfService และ partial ร่วมต้องการ
 */
interface PrintableRecord
{
    /** Zoho record ID */
    public function id(): string;

    /** เลขที่เอกสารที่แสดง/ใช้เป็นชื่อไฟล์ */
    public function number(): string;

    /** Modified_Time ของ record (ใช้เป็นส่วนหนึ่งของ cache key) */
    public function modifiedTime(): string;

    /** ชื่อไฟล์ดาวน์โหลด เช่น "IO2026-021613.pdf" */
    public function filename(): string;

    /** หัวข้อเอกสาร (ใส่ใน metadata ของ PDF) */
    public function serviceSubject(): string;

    /** รหัสแบบฟอร์ม ISO ที่พิมพ์ท้ายกระดาษ */
    public function isoCode(): string;

    /** เงื่อนไข/ข้อความยาวที่อาจขึ้นหน้าใหม่ ('' = ไม่มี) */
    public function paymentTerms(): string;

    /** record พนักงาน (Staffs) ที่ผูกไว้ สำหรับชื่อเล่น/ลายเซ็น — null ถ้าไม่มี */
    public function employeeRecord(): ?array;

    /** File_Id__s ของรูปลายเซ็นพนักงาน — null ถ้าไม่มี */
    public function salesSignatureFileId(): ?string;
}
