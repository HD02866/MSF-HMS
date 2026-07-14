<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LabRequest extends Model
{
    protected $fillable = [
        'opd_queue_id',
        'patient_id',
        'requested_by',
        'requester_name',
        'signature_data',
        'request_date',
        'priority',
        'clinical_notes',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
        ];
    }

    // ── Priority constants ──────────────────────────────────────────────────

    public const PRIORITIES = [
        'Normal' => 'Normal',
        'Urgent' => 'Urgent',
    ];

    // ── Catalog of available tests ──────────────────────────────────────────
    // Grouped by panel — extend as needed for future phases.

    public const TEST_CATALOG = [
        'Haematology' => [
            'CBC (Complete Blood Count)',
            'Haemoglobin (Hgb)',
            'Haematocrit (Hct)',
            'White Blood Cell Count (WBC)',
            'Platelet Count',
            'Peripheral Blood Film',
            'Erythrocyte Sedimentation Rate (ESR)',
            'Reticulocyte Count',
            'Blood Group & Rh Factor',
            'Coagulation Profile (PT/APTT)',
        ],
        'Chemistry' => [
            'Random Blood Sugar (RBS)',
            'Fasting Blood Sugar (FBS)',
            'HbA1c',
            'Urea',
            'Creatinine',
            'Uric Acid',
            'Total Protein',
            'Albumin',
            'Total Bilirubin',
            'Direct Bilirubin',
            'SGOT (AST)',
            'SGPT (ALT)',
            'Alkaline Phosphatase (ALP)',
            'Gamma-GT (GGT)',
            'Cholesterol',
            'Triglycerides',
            'HDL',
            'LDL',
            'Calcium',
            'Phosphorus',
            'Sodium',
            'Potassium',
            'Chloride',
            'Bicarbonate (CO2)',
            'Amylase',
            'Lipase',
            'Lactate Dehydrogenase (LDH)',
        ],
        'Microbiology' => [
            'Blood Culture & Sensitivity',
            'Urine Culture & Sensitivity',
            'Stool Culture',
            'Wound Swab Culture',
            'Throat Swab Culture',
            'Sputum Culture',
            'AFB Smear (Tuberculosis)',
            'Malaria Rapid Test (RDT)',
            'Malaria Thick & Thin Film',
            'Widal Test (Typhoid)',
            'Brucella Agglutination',
        ],
        'Urinalysis' => [
            'Urinalysis (Routine)',
            'Urine Microscopy',
            'Urine Protein',
            'Urine Glucose',
            'Urine Ketones',
            '24-Hour Urine Protein',
        ],
        'Serology / Immunology' => [
            'HIV Rapid Test',
            'HIV-1/2 ELISA',
            'Hepatitis B Surface Antigen (HBsAg)',
            'Anti-HCV Antibody',
            'VDRL / RPR (Syphilis)',
            'TPHA (Syphilis Confirmation)',
            'Rheumatoid Factor (RF)',
            'Anti-Nuclear Antibody (ANA)',
            'C-Reactive Protein (CRP)',
            'ASO Titer',
            'Pregnancy Test (urine)',
            'TSH',
            'Free T3',
            'Free T4',
        ],
        'Stool' => [
            'Stool Routine / Microscopy',
            'Stool Occult Blood',
            'Stool for Ova & Parasites',
        ],
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function opdQueue(): BelongsTo
    {
        return $this->belongsTo(OpdQueue::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(LabRequestTest::class)->orderBy('test_name');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }

    public function labQueue(): HasOne
    {
        return $this->hasOne(LabQueue::class);
    }

    public function labNotifications(): HasMany
    {
        return $this->hasMany(LabNotification::class);
    }
}
