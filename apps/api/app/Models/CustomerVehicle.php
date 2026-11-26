<?php

namespace App\Models;

use App\Modules\Automotive\Models\VehicleModel;
use Illuminate\Database\Eloquent\Model;

class CustomerVehicle extends Model
{
    protected $fillable = ['customer_id', 'vehicle_model_id', 'year', 'nickname', 'vin', 'color', 'is_default'];

    /** The VIN identifies one car: it is stored encrypted and never leaves the API in full. */
    protected $casts = ['vin' => 'encrypted', 'is_default' => 'boolean', 'year' => 'integer'];

    protected $hidden = ['vin'];

    public function model()
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    /** "Toyota Camry 2020" */
    public function label(): string
    {
        $m = $this->model()->with('make')->first();

        return trim(($m?->make?->name ?? '').' '.($m?->name ?? '').' '.$this->year);
    }

    /** ••••••••••••12345, enough to recognise it. */
    public function maskedVin(): ?string
    {
        return $this->vin ? str_repeat('•', max(0, strlen($this->vin) - 4)).substr($this->vin, -4) : null;
    }
}
