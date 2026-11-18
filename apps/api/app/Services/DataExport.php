<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\CustomerVehicle;
use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\WarrantyClaim;
use Illuminate\Support\Facades\DB;

/**
 * Everything the platform holds about one customer, in one readable file: the right to take your data with you.
 * Other people's data never appears (a seller is a name, never an e-mail), and secrets (password, full VIN, tokens) are left out.
 */
class DataExport
{
    /** @return array<string, mixed> */
    public function of(User $user): array
    {
        $orders = Order::withoutGlobalScopes()->with('product:id,name,name_ar')->where('customer_id', $user->id)->orderBy('id')->get();

        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'city' => $user->city, 'country' => $user->country,
                'language' => $user->locale, 'email_notifications' => (bool) ($user->notify_email ?? true), 'push_notifications' => (bool) ($user->notify_push ?? true),
                'joined' => $user->created_at?->toIso8601String(), 'email_verified' => $user->email_verified_at?->toIso8601String(),
                'referral_code' => $user->referral_code,
            ],
            'addresses' => CustomerAddress::where('user_id', $user->id)->get()->map(fn ($a) => $a->only(['label', 'name', 'phone', 'line1', 'line2', 'city', 'region', 'postal_code', 'is_default']))->values(),
            'cars' => CustomerVehicle::where('customer_id', $user->id)->get()->map(fn ($v) => ['nickname' => $v->nickname, 'year' => $v->year, 'color' => $v->color, 'vin_last4' => $v->vin ? substr((string) $v->vin, -4) : null])->values(),
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id, 'product' => $o->product?->name, 'status' => $o->status instanceof \BackedEnum ? $o->status->value : $o->status, 'quantity' => $o->quantity,
                'total' => (float) $o->total_amount, 'shipping' => (float) $o->shipping_amount, 'discount' => (float) $o->discount_amount, 'currency' => $o->currency,
                'delivery_speed' => $o->delivery_speed, 'preferred_day' => $o->preferred_day?->toDateString(), 'gift_note' => $o->gift_note,
                'delivery_address' => $o->makeVisible('shipping_address')->shipping_address, 'placed' => $o->created_at?->toIso8601String(),
            ])->values(),
            'reviews' => ProductReview::with('product:id,name,name_ar')->where('user_id', $user->id)->get()->map(fn ($r) => ['product' => $r->product?->name, 'rating' => $r->rating, 'comment' => $r->comment, 'photos' => $r->photos ?? [], 'date' => $r->created_at?->toDateString()])->values(),
            'questions' => ProductQuestion::with('product:id,name,name_ar')->where('user_id', $user->id)->get()->map(fn ($q) => ['product' => $q->product?->name, 'question' => $q->question, 'answer' => $q->answer, 'date' => $q->created_at?->toDateString()])->values(),
            'warranty_claims' => WarrantyClaim::with('product:id,name,name_ar')->where('customer_id', $user->id)->get()->map(fn ($c) => ['product' => $c->product?->name, 'problem' => $c->description, 'status' => $c->status, 'resolution' => $c->resolution, 'date' => $c->created_at?->toDateString()])->values(),
            'loyalty_points' => ['balance' => (int) LoyaltyEntry::where('user_id', $user->id)->sum('points'), 'history' => LoyaltyEntry::where('user_id', $user->id)->orderBy('id')->get(['points', 'reason', 'created_at'])->map(fn ($e) => ['points' => $e->points, 'reason' => $e->reason, 'date' => $e->created_at?->toDateString()])->values()],
            'favourites' => DB::table('favourites')->where('user_id', $user->id)->pluck('product_id')->values(),
            'notifications' => $user->notifications()->latest()->limit(200)->get()->map(fn ($n) => ['title' => $n->data['title'] ?? null, 'body' => $n->data['body'] ?? null, 'date' => $n->created_at?->toIso8601String()])->values(),
        ];
    }
}
