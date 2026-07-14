<?php

namespace App\Modules\CardRoom\Requests;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Patient::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'patient_type_id'      => ['required', 'exists:patient_types,id'],
            'relationship_type_id' => ['nullable', 'exists:relationship_types,id'],
            'employee_no'          => ['nullable', 'string', 'max:50'],
            'insurance_no'         => ['nullable', 'string', 'max:100'],
            'dependent_no'         => ['nullable', 'integer', 'min:0'],
            'full_name'            => ['required', 'string', 'max:255'],
            'gender'               => ['nullable', 'string', 'max:10'],
            'date_of_birth'        => ['required', 'date'],
            'phone'                => ['nullable', 'string', 'max:50'],
            'address'              => ['nullable', 'string'],
            'woreda'               => ['nullable', 'string', 'max:100'],
            'kebele'               => ['nullable', 'string', 'max:100'],
            'house_no'             => ['nullable', 'string', 'max:50'],
            'assign_room'          => ['nullable', 'boolean'],
            // OS / other non-employee types can supply their own card number
            'os_card_number'       => ['nullable', 'string', 'max:50', Rule::unique('patients', 'card_number')],
            // Optional photo — max 2 MB, images only
            'photo'                => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'os_card_number.unique' => 'This card number is already assigned to another patient.',
        ];
    }
}
