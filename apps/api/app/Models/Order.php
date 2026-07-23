<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Commission;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Film;
use App\Models\InstallationQuote;
use App\Models\Payment;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'customer_id',
        'film_id',
        'vehicle_model_id',
        'status',
        'film_amount',
        'installation_amount',
        'commission_amount',
        'total_amount',
        'currency',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }

    public function film()
    {
        return $this->belongsTo(Film::class);
    }

    public function commission()
    {
        return $this->hasOne(Commission::class);
    }

    public function vehicleModel()
    {
        return $this->belongsTo(VehicleModel::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function installationQuotes()
    {
        return $this->hasMany(InstallationQuote::class);
    }
}