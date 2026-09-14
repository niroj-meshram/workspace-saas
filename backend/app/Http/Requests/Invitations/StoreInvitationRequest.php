<?php

namespace App\Http\Requests\Invitations;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    /**
     * Authorized by the WorkspaceInvitationPolicy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * expires_at and invited_by are not accepted: the validity window is fixed
     * at 7 days and the inviter is the authenticated admin
     * (PROJECT_SPEC.md §10, §17).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            // Required rather than defaulted: the role decides what the
            // invitee will be able to do, so it should be a deliberate choice.
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    public function role(): WorkspaceRole
    {
        return WorkspaceRole::from($this->string('role')->toString());
    }
}
