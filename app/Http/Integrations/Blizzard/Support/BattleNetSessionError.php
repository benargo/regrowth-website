<?php

namespace App\Http\Integrations\Blizzard\Support;

/**
 * Why a request made with the member's Battle.net session couldn't complete.
 */
enum BattleNetSessionError: string
{
    case Reconnect = 'reconnect';
    case Unavailable = 'unavailable';

    public function message(): string
    {
        return match ($this) {
            self::Reconnect => 'Your Battle.net connection has expired. Please connect again.',
            self::Unavailable => 'Battle.net is unavailable right now. Please try again shortly.',
        };
    }
}
