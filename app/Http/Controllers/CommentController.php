<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Inertia\Inertia;
use Inertia\Response;

class CommentController extends Controller
{
    /**
     * Display a listing of every comment across all commentables.
     */
    public function index(): Response
    {
        $comments = Comment::with(['user', 'commentable'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Loot/Comments', [
            'comments' => CommentResource::collection($comments),
        ]);
    }
}
