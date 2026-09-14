<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            // Nullable for events with no acting user (system/automated).
            $table->uuid('user_id')->nullable();
            $table->string('type');
            // Polymorphic subject, resolved through the morph map registered
            // in AppServiceProvider. Deliberately not a foreign key: the
            // subject may be soft-deleted while the activity is retained
            // permanently (PROJECT_SPEC.md §9, §11).
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->jsonb('metadata')->nullable();
            // created_at only: activities are append-only and are never
            // modified or deleted (PROJECT_SPEC.md §9).
            $table->timestamp('created_at')->nullable();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
