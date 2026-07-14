<?php

namespace App\Modules\Pharmacy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin', 'OPD Nurse') ?? false;
    }

    public function rules(): array
    {
        return [
            'prescriber_name'  => ['nullable', 'string', 'max:150'],
            'request_date'     => ['required', 'date'],
            'clinical_notes'   => ['nullable', 'string', 'max:3000'],
            'is_external'      => ['boolean'],
            'external_notes'   => ['nullable', 'string', 'max:1000'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.medicine_id'   => ['nullable', 'integer', 'exists:medicines,id'],
            'items.*.medicine_name' => ['required', 'string', 'max:200'],
            'items.*.dosage'        => ['nullable', 'string', 'max:100'],
            'items.*.frequency'     => ['nullable', 'string', 'max:100'],
            'items.*.duration'      => ['nullable', 'string', 'max:100'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'items.*.notes'         => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'          => 'At least one medicine must be prescribed.',
            'items.min'               => 'At least one medicine must be prescribed.',
            'items.*.medicine_name.required' => 'Medicine name is required.',
            'items.*.quantity.required'=> 'Quantity is required.',
            'items.*.quantity.min'     => 'Quantity must be at least 1.',
            'request_date.required'   => 'Request date is required.',
        ];
    }
}
