<?php

namespace App\Modules\CardRoom\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Visit::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
