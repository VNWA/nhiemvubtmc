<?php

namespace App\Models;

use App\Enums\EventRoundStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRoom extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'avatar_path',
        'is_active',
        'viewer_offset',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'viewer_offset' => 'integer',
        ];
    }

    /**
     * Resolve the public URL for the room avatar (if any).
     *
     * @return Attribute<?string, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = $this->avatar_path;
            if ($path === null || $path === '') {
                return null;
            }

            return asset('storage/'.ltrim($path, '/'));
        });
    }

    /**
     * @return HasMany<EventRoomOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(EventRoomOption::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<EventRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(EventRound::class);
    }

    public function openRound(): ?EventRound
    {
        return $this->rounds()
            ->where('status', EventRoundStatus::Open)
            ->orderByDesc('id')
            ->first();
    }
}
