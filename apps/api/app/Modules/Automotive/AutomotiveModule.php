<?php

namespace App\Modules\Automotive;

use App\Modules\Automotive\Models\VehicleMake;
use App\Modules\Automotive\Models\VehicleModel;
use App\Modules\Automotive\Models\VehiclePart;
use App\Modules\Automotive\Policies\VehicleMakePolicy;
use App\Modules\Contracts\BaseIndustryModule;

/**
 * Automotive protection: PPF, ceramic coating, window tint, detailing.
 * A subject is a vehicle model; price rules target vehicle parts.
 */
class AutomotiveModule extends BaseIndustryModule
{
    public function key(): string
    {
        return 'automotive';
    }

    public function name(): string
    {
        return 'Automotive';
    }

    public function description(): string
    {
        return 'Paint protection film, ceramic coating, window tint and detailing.';
    }

    public function subjectModels(): array
    {
        return ['vehicle_model' => VehicleModel::class];
    }

    public function targetModels(): array
    {
        return ['vehicle_part' => VehiclePart::class];
    }

    public function policies(): array
    {
        return [VehicleMake::class => VehicleMakePolicy::class];
    }

    public function routesFile(): ?string
    {
        return __DIR__.'/routes.php';
    }

    public function permissions(): array
    {
        return [
            'vehicle-makes.view',
            'vehicle-makes.create',
            'vehicle-makes.update',
            'vehicle-makes.delete',
        ];
    }
}
