<?php

namespace App\Http\Requests\Members;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceMemberRequest extends FormRequest
{
    /**
     * Authorized by the WorkspaceMemberPolicy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
        ];
    }

    public function role(): WorkspaceRole
    {
        return WorkspaceRole::from($this->string('role')->toString());
    }
}
