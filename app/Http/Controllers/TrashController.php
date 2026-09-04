<?php

namespace App\Http\Controllers;

use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller
{
    public function __construct(private NoteService $notes) {}

    public function index(Request $request): Response
    {
        $notes = Note::onlyTrashed()
            ->with(['workspace', 'tags', 'author'])
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                    ->orWhereHas('workspace', fn ($w) => $w->where('user_id', $request->user()->id));
            })
            ->latest('deleted_at')
            ->get();

        return Inertia::render('Trash', [
            'notes' => $notes->map(fn (Note $n) => NoteResource::makeArray($n)),
        ]);
    }

    public function restore(Request $request, string $note): JsonResponse
    {
        $model = Note::onlyTrashed()->where('uuid', $note)->firstOrFail();
        $this->authorize('restore', $model);
        $this->notes->restore($model, $request->user());

        return response()->json(['ok' => true]);
    }

    public function forceDestroy(Request $request, string $note): JsonResponse
    {
        $model = Note::onlyTrashed()->where('uuid', $note)->firstOrFail();
        $this->authorize('forceDelete', $model);
        $this->notes->forceDelete($model, $request->user());

        return response()->json(['ok' => true]);
    }
}
