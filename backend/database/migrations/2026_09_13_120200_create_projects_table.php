<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('name');
            $table->text('description')->nullable();
            // active | archived. Projects are never soft-deleted
            // (PROJECT_SPEC.md §11).
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->restrictOnDelete();

            $table->unique(['workspace_id', 'name']);

            // Target for the composite foreign key on `tasks`, which is what
            // guarantees a task cannot point at a project in another
            // workspace (PROJECT_SPEC.md §8).
            $table->unique(['id', 'workspace_id']);

            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
