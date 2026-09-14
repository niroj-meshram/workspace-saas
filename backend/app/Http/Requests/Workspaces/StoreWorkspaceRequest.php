<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    /**
     * Any authenticated user may create a workspace (PROJECT_SPEC.md §6).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the name is accepted. The creator comes from the session, never
     * from the payload (PROJECT_SPEC.md §17).
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
