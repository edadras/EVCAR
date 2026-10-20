<?php

namespace App\Services\Ocpp;

/**
 * خطای پروتکل OCPP که به‌صورت CALLERROR به شارژر برمی‌گردد.
 */
class OcppError extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $description = '',
    ) {
        parent::__construct($description);
    }
}
