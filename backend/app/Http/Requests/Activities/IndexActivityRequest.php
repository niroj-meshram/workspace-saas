<?php

namespace App\Http\Requests\Activities;

use App\Enums\ActivityType;
use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexActivityRequest extends FormRequest
{
    /**
     * Authorized by the ActivityPolicy in the controller.
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
            'type' => ['sometimes', Rule::enum(ActivityType::class)],
            // Not required to still be a member: the feed must keep showing
            // what someone did after they were removed.
            'user_id' => ['sometimes', 'uuid'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only(Activity::FILTERABLE);
    }
}
