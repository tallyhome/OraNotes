<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Authorization\AccessService;
use Illuminate\Support\Collection;

class SearchService
{
    public function __construct(private AccessService $access) {}

    /**
     * @return array{notes: Collection<int, Note>, workspaces: Collection<int, Workspace>}
     */
    public function search(User $user, string $query, int $limit = 20): array
    {
        $term = trim($query);
        if ($term === '') {
            return ['notes' => collect(), 'workspaces' => collect()];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $workspaces = Workspace::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->limit($limit)
            ->get();

        $notes = Note::query()
            ->with(['workspace', 'tags', 'author'])
            ->where('is_archived', false)
            ->whereHas('workspace', fn ($w) => $w->where('is_archived', false))
            ->where(function ($q) use ($user) {
                $q->whereHas('workspace', fn ($w) => $w->visibleTo($user))
                    ->orWhereHas('shares', fn ($s) => $s->where('user_id', $user->id));
            })
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('text_content', 'like', $like)
                    ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', $like));
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->filter(fn (Note $note) => $this->access->canViewNote($user, $note))
            ->values();

        return [
            'notes' => $notes,
            'workspaces' => $workspaces,
        ];
    }
}
