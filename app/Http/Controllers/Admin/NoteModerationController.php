<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Services\ActivityLogger;
use App\Services\NoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NoteModerationController extends Controller
{
    public function __construct(
        private NoteService $notes,
        private ActivityLogger $logger,
    ) {}

    public function index(Request $request): Response
    {
        $notes = Note::query()
            ->withTrashed()
            ->with(['author', 'workspace'])
            ->when($request->string('q')->isNotEmpty(), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($inner) => $inner->where('title', 'like', $term)->orWhere('text_content', 'like', $term));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Notes', [
            'notes' => $notes->through(fn (Note $note) => NoteResource::makeArray($note) + [
                'internal_id' => $note->id,
                'author_email' => $note->author?->email,
                'is_trashed' => $note->trashed(),
            ]),
            'filters' => $request->only(['q']),
        ]);
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'is_archived' => ['sometimes', 'boolean'],
            'color' => ['sometimes', 'string', 'max:16'],
            'status' => ['sometimes', 'string', 'max:24'],
            'priority' => ['sometimes', 'string', 'max:16'],
        ]);

        $this->notes->update($note, $request->user(), $data);
        $this->logger->log(ActivityAction::NoteUpdated, $request->user(), $note, ['admin' => true]);

        return back();
    }

    public function restore(Request $request, string $note): RedirectResponse
    {
        $model = Note::withTrashed()->where('uuid', $note)->firstOrFail();
        if ($model->trashed()) {
            $this->notes->restore($model, $request->user());
        } else {
            $this->notes->update($model, $request->user(), ['is_archived' => false]);
        }

        return back();
    }

    public function destroy(Request $request, Note $note): RedirectResponse
    {
        $this->notes->forceDelete($note, $request->user());

        return back();
    }
}
