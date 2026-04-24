<?php

namespace App\Http\Requests\Sukien;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventBetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'option_id' => ['required', 'integer', 'exists:event_room_options,id'],
            'amount_vnd' => ['required', 'integer', 'min:1000', 'max:1000000000'],
        ];
    }
}
