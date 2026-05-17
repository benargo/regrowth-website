<?php

use App\Http\Resources\PresenceUserResource;
use App\Models\Boss;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private data channel: both ShowEvent and EditEvent subscribe here.
// Requires an authenticated user who can view the event.
Broadcast::channel('event.{event}', function (User $user, Event $event): bool {
    return $user->can('view', $event);
});

// Presence channel: only EditEvent joins here.
// Requires manage-raid-plans permission; returns user data for the avatar stack.
Broadcast::channel('event.{event}.editors', function (User $user, Event $event): bool|array {
    if (! $user->can('update', $event)) {
        return false;
    }

    return (new PresenceUserResource($user))->resolve();
});

// Boss strategy channel: ShowEvent and EditEvent subscribe per boss in the event.
// Requires an authenticated user who can view any upcoming event (boss strategies are not event-gated).
Broadcast::channel('boss.{boss}', function (User $user, Boss $boss): bool {
    return true;
});
