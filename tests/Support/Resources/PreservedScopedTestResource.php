<?php

namespace Tests\Support\Resources;

use Illuminate\Http\Resources\Attributes\PreserveKeys;

#[PreserveKeys]
class PreservedScopedTestResource extends ScopedTestResource {}
