<?php

namespace Tests\Fixtures\Clinic;

use App\Core\Modules\Module;
use App\Core\Modules\ModuleContext;
use App\Core\Modules\ModuleManifest;

/**
 * A pretend second product: appointments for a clinic. It lives OUTSIDE the core and outside ArmorHub, and must work
 * with zero changes to either. If it ever needs a core edit, the core is missing an extension point.
 */
class ClinicModule implements Module
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest('clinic', 'Clinic', '1.0.0', 'Appointment booking for clinics');
    }

    public function register(ModuleContext $context): void
    {
        $context->event('clinic.appointment_booked', ['appointment_id' => 'int', 'patient' => 'string', 'notes' => 'string?'], 'A patient booked an appointment');
        $context->event('clinic.reminder_scheduled', ['appointment_id' => 'int'], 'A reminder was scheduled');
        $context->subscribe(BookingReactor::class);
        $context->subscribe(FlakyReactor::class);
    }
}
