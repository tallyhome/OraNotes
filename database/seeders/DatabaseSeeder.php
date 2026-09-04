<?php

namespace Database\Seeders;

use App\Enums\NoteColor;
use App\Enums\SharePermission;
use App\Enums\UserRole;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Services\NoteService;
use App\Services\SharingService;
use App\Services\WorkspaceService;
use App\Support\OraDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $workspaces = app(WorkspaceService::class);
        $notes = app(NoteService::class);
        $sharing = app(SharingService::class);

        $admin = User::query()->create([
            'name' => 'Ada Admin',
            'email' => 'admin@oranotes.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'theme' => 'auto',
            'locale' => 'fr',
        ]);
        $admin->forceFill(['role' => UserRole::Admin])->save();

        $alice = User::query()->create([
            'name' => 'Alice Martin',
            'email' => 'alice@oranotes.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'theme' => 'light',
            'locale' => 'fr',
        ]);

        $bob = User::query()->create([
            'name' => 'Bob Leroy',
            'email' => 'bob@oranotes.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'theme' => 'dark',
            'locale' => 'fr',
        ]);

        $clara = User::query()->create([
            'name' => 'Clara Nguyen',
            'email' => 'clara@oranotes.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        foreach ([$admin, $alice, $bob, $clara] as $user) {
            $workspaces->createDefaultFor($user);
        }

        $ideas = $workspaces->create($alice, [
            'name' => 'Idées produit',
            'icon' => '💡',
            'color' => 'yellow',
        ]);
        $sprint = $workspaces->create($alice, [
            'name' => 'Sprint',
            'icon' => '🚀',
            'color' => 'blue',
        ]);

        $tags = collect(['urgent', 'design', 'backend', 'recherche'])->map(
            fn (string $name) => Tag::query()->create([
                'user_id' => $alice->id,
                'name' => $name,
                'slug' => $name,
                'color' => 'gray',
            ])
        );

        $samples = [
            ['Roadmap V1', 'Prioriser le bureau virtuel et OraEditor.', NoteColor::Yellow, 80, 80],
            ['Stand-up', 'Points bloquants, démos, prochaines 24h.', NoteColor::Blue, 380, 90],
            ['Recherche UX', 'Tester le pinch-zoom sur tablette.', NoteColor::Pink, 680, 70],
            ['Sécurité', 'Policies + tests IDOR avant v1.', NoteColor::Orange, 120, 360],
            ['Partage', 'Lien lecture seule, token, expiration.', NoteColor::Green, 440, 380],
            ['Palette', 'Ctrl+K pour tout faire sans souris.', NoteColor::Purple, 760, 360],
            ['Corbeille', 'Soft delete, restauration, purge.', NoteColor::Gray, 980, 120],
            ['Favoris', 'Les notes épinglées ici.', NoteColor::Yellow, 200, 620],
        ];

        foreach ($samples as $i => [$title, $body, $color, $x, $y]) {
            $note = $notes->create($ideas, $alice, [
                'title' => $title,
                'document' => $this->doc($body),
                'html_preview' => '<p>'.e($body).'</p>',
                'color' => $color->value,
                'x' => $x,
                'y' => $y,
                'status' => $i % 2 === 0 ? 'todo' : 'idea',
                'is_favorite' => $i < 2,
                'tags' => [$tags[$i % $tags->count()]->name],
            ]);

            if ($i === 0) {
                $sharing->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);
            }
            if ($i === 1) {
                $sharing->shareNoteWithUser($note, $alice, $clara, SharePermission::Read);
            }
        }

        for ($i = 0; $i < 16; $i++) {
            $notes->create($sprint, $alice, [
                'title' => 'Ticket #'.($i + 1),
                'document' => $this->doc('Note de sprint générée pour le smoke test.'),
                'html_preview' => '<p>Note de sprint générée pour le smoke test.</p>',
                'color' => NoteColor::cases()[$i % count(NoteColor::cases())]->value,
                'x' => 60 + ($i % 5) * 280,
                'y' => 60 + intdiv($i, 5) * 250,
                'status' => ['idea', 'todo', 'in_progress', 'done'][$i % 4],
            ]);
        }

        $workspaces->addMember($sprint, $alice, $bob, SharePermission::Edit);

        $bobDesk = Workspace::query()->where('user_id', $bob->id)->where('is_default', true)->first();
        $notes->create($bobDesk, $bob, [
            'title' => 'Privé Bob',
            'document' => $this->doc('Cette note n’appartient qu’à Bob.'),
            'html_preview' => '<p>Cette note n’appartient qu’à Bob.</p>',
            'x' => 140,
            'y' => 140,
        ]);
    }

    /**
     * @return array{version: int, type: string, content: list<array<string, mixed>>}
     */
    private function doc(string $text): array
    {
        $empty = OraDocument::empty();
        $empty['content'][0]['content'][0]['text'] = $text;

        return $empty;
    }
}
