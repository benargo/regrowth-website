<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LocalLoginController extends Controller
{
    /**
     * Show the manual login form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/LocalLogin');
    }

    /**
     * Log in as an existing user by ID.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'exists:users,id'],
        ]);

        Auth::login(User::findOrFail($validated['id']));

        return redirect()->intended('/')->with('success', 'Logged in as a local test user.');
    }
}
