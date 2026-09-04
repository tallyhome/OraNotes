<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\Authorization\AccessService;
use App\Services\Collab\CollabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollabController extends Controller
{
    public function __construct(
        private CollabService $collab,
        private AccessService $access,
    ) {}

    public function show(Request $request, Note $note): JsonResponse
    {
        $this->authorize('view', $note);
        $members = $this->collab->join($note, $request->user());

        return response()->json([
            ...$this->collab->snapshot($note),
            'canEdit' => $this->access->canEditNote($request->user(), $note),
            'members' => $members,
            'transport' => 'sse+yjs',
        ]);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $data = $request->validate([
            'update' => ['nullable', 'string', 'max:2000000'],
            'state' => ['nullable', 'string', 'max:2000000'],
            'seq' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (! empty($data['update'])) {
            $this->collab->relayUpdate($note, $request->user(), $data['update']);
        }

        $seq = (int) $note->collab_seq;
        if (! empty($data['state'])) {
            $seq = $this->collab->applyState($note, $request->user(), $data['state'], (int) ($data['seq'] ?? 0))['seq'];
        }

        return response()->json(['ok' => true, 'seq' => $seq]);
    }

    public function stream(Request $request, Note $note): StreamedResponse
    {
        $this->authorize('view', $note);
        $this->collab->join($note, $request->user());
        $after = (int) $request->query('after', 0);

        return response()->stream(function () use ($request, $note, $after) {
            $cursor = $after;
            $started = time();
            while (! connection_aborted() && (time() - $started) < 25) {
                $fresh = $note->fresh();
                if (! $fresh || ! $this->access->canViewNote($request->user(), $fresh)) {
                    echo "event: revoked\ndata: {}\n\n";
                    flush();
                    break;
                }
                foreach ($this->collab->pull($fresh, $cursor) as $event) {
                    $cursor = (int) $event['id'];
                    echo 'id: '.$cursor."\n";
                    echo 'data: '.json_encode($event)."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
                usleep(400000);
            }
            $this->collab->leave($note, $request->user());
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function leave(Request $request, Note $note): JsonResponse
    {
        $this->authorize('view', $note);
        $this->collab->leave($note, $request->user());

        return response()->json(['ok' => true]);
    }
}
