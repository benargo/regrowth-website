<?php

namespace App\Policies;

use App\Models\User;

class SpellPolicy
{
    public function create(User $user): bool
    {
        return $user->isAuthorizedTo('edit-datasets');
    }
}
