<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hoạt động',
            self::Locked => 'Đã khóa',
        };
    }
}
