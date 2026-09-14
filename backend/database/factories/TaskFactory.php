<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            // Derived from the project so the composite foreign key on
            // (project_id, workspace_id) is always satisfied.
            'workspace_id' => fn (array $attributes) => Project::findOrFail($attributes['project_id'])->workspace_id,
            'assignee_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'due_date' => null,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => [
            'project_id' => $project->getKey(),
            'workspace_id' => $project->workspace_id,
        ]);
    }
}
