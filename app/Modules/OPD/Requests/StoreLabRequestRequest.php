<?php

namespace App\Modules\OPD\Requests;

use App\Models\LabRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin', 'OPD Nurse') ?? false;
    }

    public function rules(): array
    {
        return [
            'tests'          => ['required', 'array', 'min:1'],
            'tests.*'        => ['required', 'string', 'max:150'],
            'clinical_notes' => ['nullable', 'string', 'max:3000'],
            'priority'       => ['required', 'string', Rule::in(array_keys(LabRequest::PRIORITIES))],
            'request_date'   => ['required', 'date'],
            'requester_name' => ['nullable', 'string', 'max:150'],
            // Base64 PNG data URL — validated loosely (starts with data:image/)
            'signature_data' => ['nullable', 'string', 'max:200000', 'regex:/^data:image\//'],
        ];
    }

    public function messages(): array
    {
        return [
            'tests.required'   => 'At least one test must be selected.',
            'tests.min'        => 'At least one test must be selected.',
            'tests.*.max'      => 'Test name is too long.',
            'priority.in'      => 'Priority must be Normal or Urgent.',
            'request_date.required' => 'Request date is required.',
        ];
    }
}
