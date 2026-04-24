<?php

namespace Database\Seeders;

use App\Models\EventRoom;
use App\Models\EventRoomOption;
use Illuminate\Database\Seeder;

class SukienDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (EventRoom::query()->where('slug', 'demo')->exists()) {
            return;
        }

        $room = EventRoom::query()->create([
            'name' => 'Sự kiện demo',
            'slug' => 'demo',
            'is_active' => true,
        ]);

        $opts = [
            ['label' => 'Mặt 1', 'bg_color' => '#1565c0', 'text_color' => '#ffffff'],
            ['label' => 'Mặt 2', 'bg_color' => '#c62828', 'text_color' => '#ffffff'],
            ['label' => 'Mặt 3', 'bg_color' => '#2e7d32', 'text_color' => '#ffffff'],
        ];
        foreach ($opts as $i => $o) {
            EventRoomOption::query()->create([
                'event_room_id' => $room->getKey(),
                'label' => $o['label'],
                'bg_color' => $o['bg_color'],
                'text_color' => $o['text_color'],
                'sort_order' => $i,
            ]);
        }
    }
}
