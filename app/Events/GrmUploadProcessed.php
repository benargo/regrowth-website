<?php

namespace App\Events;

use App\Contracts\Events\PreparesRegrowthAddonData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GrmUploadProcessed implements PreparesRegrowthAddonData
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public readonly int $processedCount,
        public readonly int $skippedCount,
        public readonly int $warningCount,
        public readonly int $errorCount,
        public readonly array $errors,
    ) {}
}
