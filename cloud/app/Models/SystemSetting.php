<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'is_public'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public static function text(string $group, string $key, string $default = ''): string
    {
        $setting = static::where('group', $group)->where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        $value = $setting->value;

        return is_array($value) ? (string) ($value['fa'] ?? $value['text'] ?? reset($value) ?: $default) : (string) $value;
    }
}
