<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasUuid;

    /** Money paid into the wallet by the customer (gateway or admin top-up). */
    public const TOPUP_TYPES = ['credit', 'topup'];

    /** Money given back or granted by the operator. */
    public const GRANT_TYPES = ['refund', 'bonus', 'compensation'];

    public const DEBIT_TYPES = ['debit', 'charge'];

    /** @return list<string> */
    public static function creditTypes(): array
    {
        return array_merge(self::TOPUP_TYPES, self::GRANT_TYPES);
    }

    public function isCredit(): bool
    {
        return in_array($this->type, self::creditTypes(), true);
    }

    protected $fillable = [
        'uuid', 'wallet_id', 'type', 'amount', 'balance_after',
        'reference_type', 'reference_id', 'idempotency_key',
        'edge_station_uuid', 'description', 'meta', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'topup', 'credit' => 'شارژ کیف پول',
            'charge', 'debit' => 'پرداخت شارژ',
            'refund' => 'استرداد',
            'bonus' => 'پاداش',
            'compensation' => 'اعتبار جبرانی',
            'adjustment' => 'تعدیل',
            default => $this->description ?: $this->type,
        };
    }
}
