<?php

namespace App\Modules\OPD\Requests;

use App\Models\Referral;
use Illuminate\Foundation\Http\FormRequest;

class StoreReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin', 'OPD Nurse') ?? false;
    }

    public function rules(): array
    {
        return [
            'destination'       => ['required', 'string', 'in:'.implode(',', array_keys(Referral::DESTINATIONS))],
            'reason'            => ['required', 'string', 'max:2000'],
            'diagnosis'         => ['required', 'string', 'max:2000'],
            'doctor_nurse_name' => ['required', 'string', 'max:255'],
            'signature_data'    => ['nullable', 'string', 'max:10000'],
            'date'              => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'destination.required'       => 'Please select a destination department.',
            'reason.required'            => 'Please provide a reason for the referral.',
            'diagnosis.required'         => 'Please enter the diagnosis.',
            'doctor_nurse_name.required' => 'Please enter the attending doctor or nurse name.',
            'date.required'              => 'Please select a date.',
        ];
    }
}
