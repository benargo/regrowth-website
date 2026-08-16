<?php

namespace App\Contracts;

use Illuminate\Broadcasting\Channel;

interface Commentable
{
    /**
     * The channel that comments on this model broadcast on.
     */
    public function commentChannel(): Channel;
}
