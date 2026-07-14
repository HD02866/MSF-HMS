<?php

namespace App\Modules\CardRoom\Requests;

use App\Models\DailyRegister;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Admin', 'Card Officer', 'Recorder');
    }

    public function rules(): array
    {
        return [
            'patient_id'      => ['required', 'integer', 'exists:patients,id'],
            'register_type'   => ['required', 'string', Rule::in(array_keys(DailyRegister::TYPES))],
            'record_date'     => ['required', 'date'],
            'department_name' => ['nullable', 'string', 'max:100'],
            // Referral-only fields
            'referred_from'   => ['nullable', 'string', Rule::in(DailyRegister::REFERRAL_SOURCES)],
            'days_given'      => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
