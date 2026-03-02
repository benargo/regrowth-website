<?php

namespace App\Events;

use App\Contracts\Events\PreparesRegrowthAddonData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CharacterDeleted implements PreparesRegrowthAddonData
{
    use Dispatchable, SerializesModels;
}
