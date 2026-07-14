<?php

namespace App\Modules\OPD\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpdClinicalNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin', 'OPD Nurse') ?? false;
    }

    public function rules(): array
    {
        return [
            'chief_complaint'        => ['nullable', 'string', 'max:2000'],
            'history'                => ['nullable', 'string', 'max:5000'],
            'physical_examination'   => ['nullable', 'string', 'max:5000'],
            'diagnosis'              => ['nullable', 'string', 'max:2000'],
            'treatment_plan'         => ['nullable', 'string', 'max:5000'],
            'follow_up_instructions' => ['nullable', 'string', 'max:2000'],

            // Vital signs — nullable, with medical range validation
            'temperature'         => ['nullable', 'numeric', 'between:30,45'],
            'systolic_bp'         => ['nullable', 'integer', 'between:60,300'],
            'diastolic_bp'        => ['nullable', 'integer', 'between:20,200'],
            'pulse_rate'          => ['nullable', 'integer', 'between:30,250'],
            'respiratory_rate'    => ['nullable', 'integer', 'between:5,60'],
            'spo2'                => ['nullable', 'numeric', 'between:50,100'],
            'weight'              => ['nullable', 'numeric', 'between:0.5,500'],
            'height'              => ['nullable', 'numeric', 'between:20,250'],
            'bmi'                 => ['nullable', 'numeric', 'between:5,100'],
            'random_blood_sugar'  => ['nullable', 'numeric', 'between:1,100'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.max'          => 'This field is too long.',
            'temperature.between' => 'Temperature must be between 30°C and 45°C.',
            'systolic_bp.between'    => 'Systolic BP must be between 60 and 300 mmHg.',
            'diastolic_bp.between'   => 'Diastolic BP must be between 20 and 200 mmHg.',
            'pulse_rate.between'     => 'Pulse rate must be between 30 and 250 bpm.',
            'respiratory_rate.between' => 'Respiratory rate must be between 5 and 60 breaths/min.',
            'spo2.between'           => 'SpO₂ must be between 50% and 100%.',
            'weight.between'         => 'Weight must be between 0.5 kg and 500 kg.',
            'height.between'         => 'Height must be between 20 cm and 250 cm.',
            'bmi.between'            => 'BMI must be between 5 and 100.',
            'random_blood_sugar.between' => 'Random blood sugar must be between 1.0 and 100 mmol/L.',
        ];
    }
}
