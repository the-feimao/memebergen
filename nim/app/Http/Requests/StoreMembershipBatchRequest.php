<?php

namespace App\Http\Requests;

use App\Services\SystemSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('membership.register.import') ?? false;
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
     * Get validation messages for the uploaded workbook.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose an .xlsx or .csv file to upload.',
            'file.mimetypes' => 'Upload a valid .xlsx workbook or .csv file.',
            'file.extensions' => 'Only .xlsx and .csv files are supported.',
            'file.max' => 'The file exceeds the configured upload size limit of :max KB.',
        ];
    }
}
