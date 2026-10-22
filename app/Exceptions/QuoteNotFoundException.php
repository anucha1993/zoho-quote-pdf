<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * ไม่พบ Quote ตาม ID ที่ระบุใน Zoho CRM
 */
class QuoteNotFoundException extends RuntimeException {}
