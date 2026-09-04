<?php

namespace App\Http\Controllers;

use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $notes = Note::query()
            ->with(['workspace', 'tags', 'author'])
            ->where('is_favorite', true)
            ->where('is_archived', false)
            ->whereHas('workspace', fn ($w) => $w->where('is_archived', false))
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                    ->orWhereHas('workspace', fn ($w) => $w->visibleTo($request->user()));
            })
            ->latest('updated_at')
            ->get();

        return Inertia::render('Favorites', [
            'notes' => $notes->map(fn (Note $n) => NoteResource::makeArray($n)),
        ]);
    }
}
