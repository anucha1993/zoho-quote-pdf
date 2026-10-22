<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * ข้อผิดพลาดจากการเรียก Zoho CRM API (token, network, response ไม่ถูกต้อง)
 */
class ZohoApiException extends RuntimeException {}
