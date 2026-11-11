<?php

namespace App\Modules\Devices;

use App\Modules\Contracts\BaseIndustryModule;

class DevicesModule extends BaseIndustryModule
{
    public function key(): string
    {
        return 'devices';
    }

    public function name(): string
    {
        return 'Devices';
    }

    public function description(): string
    {
        return 'Screen protection, repair and care for phones, laptops and tablets.';
    }

    public function subjectModels(): array
    {
        return ['device_model' => Models\DeviceModel::class];
    }

    public function targetModels(): array
    {
        return ['device_component' => Models\DeviceComponent::class];
    }

    public function catalogModels(): array
    {
        return ['device_brand' => Models\DeviceBrand::class];
    }
}
