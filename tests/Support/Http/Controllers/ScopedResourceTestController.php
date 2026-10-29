<?php

namespace Tests\Support\Http\Controllers;

use App\Attributes\Http\Controllers\ScopesResourceTo;
use Tests\Support\Resources\ScopedTestResource;

class ScopedResourceTestController
{
    #[ScopesResourceTo(ScopedTestResource::class, 'summary')]
    public function scoped(): ScopedTestResource
    {
        return new ScopedTestResource(['id' => 1, 'name' => 'Thrall', 'secret' => 's']);
    }

    public function plain(): ScopedTestResource
    {
        return new ScopedTestResource(['id' => 1, 'name' => 'Thrall', 'secret' => 's']);
    }
}
