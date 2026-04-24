<?php

namespace App\Models;

use Cache;
use Illuminate\Database\Eloquent\Model;

class Appearance extends Model
{
    //
    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    protected static $cacheTTL = 86400; // 1 ngày

    /**
     * 🔹 Lấy giá trị theo key và locale, fallback nếu không có
     */
    public static function getValue(string $key, $default = [])
    {
        $cacheKey = "appearance_{$key}";

        return Cache::remember($cacheKey, static::$cacheTTL, function () use ($key, $default) {
            // Thử lấy theo locale hiện tại
            $record = static::where('key', $key)->first();

            return $record ? $record->value : $default;
        });
    }

    public static function setValue(string $key, $value)
    {
        $record = static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        Cache::put("appearance_{$key}", $value, static::$cacheTTL);

        return $record;
    }

    /**
     * 🔹 Xóa cache khi cập nhật hoặc xóa
     */
    protected static function booted()
    {
        static::updated(function ($appearance) {
            $cacheKey = "appearance_{$appearance->key}";
            Cache::put($cacheKey, $appearance->value, static::$cacheTTL);
        });

        static::deleted(function ($appearance) {
            $cacheKey = "appearance_{$appearance->key}";
            Cache::forget($cacheKey);
        });
    }
}
