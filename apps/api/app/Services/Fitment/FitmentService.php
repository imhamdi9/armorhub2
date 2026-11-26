<?php

namespace App\Services\Fitment;

use App\Models\CustomerVehicle;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Modules\Automotive\Models\VehicleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Whether a product fits a car. A product with no fitment rows (and not marked "fitment required") fits everything; otherwise at least one row must
 * match the car's make, model and year. A row with neither make nor model means every vehicle, and years are an optional range.
 */
class FitmentService
{
    public const FITS = 'fits';
    public const DOES_NOT_FIT = 'does_not_fit';
    public const UNIVERSAL = 'universal';
    public const UNKNOWN = 'unknown';

    /** @return 'fits'|'does_not_fit'|'universal'|'unknown' */
    public function check(Product $product, ?int $modelId, ?int $year): string
    {
        $rows = ProductFitment::where('product_id', $product->id)->get();
        if ($rows->isEmpty() && ! $product->fitment_required) {
            return self::UNIVERSAL;
        }
        if (! $modelId || ! $year) {
            return self::UNKNOWN;
        }

        $makeId = VehicleModel::whereKey($modelId)->value('vehicle_make_id');

        return $rows->contains(fn (ProductFitment $r) => ($r->vehicle_make_id === null || (int) $r->vehicle_make_id === (int) $makeId)
            && ($r->vehicle_model_id === null || (int) $r->vehicle_model_id === $modelId)
            && ($r->year_from === null || $r->year_from <= $year)
            && ($r->year_to === null || $r->year_to >= $year)) ? self::FITS : self::DOES_NOT_FIT;
    }

    public function checkVehicle(Product $product, ?CustomerVehicle $vehicle): string
    {
        return $this->check($product, $vehicle?->vehicle_model_id, $vehicle?->year);
    }

    /** A product that requires a fit cannot be bought for a car it does not fit, or for no car at all. */
    public function assertCanBuy(Product $product, ?CustomerVehicle $vehicle): void
    {
        if (! $product->fitment_required) {
            return;
        }
        if (! $vehicle) {
            throw ValidationException::withMessages(['vehicle_id' => ['Choose the car this is for: it must be checked against it.']]);
        }
        if ($this->checkVehicle($product, $vehicle) !== self::FITS) {
            throw ValidationException::withMessages(['vehicle_id' => ['This product does not fit the car you chose.']]);
        }
    }

    /** Narrows a product query to what fits the car (and what fits everything). */
    public function scopeFitting(Builder $query, int $modelId, int $year): Builder
    {
        $makeId = VehicleModel::whereKey($modelId)->value('vehicle_make_id');

        return $query->where(function (Builder $q) use ($modelId, $year, $makeId) {
            $q->where(fn (Builder $u) => $u->where('fitment_required', false)->whereDoesntHave('fitments'))
                ->orWhereHas('fitments', fn ($f) => $f
                    ->where(fn ($w) => $w->whereNull('vehicle_make_id')->orWhere('vehicle_make_id', $makeId))
                    ->where(fn ($w) => $w->whereNull('vehicle_model_id')->orWhere('vehicle_model_id', $modelId))
                    ->where(fn ($w) => $w->whereNull('year_from')->orWhere('year_from', '<=', $year))
                    ->where(fn ($w) => $w->whereNull('year_to')->orWhere('year_to', '>=', $year)));
        });
    }

    /** What the seller's rows say, for showing on the product page. @return list<array<string, mixed>> */
    public function describe(Product $product): array
    {
        return ProductFitment::where('product_id', $product->id)->get()->map(function (ProductFitment $r) {
            $make = $r->vehicle_make_id ? \App\Modules\Automotive\Models\VehicleMake::whereKey($r->vehicle_make_id)->value('name') : null;
            $model = $r->vehicle_model_id ? VehicleModel::whereKey($r->vehicle_model_id)->value('name') : null;

            return ['id' => $r->id, 'vehicle_make_id' => $r->vehicle_make_id, 'vehicle_model_id' => $r->vehicle_model_id, 'make' => $make, 'model' => $model,
                'year_from' => $r->year_from, 'year_to' => $r->year_to, 'note' => $r->note];
        })->all();
    }
}
