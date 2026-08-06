<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

#[Middleware('auth')]
class CommentController extends Controller
{
    /**
     * Display a listing of every comment across all commentables.
     */
    #[Authorize('viewAny', Comment::class)]
    public function index(): Response
    {
        $comments = Comment::with(['user', 'commentable'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Loot/Comments/Index', [
            'comments' => CommentResource::collection($comments),
        ]);
    }
}
