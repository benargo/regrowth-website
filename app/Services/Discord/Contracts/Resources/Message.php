<?php

namespace App\Services\Discord\Contracts\Resources;

interface Message
{
    public string $id { get; }

    public string $channel_id { get; }
}
