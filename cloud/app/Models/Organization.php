<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'code', 'name', 'type', 'contact_phone', 'contact_email',
        'credit_limit', 'consumption_cap_kwh', 'status', 'manager_user_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'consumption_cap_kwh' => 'decimal:3',
            'meta' => 'array',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
