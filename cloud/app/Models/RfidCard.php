<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RfidCard extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'id_tag', 'user_id', 'organization_id', 'card_label',
        'status', 'issued_at', 'expires_at', 'version', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function cardTypeLabel(): string
    {
        return match ($this->meta['card_type'] ?? null) {
            'org' => 'سازمانی',
            'anon' => 'بی‌نام',
            default => $this->organization_id ? 'سازمانی' : 'شخصی',
        };
    }

    public function isOfflineAllowed(): bool
    {
        return (bool) ($this->meta['offline_allowed'] ?? false);
    }
}
