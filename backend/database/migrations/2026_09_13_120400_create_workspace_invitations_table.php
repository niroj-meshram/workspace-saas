<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('email');
            $table->string('role');
            // SHA-256 hex digest. The raw token is never stored
            // (PROJECT_SPEC.md §10, §17).
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->uuid('invited_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->restrictOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->restrictOnDelete();

            $table->index('workspace_id');
            $table->index('email');
        });

        // "Cannot have multiple active invitations for same email/workspace"
        // (PROJECT_SPEC.md §10). Expiry is time-based and therefore not usable
        // in an index predicate, so the constraint covers pending invitations:
        // not yet accepted and not revoked. Accepted and revoked rows are kept
        // for history and no longer block a fresh invitation.
        DB::statement('
            CREATE UNIQUE INDEX "workspace_invitations_workspace_id_email_pending_unique"
            ON "workspace_invitations" ("workspace_id", "email")
            WHERE "accepted_at" IS NULL AND "deleted_at" IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invitations');
    }
};
