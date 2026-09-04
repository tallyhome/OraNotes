<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function store(Request $request, AttachmentService $attachments): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:8192'],
            'note' => ['nullable', 'uuid', 'exists:notes,uuid'],
        ]);

        $note = null;
        if ($request->filled('note')) {
            $note = Note::query()->where('uuid', $request->string('note'))->firstOrFail();
            $this->authorize('update', $note);
        }

        $attachment = $attachments->store($request->file('file'), $request->user(), $note);

        return response()->json([
            'url' => $attachment->publicUrl(),
            'alt' => pathinfo($attachment->original_name, PATHINFO_FILENAME),
            'id' => $attachment->uuid,
        ], 201);
    }
}
