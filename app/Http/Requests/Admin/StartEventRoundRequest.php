<?php

namespace App\Http\Requests\Admin;

use App\Services\EventRoundService;
use Illuminate\Foundation\Http\FormRequest;

class StartEventRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'duration_seconds' => [
                'nullable',
                'integer',
                'min:'.EventRoundService::MIN_DURATION_SECONDS,
                'max:'.EventRoundService::MAX_DURATION_SECONDS,
            ],
            'auto_rollover' => ['nullable', 'boolean'],
        ];
    }
}
