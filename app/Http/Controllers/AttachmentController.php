<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Note;
use App\Services\AttachmentService;
use App\Services\Authorization\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(Request $request, AttachmentService $attachments): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
            'note' => ['required', 'uuid'],
        ]);

        $note = Note::query()->where('uuid', $request->string('note'))->firstOrFail();
        $this->authorize('update', $note);

        $attachment = $attachments->store($request->file('file'), $request->user(), $note);

        return response()->json([
            'url' => $attachment->publicUrl(),
            'alt' => pathinfo($attachment->original_name, PATHINFO_FILENAME),
            'id' => $attachment->uuid,
        ], 201);
    }

    public function show(Request $request, Attachment $attachment, AccessService $access): StreamedResponse
    {
        $tokens = $request->session()->get(PublicShareController::SESSION_TOKENS_KEY, []);
        abort_unless(
            $access->canDownloadAttachment($request->user(), $attachment, is_array($tokens) ? $tokens : []),
            404,
        );

        abort_unless(Attachment::pathIsSafe($attachment->path), 404);

        $disk = Storage::disk($attachment->disk ?: 'local');
        abort_unless($disk->exists($attachment->path), 404);

        $inline = str_starts_with($attachment->mime, 'image/');
        $disposition = HeaderUtils::makeDisposition(
            $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $attachment->original_name ?: 'fichier',
            'fichier'
        );

        return $disk->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime,
                'Content-Disposition' => $disposition,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            ]
        );
    }
}
