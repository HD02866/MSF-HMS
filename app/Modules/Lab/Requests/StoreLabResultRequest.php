<?php

namespace App\Modules\Lab\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin', 'Lab Technician') ?? false;
    }

    public function rules(): array
    {
        return [
            'results'                    => ['required', 'array', 'min:1'],
            'results.*.result'           => ['nullable', 'string', 'max:1000'],
            'results.*.remarks'          => ['nullable', 'string', 'max:1000'],
            'results.*.result_date'      => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'results.required'                => 'At least one result must be provided.',
            'results.*.result.max'            => 'Result value is too long (max 1000 characters).',
            'results.*.remarks.max'           => 'Remarks are too long (max 1000 characters).',
            'results.*.result_date.required'  => 'Result date is required for each test.',
            'results.*.result_date.date'      => 'Result date must be a valid date.',
        ];
    }

    /**
     * After base validation, ensure at least one result field is filled.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $results = $this->input('results', []);
            $hasAny  = collect($results)->contains(fn ($r) => filled($r['result'] ?? ''));
            if (! $hasAny) {
                $v->errors()->add('results', 'At least one test result must be filled in.');
            }
        });
    }
}
