<?php

namespace App\Modules\OPD\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSickLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin', 'OPD Nurse') ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_name'  => ['required', 'string', 'max:255'],
            'days'           => ['required', 'integer', 'min:1', 'max:365'],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'diagnosis'      => ['required', 'string', 'max:2000'],
            'recommendation' => ['nullable', 'string', 'max:2000'],
            'signature_data' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_name.required' => 'Please enter the employee name.',
            'days.required'          => 'Please enter the number of days.',
            'days.min'               => 'Days must be at least 1.',
            'start_date.required'    => 'Please select a start date.',
            'end_date.required'      => 'Please select an end date.',
            'end_date.after_or_equal'=> 'End date must be on or after the start date.',
            'diagnosis.required'     => 'Please enter the diagnosis.',
        ];
    }
}
