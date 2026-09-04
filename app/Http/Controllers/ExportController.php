<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function json(Request $request, Note $note): JsonResponse
    {
        $this->authorize('view', $note);

        return response()->json([
            'title' => $note->title,
            'document' => $note->document,
            'exported_at' => now()->toIso8601String(),
        ]);
    }

    public function html(Request $request, Note $note): Response|StreamedResponse
    {
        $this->authorize('view', $note);

        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>'
            .e($note->title).'</title></head><body>'
            .($note->html_preview ?? '').'</body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.($note->uuid).'.html"',
        ]);
    }
}
