<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->catchPhrase(),
            'description' => fake()->optional()->sentence(),
            'status' => ProjectStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ProjectStatus::Archived]);
    }
}
