<?php

namespace App\Enums;

enum EventBetStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa hoàn thành',
            self::Completed => 'Đã hoàn thành',
        };
    }
}
