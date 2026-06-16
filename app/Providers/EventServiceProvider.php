<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        // LLM Cache Invalidation Observer
        $clinicalModels = [
            \App\Models\VitalSign::class,
            \App\Models\Encounter::class,
            \App\Models\NursingNote::class,
            \App\Models\MedicationAdministration::class,
            \App\Models\LabServiceRequest::class,
            \App\Models\ImagingServiceRequest::class,
            \App\Models\ProductRequest::class,
            \App\Models\Procedure::class,
            \App\Models\AdmissionRequest::class,
            \App\Models\SpecialistReferral::class,
            \App\Models\NonPharmOrder::class,
            \App\Models\InjectionAdministration::class,
            \App\Models\ImmunizationRecord::class,
        ];

        foreach ($clinicalModels as $model) {
            if (class_exists($model)) {
                $model::observe(\App\Observers\ClinicalDataObserver::class);
            }
        }
    }
}
