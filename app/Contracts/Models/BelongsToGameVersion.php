<?php

namespace App\Contracts\Models;

/**
 * A record that belongs to one game version, or to none, exposing that
 * version's key as its `game_version_id` attribute.
 */
interface BelongsToGameVersion {}
