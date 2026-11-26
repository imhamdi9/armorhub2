<?php

namespace App\Modules\Beauty;

use App\Modules\Contracts\BaseIndustryModule;

class BeautyModule extends BaseIndustryModule
{
    public function key(): string
    {
        return 'beauty';
    }

    public function name(): string
    {
        return 'Beauty & Wellness';
    }

    public function description(): string
    {
        return 'Salons, spas and clinics. Nothing to service on the customer side, so orders have no subject.';
    }

    public function targetModels(): array
    {
        return ['treatment_area' => Models\TreatmentArea::class];
    }
}
