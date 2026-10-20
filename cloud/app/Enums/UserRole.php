<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case FinancialManager = 'financial_manager';
    case StationOperator = 'station_operator';
    case Support = 'support';
    case CorporateManager = 'corporate_manager';
    case Partner = 'partner';
    case Customer = 'customer';

    public function isStaff(): bool
    {
        return match ($this) {
            self::Customer, self::Partner => false,
            default => true,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'مدیر کل',
            self::FinancialManager => 'مدیر مالی',
            self::StationOperator => 'اپراتور ایستگاه',
            self::Support => 'پشتیبانی',
            self::CorporateManager => 'مدیر سازمان',
            self::Partner => 'شریک',
            self::Customer => 'مشتری',
        };
    }
}
