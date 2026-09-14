<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    /**
     * Authorized by the WorkspacePolicy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * name is required even on PATCH: it is the only editable attribute, so a
     * request without it is a mistake worth reporting rather than a silent
     * no-op.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
