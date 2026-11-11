<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth')]
class ApiTokenController extends Controller
{
    /**
     * Respond with no content, so the web middleware attaches a fresh Passport API token cookie.
     */
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
