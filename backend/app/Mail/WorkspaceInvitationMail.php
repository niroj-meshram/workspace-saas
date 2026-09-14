<?php

namespace App\Mail;

use App\Enums\WorkspaceRole;
use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The raw token is passed in rather than read from the invitation: it only
     * exists in memory during the request that created it, and the accept link
     * is the single place it is ever written down (PROJECT_SPEC.md §10).
     */
    public function __construct(
        public readonly WorkspaceInvitation $invitation,
        public readonly string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->invitation->workspace->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.workspace-invitation',
            with: [
                'workspaceName' => $this->invitation->workspace->name,
                'inviterName' => $this->invitation->invitedBy->name,
                // The role is named the way the interface names it: the
                // stored value is "user", but nothing in the product calls a
                // person that.
                'roleLabel' => $this->invitation->role === WorkspaceRole::Admin
                    ? 'an admin'
                    : 'a member',
                'email' => $this->invitation->email,
                'expiresAt' => $this->invitation->expires_at,
                'acceptUrl' => $this->acceptUrl,
            ],
        );
    }
}
