<?php

namespace App\Providers;

use App\Models\ConsultationRequestQueue;
use App\Models\DailyRegister;
use App\Models\LabQueue;
use App\Models\OpdQueue;
use App\Models\Patient;
use App\Models\PharmacyQueue;
use App\Models\Visit;
use App\Policies\ConsultationRequestQueuePolicy;
use App\Policies\DailyRegisterPolicy;
use App\Policies\LabQueuePolicy;
use App\Policies\OpdQueuePolicy;
use App\Policies\PatientPolicy;
use App\Policies\PharmacyQueuePolicy;
use App\Policies\VisitPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Visit::class, VisitPolicy::class);
        Gate::policy(DailyRegister::class, DailyRegisterPolicy::class);
        Gate::policy(OpdQueue::class, OpdQueuePolicy::class);
        Gate::policy(LabQueue::class, LabQueuePolicy::class);
        Gate::policy(PharmacyQueue::class, PharmacyQueuePolicy::class);
        Gate::policy(ConsultationRequestQueue::class, ConsultationRequestQueuePolicy::class);
    }
}
