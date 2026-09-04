<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Support\OraDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(3);
        $document = [
            'version' => 1,
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => fake()->paragraph()],
                    ],
                ],
            ],
        ];

        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'title' => $title,
            'document' => $document,
            'html_preview' => '<p>'.e(OraDocument::extractText($document)).'</p>',
            'text_content' => OraDocument::extractText($document),
            'color' => fake()->randomElement(['yellow', 'blue', 'green', 'pink', 'purple', 'orange', 'gray']),
            'x' => fake()->numberBetween(40, 1400),
            'y' => fake()->numberBetween(40, 900),
            'width' => 260,
            'height' => 220,
            'z_index' => fake()->numberBetween(1, 40),
            'status' => fake()->randomElement(['idea', 'todo', 'in_progress', 'done']),
            'priority' => fake()->randomElement(['low', 'normal', 'high']),
        ];
    }
}
