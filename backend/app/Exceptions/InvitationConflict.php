<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The invitation exists, but its current state makes the request impossible.
 *
 * These answer 409 rather than 422: PROJECT_SPEC.md §14 reserves 422 for
 * validation and business-rule failures, and 409 for conflicts — a request
 * that is well-formed and permitted but collides with the state the resource
 * is already in.
 */
class InvitationConflict extends Exception
{
    public static function alreadyAMember(): self
    {
        return new self('That person is already a member of this workspace.');
    }

    public static function alreadyInvited(): self
    {
        return new self('There is already a pending invitation for this email address.');
    }

    public static function alreadyAccepted(): self
    {
        return new self('This invitation has already been accepted.');
    }

    public static function expired(): self
    {
        return new self('This invitation has expired.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
