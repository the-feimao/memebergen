<?php

namespace App\Http\Requests;

use App\Models\UpgradeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUpgradeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('upgrade-types.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $upgradeType = $this->route('upgradeType');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(UpgradeType::class, 'name')->ignore($upgradeType),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
