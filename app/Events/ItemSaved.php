<?php

namespace App\Events;

use App\Contracts\Events\SchedulesAddonExportBuild;
use App\Models\LootCouncil\Item;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemSaved implements SchedulesAddonExportBuild
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Item $item,
    ) {}
}
