<?php

namespace App\Enums;

/**
 * Derived state of an invitation. Not a column: it is computed from
 * accepted_at and expires_at, so it can never drift out of sync with them.
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
}
