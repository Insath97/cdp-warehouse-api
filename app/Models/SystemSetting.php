<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Helper to get setting value by key with optional default fallback.
     */
    public static function get(string $key, $default = null): ?string
    {
        $setting = static::where('key', $key)->first();
        return $setting && !is_null($setting->value) ? (string) $setting->value : (string) $default;
    }
}
