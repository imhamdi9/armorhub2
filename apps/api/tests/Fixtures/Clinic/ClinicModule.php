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
        // The clinic's own lifecycle, defined entirely as data. The core knows nothing about appointments.
        $context->guard(WithinHours::class);
        $context->action(SendReminder::class);
        $context->workflow([
            'key' => 'clinic.appointment',
            'initial' => 'booked',
            'states' => ['booked' => ['label' => 'Booked'], 'confirmed' => ['label' => 'Confirmed'], 'checked_in' => ['label' => 'Checked in'], 'completed' => ['label' => 'Completed', 'final' => true], 'cancelled' => ['label' => 'Cancelled', 'final' => true]],
            'transitions' => [
                'confirm' => ['from' => ['booked'], 'to' => 'confirmed', 'actions' => ['clinic.send_reminder']],
                'check_in' => ['from' => ['confirmed'], 'to' => 'checked_in', 'guards' => ['clinic.within_hours']],
                'complete' => ['from' => ['checked_in'], 'to' => 'completed'],
                'cancel' => ['from' => ['booked', 'confirmed'], 'to' => 'cancelled', 'message' => 'Too late to cancel: please call the clinic.'],
            ],
        ]);

        // A launch bundle for a clinic: one step of its own, one step from the core.
        $context->blueprintStep(CreateClinicStep::class);
        $context->blueprintStep(ExplodeStep::class);
        $context->blueprint([
            'id' => 'clinic.small-clinic', 'name' => 'Small clinic', 'description' => 'A clinic that lets patients cancel right up to check-in.', 'params' => ['name' => 'string', 'city' => 'string?'],
            'steps' => [
                ['id' => 'clinic', 'type' => 'clinic.create_clinic', 'args' => ['name' => '{{name}}']],
                ['type' => 'workflow.override', 'args' => ['key' => 'clinic.appointment', 'changes' => [['transition' => 'cancel', 'from' => ['booked', 'confirmed', 'checked_in']]]]],
            ],
        ]);
        $context->blueprint([
            'id' => 'clinic.broken', 'name' => 'Broken', 'description' => 'Fails half way', 'params' => ['name' => 'string'],
            'steps' => [['type' => 'clinic.create_clinic', 'args' => ['name' => '{{name}}']], ['type' => 'clinic.explode']],
        ]);

        // What the clinic's staff may edit on its own landing page. Declared here; the core knows nothing about clinics.
        $context->surface('clinic.landing', 'Clinic landing page', [
            'welcome' => ['type' => 'text', 'label' => 'Welcome line', 'default' => 'Welcome to the clinic', 'max' => 60],
            'phone_link' => ['type' => 'url', 'label' => 'Booking link'],
            'accent' => ['type' => 'color', 'label' => 'Accent', 'default' => '#0b4c72'],
            'show_hours' => ['type' => 'bool', 'label' => 'Show opening hours', 'default' => true],
            'doctors' => ['type' => 'list', 'label' => 'Doctors', 'maxItems' => 3, 'items' => ['name' => ['type' => 'text', 'required' => true, 'max' => 40], 'speciality' => ['type' => 'text', 'max' => 40]]],
        ], 'The public page of a clinic', public: true);

        $context->subscribe(BookingReactor::class);
        $context->subscribe(FlakyReactor::class);
    }
}
