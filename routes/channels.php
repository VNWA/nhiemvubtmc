<?php

use App\Models\EventRoom;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id) {
    return (int) $user->id === (int) $id;
});

/*
| Các client trong phòng join presence để đếm số người online.
*/
Broadcast::channel('sukien-presence.{roomId}', function (User $user, string $roomId) {
    $room = EventRoom::query()->find($roomId);
    if ($room === null) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
