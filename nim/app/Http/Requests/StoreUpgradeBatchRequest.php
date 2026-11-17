<?php

namespace App\Http\Requests;

use App\Models\UpgradeType;
use App\Services\SystemSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUpgradeBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('membership.upgrades.import') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKilobytes = app(SystemSettings::class)->getInt(SystemSettings::IMPORT_MAX_KILOBYTES);

        return [
            'upgrade_type_id' => [
                'required',
                'integer',
                Rule::exists(UpgradeType::class, 'id')->where('is_active', true),
            ],
            'file' => [
                'required',
                'file',
                'extensions:csv,xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,application/csv,application/vnd.ms-excel',
                "max:{$maxKilobytes}",
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'upgrade_type_id.required' => 'Select an active upgrade type for this batch.',
            'upgrade_type_id.exists' => 'The selected upgrade type is not available.',
            'file.required' => 'Select an .xlsx or .csv file to upload.',
            'file.extensions' => 'The upload must be an .xlsx or .csv file.',
            'file.mimetypes' => 'The upload must be a valid .xlsx or .csv file.',
            'file.max' => 'The file is too large.',
        ];
    }
}
