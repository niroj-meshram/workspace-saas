<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'type' => ActivityType::ProjectCreated,
            'subject_type' => 'project',
            'subject_id' => fn (array $attributes) => Project::factory()
                ->create(['workspace_id' => $attributes['workspace_id']])
                ->getKey(),
            'metadata' => [],
        ];
    }

    public function forSubject(Model $subject): static
    {
        return $this->state(fn () => [
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }
}
