<?php

namespace App\Models\Concerns;

trait StoresAppTimezone
{
    /**
     * Datetime columns hold wall-clock time in the app timezone, so values that
     * arrive with another offset (OCPP / edge ISO-8601 "Z") must be converted first.
     */
    public function fromDateTime($value)
    {
        return empty($value)
            ? $value
            : $this->asDateTime($value)->setTimezone(config('app.timezone'))->format($this->getDateFormat());
    }
}
