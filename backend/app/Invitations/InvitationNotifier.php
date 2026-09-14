<?php

namespace App\Invitations;

use App\Mail\WorkspaceInvitationMail;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The application's boundary for invitation email.
 *
 * Everything provider-specific sits behind it: which transport actually sends
 * is a MAIL_MAILER config change (log, postmark, resend, ses, smtp) and no
 * calling code moves. Nothing here implements delivery itself
 * — no custom SMTP.
 */
class InvitationNotifier
{
    /**
     * Send the invitation.
     *
     * Called after the database transaction has committed, and it swallows
     * delivery failures on purpose: the invitation is already a durable fact,
     * and a provider outage must not roll it back or turn a successful
     * invite into a 500. A failed send is logged so it can be re-sent; the
     * admin can always revoke and invite again.
     */
    public function send(WorkspaceInvitation $invitation, string $token): void
    {
        try {
            Mail::to($invitation->email)->send(
                new WorkspaceInvitationMail($invitation, $this->acceptUrl($token))
            );
        } catch (Throwable $e) {
            Log::error('Failed to deliver workspace invitation email.', [
                'invitation_id' => $invitation->getKey(),
                'workspace_id' => $invitation->workspace_id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The link points at the SPA route from PROJECT_SPEC.md §22, not at the
     * API: opening it is public, accepting from it is not.
     */
    public function acceptUrl(string $token): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return "{$frontend}/invitations/{$token}";
    }
}
