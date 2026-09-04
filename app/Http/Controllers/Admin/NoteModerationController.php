<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NoteModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $notes = Note::query()
            ->withTrashed()
            ->with(['author', 'workspace'])
            ->when($request->string('q')->isNotEmpty(), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($inner) => $inner->where('title', 'like', $term)->orWhere('text_content', 'like', $term));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Notes', [
            'notes' => $notes->through(fn (Note $note) => NoteResource::makeArray($note) + [
                'internal_id' => $note->id,
                'author_email' => $note->author?->email,
            ]),
            'filters' => $request->only(['q']),
        ]);
    }

    public function destroy(Note $note): RedirectResponse
    {
        $note->forceDelete();

        return back();
    }
}
