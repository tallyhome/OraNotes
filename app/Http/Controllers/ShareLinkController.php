<?php

namespace App\Http\Controllers;

use App\Enums\SharePermission;
use App\Models\Note;
use App\Models\ShareLink;
use App\Models\Workspace;
use App\Services\SharingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShareLinkController extends Controller
{
    public function __construct(private SharingService $sharing) {}

    public function storeForNote(Request $request, Note $note): JsonResponse
    {
        $this->authorize('share', $note);
        $data = $request->validate([
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $link = $this->sharing->createLink(
            $note,
            $request->user(),
            SharePermission::Read,
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );

        return response()->json([
            'link' => [
                'token' => $link->token,
                'url' => route('shares.public', $link->token),
                'expires_at' => $link->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function storeForWorkspace(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);
        $link = $this->sharing->createLink($workspace, $request->user(), SharePermission::Read);

        return response()->json([
            'link' => [
                'token' => $link->token,
                'url' => route('shares.public', $link->token),
            ],
        ], 201);
    }

    public function destroy(Request $request, ShareLink $shareLink): JsonResponse
    {
        abort_unless((int) $shareLink->created_by === (int) $request->user()->id || $request->user()->isAdmin(), 403);
        $this->sharing->revokeLink($shareLink, $request->user());

        return response()->json(['ok' => true]);
    }
}
