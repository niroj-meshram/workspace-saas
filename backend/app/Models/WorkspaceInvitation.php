<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\WorkspaceRole;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\WorkspaceInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nothing is mass assignable: every column is either tenant-owned or
 * security-sensitive and is set explicitly by the invitation action
 * (PROJECT_SPEC.md §10, §17).
 */
#[Hidden(['token_hash'])]
class WorkspaceInvitation extends Model
{
    /** @use HasFactory<WorkspaceInvitationFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    /**
     * An invitation is valid for 7 days (PROJECT_SPEC.md §10).
     */
    public const EXPIRES_AFTER_DAYS = 7;

    /**
     * The lookup value for a raw invitation token.
     *
     * Only this digest is ever written to the database; the raw token exists
     * for the length of one request and then only inside the email
     * (PROJECT_SPEC.md §10, §17). SHA-256 rather than a password hash on
     * purpose: the token is 256 bits of entropy, so it needs a deterministic
     * digest that can be looked up by index, not a slow salted one.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    public function status(): InvitationStatus
    {
        return match (true) {
            $this->isAccepted() => InvitationStatus::Accepted,
            $this->isExpired() => InvitationStatus::Expired,
            default => InvitationStatus::Pending,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
