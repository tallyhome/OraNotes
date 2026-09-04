<?php

namespace App\Http\Controllers;

use App\Http\Resources\NoteResource;
use App\Http\Resources\WorkspaceResource;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'max:120']]);

        $results = $search->search($request->user(), (string) $request->query('q'));

        return response()->json([
            'notes' => $results['notes']->map(fn ($n) => NoteResource::makeArray($n))->values(),
            'workspaces' => $results['workspaces']->map(fn ($w) => WorkspaceResource::makeArray($w))->values(),
        ]);
    }
}
