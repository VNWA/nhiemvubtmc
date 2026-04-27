<?php

namespace Tests\Unit;

use App\Support\ActivityLogActionLabels;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityLogActionLabelsTest extends TestCase
{
    #[Test]
    public function known_wallet_freeze_maps_to_vietnamese(): void
    {
        $this->assertStringContainsString('Đóng băng', ActivityLogActionLabels::label('wallet.freeze'));
    }

    #[Test]
    public function unknown_action_falls_back_with_prefix(): void
    {
        $this->assertSame(
            'Hành động lạ: custom.unknown_action',
            ActivityLogActionLabels::label('custom.unknown_action'),
        );
    }

    #[Test]
    public function all_includes_each_distinct_action_key(): void
    {
        $all = ActivityLogActionLabels::all();
        $this->assertArrayHasKey('wallet.unfreeze', $all);
        $this->assertArrayHasKey('user.2fa_cleared', $all);
        $this->assertCount(count(array_unique(array_keys($all))), $all);
    }
}
