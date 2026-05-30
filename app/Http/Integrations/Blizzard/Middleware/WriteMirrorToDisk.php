<?php

namespace App\Http\Integrations\Blizzard\Middleware;

use Saloon\Http\Response;

class WriteMirrorToDisk
{
    public function __invoke(Response $response): void {}
}
