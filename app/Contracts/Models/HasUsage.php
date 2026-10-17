<?php

namespace App\Contracts\Models;

/**
 * A model that other records can reference, which blocks deleting it.
 * Implemented by using the App\Models\Concerns\TracksUsage trait.
 */
interface HasUsage
{
    /**
     * Determine whether any record in the model's usage relations references it.
     */
    public function isInUse(): bool;
}
