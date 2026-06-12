<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyRaidHelperWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('services.raidhelper.webhook_key');
        $header = $request->header('Authorization');

        if (! $configuredKey || ! $header || ! hash_equals($configuredKey, $header)) {
            abort(401);
        }

        return $next($request);
    }
}
