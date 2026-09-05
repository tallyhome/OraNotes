<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Services\ActivityLogger;
use App\Services\NoteService;
use Illuminate\Database\Eloquent\Builder;
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
        $status = $this->statusFilter($request);

        $notes = Note::query()
            ->withTrashed()
            ->with(['author', 'workspace'])
            ->when($request->string('q')->isNotEmpty(), function (Builder $q) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn (Builder $inner) => $inner->where('title', 'like', $term)->orWhere('text_content', 'like', $term));
            })
            ->when($status === 'active', fn (Builder $q) => $q->whereNull('deleted_at')->where('is_archived', false))
            ->when($status === 'archived', fn (Builder $q) => $q->whereNull('deleted_at')->where('is_archived', true))
            ->when($status === 'trashed', fn (Builder $q) => $q->onlyTrashed())
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Notes', [
            'notes' => $notes->through(fn (Note $note) => NoteResource::makeArray($note) + [
                'internal_id' => $note->id,
                'author_name' => $note->author?->name,
                'author_email' => $note->author?->email,
                'workspace_name' => $note->workspace?->name,
                'is_trashed' => $note->trashed(),
            ]),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $status,
            ],
        ]);
    }

    public function update(Request $request, string $note): RedirectResponse
    {
        $model = $this->resolveNote($note);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'is_archived' => ['sometimes', 'boolean'],
            'color' => ['sometimes', 'string', 'max:16'],
            'status' => ['sometimes', 'string', 'max:24'],
            'priority' => ['sometimes', 'string', 'max:16'],
        ]);

        $this->notes->update($model, $request->user(), $data);
        $this->logger->log(ActivityAction::NoteUpdated, $request->user(), $model, ['admin' => true]);

        return back();
    }

    public function restore(Request $request, string $note): RedirectResponse
    {
        $model = $this->resolveNote($note);
        if ($model->trashed()) {
            $this->notes->restore($model, $request->user());
        } else {
            $this->notes->update($model, $request->user(), ['is_archived' => false]);
        }

        return back();
    }

    public function destroy(Request $request, string $note): RedirectResponse
    {
        $this->notes->forceDelete($this->resolveNote($note), $request->user());

        return back();
    }

    private function resolveNote(string $note): Note
    {
        return Note::withTrashed()->where('uuid', $note)->firstOrFail();
    }

    private function statusFilter(Request $request): string
    {
        $status = $request->string('status')->toString();

        return in_array($status, ['active', 'archived', 'trashed'], true) ? $status : '';
    }
}
