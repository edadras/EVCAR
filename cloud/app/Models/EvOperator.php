<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvOperator extends Model
{
    public const DEFAULT_LOGO = '/assets/operators/generic.png';

    protected $fillable = [
        'slug', 'name', 'network', 'logo_path', 'color', 'website', 'phone',
        'app_name', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function stations(): HasMany
    {
        return $this->hasMany(ExternalStation::class);
    }

    public function logoUrl(): string
    {
        $path = $this->logo_path ?: self::DEFAULT_LOGO;

        return str_starts_with($path, 'http') ? $path : '/'.ltrim($path, '/');
    }
}
