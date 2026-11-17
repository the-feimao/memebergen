<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'max_kilobytes' => ['required', 'integer', 'min:1', 'max:102400'],
            'max_rows' => ['required', 'integer', 'min:1', 'max:100000'],
            'chunk_size' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
