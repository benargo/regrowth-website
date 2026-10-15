<?php

namespace App\Notifications\Attributes;

use Attribute;

/**
 * Marks a notification that posts a new Discord message when the message it edits has been deleted.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class RepostsIfOriginalDeleted {}
