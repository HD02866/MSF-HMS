<?php

namespace App\Modules\CardRoom\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Patient::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'patient_type_id' => ['required', 'exists:patient_types,id'],
            'relationship_type_id' => ['nullable', 'exists:relationship_types,id'],
            'employee_no' => ['nullable', 'string', 'max:50'],
            'insurance_no' => ['nullable', 'string', 'max:100'],
            'dependent_no' => ['nullable', 'integer', 'min:0'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:10'],
            'date_of_birth' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'woreda' => ['nullable', 'string', 'max:100'],
            'kebele' => ['nullable', 'string', 'max:100'],
            'house_no' => ['nullable', 'string', 'max:50'],
            'assign_room' => ['nullable', 'boolean'],
        ];
    }
}
