<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

/** One box for everything an administrator looks for: an order by its number, a person by name, e-mail or phone, a company, a product. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return ['query' => $q, 'groups' => []];
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
        $id = ctype_digit(ltrim($q, '#')) ? (int) ltrim($q, '#') : null;

        $orders = Order::withoutGlobalScopes()->with('product:id,name')
            ->where(fn ($w) => $w->when($id, fn ($x) => $x->orWhere('id', $id))->orWhere('guest_email', 'like', $like))
            ->orderByDesc('id')->limit(5)->get()
            ->map(fn ($o) => ['id' => $o->id, 'title' => "Order #{$o->id}", 'detail' => trim(($o->product?->name ?? '').' · '.($o->status->value ?? $o->status).' · '.number_format((float) $o->total_amount, 2).' '.$o->currency, ' ·'), 'path' => '/dashboard/orders/'.$o->id]);

        $users = User::query()->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->when($id, fn ($x) => $x->orWhere('id', $id)))
            ->where(fn ($w) => $w->where('is_guest', false)->orWhereNull('is_guest'))->orderBy('name')->limit(5)->get()
            ->map(fn ($u) => ['id' => $u->id, 'title' => $u->name, 'detail' => $u->email, 'path' => '/dashboard/users?search='.urlencode($u->email)]);

        $companies = Company::withoutGlobalScopes()->where(fn ($w) => $w->where('name', 'like', $like)->when($id, fn ($x) => $x->orWhere('id', $id)))->orderBy('name')->limit(5)->get()
            ->map(fn ($c) => ['id' => $c->id, 'title' => $c->name, 'detail' => $c->type.' · '.$c->status, 'path' => '/dashboard/companies']);

        $products = Product::withoutGlobalScopes()->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('name_ar', 'like', $like)->when($id, fn ($x) => $x->orWhere('id', $id)))->orderBy('name')->limit(5)->get()
            ->map(fn ($p) => ['id' => $p->id, 'title' => $p->name, 'detail' => trim(($p->category ?? '').' · '.number_format((float) $p->base_price, 2), ' ·'), 'path' => '/dashboard/products/'.$p->id]);

        return ['query' => $q, 'groups' => array_values(array_filter([
            ['key' => 'orders', 'label' => 'Orders', 'items' => $orders],
            ['key' => 'users', 'label' => 'People', 'items' => $users],
            ['key' => 'companies', 'label' => 'Companies', 'items' => $companies],
            ['key' => 'products', 'label' => 'Products', 'items' => $products],
        ], fn ($g) => count($g['items']) > 0))];
    }
}
