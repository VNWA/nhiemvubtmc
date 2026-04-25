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
            'option_ids' => ['required', 'array', 'min:1', 'max:20'],
            'option_ids.*' => ['integer', 'distinct', 'exists:event_room_options,id'],
            'amount_vnd' => ['required', 'integer', 'min:1000', 'max:1000000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'option_ids.required' => 'Vui lòng chọn ít nhất một mục để tham gia.',
            'option_ids.min' => 'Vui lòng chọn ít nhất một mục để tham gia.',
            'amount_vnd.min' => 'Số tiền tham gia tối thiểu là 1.000đ.',
        ];
    }
}
