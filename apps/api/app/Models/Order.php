<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Commission;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Product;
use App\Models\PartnerQuote;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToCompany;

    /** The workflow that governs an order's life (declared by the ArmorHub module as data). */
    public const WORKFLOW = 'armorhub.order';

    public function shipments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** Goods kept by ArmorHub are shipped from the market's warehouse, not by the seller. */
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $product = Product::withoutGlobalScopes()->find($order->product_id);
            if ($product?->fulfilment === 'armorhub' && $product->requires_shipping) {
                $tenantId = (int) Company::withoutGlobalScopes()->whereKey($order->company_id)->value('tenant_id');
                $order->fulfilment = 'armorhub';
                $order->warehouse_id = app(\App\Services\Warehouse\WarehouseService::class)->warehouseFor($tenantId)?->id;
            }
        });
    }

    protected $fillable = [
        'fulfilment',
        'warehouse_id',
        'customer_id',
        'product_id',
        'subject_type',
        'subject_id',
        'status',
        'product_amount',
        'service_amount',
        'commission_amount',
        'total_amount',
        'currency',
        'market',
        'tax_rate',
        'tax_amount',
        'company_id',
        'shipping_address',
        'scheduled_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'guest_name',
        'guest_email',
        'guest_token',
    ];

    /** The delivery address is only sent to the customer and the staff handling the parcel. */
    protected $hidden = ['shipping_address', 'guest_token'];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'status' => OrderStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function commission()
    {
        return $this->hasOne(Commission::class);
    }

    /** The thing being serviced (vehicle, property, device...), if any. */
    public function subject()
    {
        return $this->morphTo();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function partnerQuotes()
    {
        return $this->hasMany(PartnerQuote::class);
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    public function warranty()
    {
        return $this->hasOne(Warranty::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function isGuest(): bool
    {
        return $this->customer_id === null && $this->guest_token !== null;
    }

    /** Where a payment provider sends the buyer back to: the dashboard order, or (for a guest) the order's secret tracking page. */
    public function returnUrl(string $result): string
    {
        $web = rtrim(config('platform.web_url'), '/');

        return $this->isGuest()
            ? "{$web}/track/{$this->guest_token}?checkout={$result}"
            : "{$web}/dashboard/orders/{$this->id}?checkout={$result}";
    }

    /** A signed-in buyer's earlier guest orders (same e-mail) join their account, once that e-mail is verified. */
    public static function claimGuestOrders(User $user): int
    {
        if (! $user->hasVerifiedEmail() || ! $user->email || ! $user->company_id) {
            return 0;
        }

        // Only orders of the store the account belongs to: a customer's dashboard shows their own store's orders.
        return static::withoutGlobalScopes()->where('company_id', $user->company_id)->whereNull('customer_id')->whereNotNull('guest_token')
            ->where('guest_email', strtolower($user->email))->update(['customer_id' => $user->id, 'guest_token' => null]);
    }
}
