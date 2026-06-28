<?php

namespace App\Enums;

enum SignupStatus: string
{
    public const DEFAULT = self::Unconfirmed;

    case Confirmed = 'confirmed';
    case Unconfirmed = 'unconfirmed';
    case Cancelled = 'cancelled';

    public function isConfirmed(): bool
    {
        return $this === self::Confirmed;
    }
}
