<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'icon' => '🗂️',
            'color' => fake()->randomElement(['yellow', 'blue', 'green', 'pink', 'purple', 'orange', 'gray']),
            'is_default' => false,
            'canvas_settings' => ['zoom' => 1, 'x' => 0, 'y' => 0, 'snap' => false],
        ];
    }
}
