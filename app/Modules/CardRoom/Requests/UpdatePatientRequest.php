<?php

namespace App\Modules\CardRoom\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('patient')) ?? false;
    }

    public function rules(): array
    {
        $patientId = $this->route('patient')?->id;

        return [
            'patient_type_id'      => ['sometimes', 'required', 'exists:patient_types,id'],
            'relationship_type_id' => ['nullable', 'exists:relationship_types,id'],
            'employee_no'          => ['nullable', 'string', 'max:50'],
            'insurance_no'         => ['nullable', 'string', 'max:100'],
            'dependent_no'         => ['nullable', 'integer', 'min:0'],
            'full_name'            => ['sometimes', 'required', 'string', 'max:255'],
            'gender'               => ['nullable', 'string', 'max:10'],
            'date_of_birth'        => ['sometimes', 'required', 'date'],
            'phone'                => ['nullable', 'string', 'max:50'],
            'address'              => ['nullable', 'string'],
            'woreda'               => ['nullable', 'string', 'max:100'],
            'kebele'               => ['nullable', 'string', 'max:100'],
            'house_no'             => ['nullable', 'string', 'max:50'],
            // Allow editing the card number — must still be unique across all other patients
            'card_number'          => ['nullable', 'string', 'max:50', Rule::unique('patients', 'card_number')->ignore($patientId)],
            // Optional photo replacement — max 2 MB, images only
            'photo'                => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_number.unique' => 'This card number is already assigned to another patient.',
        ];
    }
}
