<?php

namespace App\Modules\OPD\Requests;

use App\Models\ConsultationRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreConsultationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin', 'OPD Nurse') ?? false;
    }

    public function rules(): array
    {
        return [
            'destination'      => ['required', 'string', 'in:'.implode(',', array_keys(ConsultationRequest::DESTINATIONS))],
            'reason'           => ['required', 'string', 'max:2000'],
            'clinical_summary' => ['nullable', 'string', 'max:5000'],
            'priority'         => ['required', 'string', 'in:'.implode(',', array_keys(ConsultationRequest::PRIORITIES))],
            'request_date'     => ['required', 'date'],
            'requester_name'   => ['nullable', 'string', 'max:255'],
            'signature_data'   => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'destination.required' => 'Please select a destination department.',
            'reason.required'      => 'Please provide a reason for the consultation request.',
            '*.max'                => 'This field is too long.',
        ];
    }
}
