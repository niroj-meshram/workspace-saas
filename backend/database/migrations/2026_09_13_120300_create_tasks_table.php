<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('project_id');
            $table->uuid('assignee_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('medium');
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->restrictOnDelete();

            // Composite foreign key: a task's project must live in the task's
            // own workspace. This enforces "task workspace must equal project
            // workspace" (PROJECT_SPEC.md §8) in the database rather than
            // trusting application code, and also covers project existence,
            // so no separate project_id foreign key is needed.
            $table->foreign(['project_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('projects')
                ->restrictOnDelete();

            // Nullable: unassigned tasks, and removing a member nulls their
            // assignments (PROJECT_SPEC.md §8). That nulling is an application
            // operation on soft-deleted memberships, so no ON DELETE action.
            $table->foreign('assignee_id')->references('id')->on('users')->restrictOnDelete();

            $table->index(['workspace_id', 'project_id']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'assignee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
