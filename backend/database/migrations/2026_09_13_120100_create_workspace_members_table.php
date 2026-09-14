<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('user_id');
            $table->string('role');
            $table->timestamps();
            $table->softDeletes();

            // restrictOnDelete everywhere: tenant data is soft-deleted, never
            // physically cascaded away (PROJECT_SPEC.md §11).
            $table->foreign('workspace_id')->references('id')->on('workspaces')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();

            $table->index('workspace_id');
            $table->index('user_id');
        });

        // UNIQUE(workspace_id, user_id) (PROJECT_SPEC.md §8), scoped to rows
        // that are not soft-deleted so a removed member can be re-invited.
        DB::statement('
            CREATE UNIQUE INDEX "workspace_members_workspace_id_user_id_active_unique"
            ON "workspace_members" ("workspace_id", "user_id")
            WHERE "deleted_at" IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_members');
    }
};
