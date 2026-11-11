<?php

namespace App\Modules\HomeServices;

use App\Modules\Contracts\BaseIndustryModule;

class HomeServicesModule extends BaseIndustryModule
{
    public function key(): string
    {
        return 'home-services';
    }

    public function name(): string
    {
        return 'Home Services';
    }

    public function description(): string
    {
        return 'Cleaning, AC, plumbing, painting and window film for homes and buildings.';
    }

    public function subjectModels(): array
    {
        return ['property' => Models\Property::class];
    }

    public function targetModels(): array
    {
        return ['room_type' => Models\RoomType::class];
    }
}
