<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRoomRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'is_active' => ['nullable', 'string', 'in:0,1'],
            'options' => ['required', 'array', 'min:2', 'max:24'],
            'options.*.label' => ['required', 'string', 'max:120'],
            'options.*.bg_color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/'],
            'options.*.text_color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/'],
        ];
    }
}
