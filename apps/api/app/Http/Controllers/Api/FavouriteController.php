<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Services\Storefront\StorefrontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Products a customer saved. Only products the customer's own store could show can be saved. */
class FavouriteController extends Controller
{
    private const MAX = 200;

    public function __construct(private StorefrontService $stores)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $company = Company::withoutGlobalScopes()->find($user->company_id);
        $ids = DB::table('favourites')->where('user_id', $user->id)->orderByDesc('id')->pluck('product_id');

        $products = Product::visibleInTenant($company?->tenant_id)->where('is_active', true)->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->filter(fn ($id) => $products->has($id))->map(fn ($id) => $this->stores->card($products[$id], $company))->values();
    }

    /** Just the ids, so every product page can show a filled heart. */
    public function ids(Request $request)
    {
        return DB::table('favourites')->where('user_id', $request->user()->id)->pluck('product_id')->values();
    }

    public function store(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|integer']);
        $user = $request->user();
        $company = Company::withoutGlobalScopes()->find($user->company_id);

        if (! Product::visibleInTenant($company?->tenant_id)->where('is_active', true)->whereKey($data['product_id'])->exists()) {
            throw ValidationException::withMessages(['product_id' => ['This product is not available.']]);
        }
        if (DB::table('favourites')->where('user_id', $user->id)->count() >= self::MAX) {
            throw ValidationException::withMessages(['product_id' => [__('You can keep up to :n favourites. Remove some first.', ['n' => self::MAX])]]);
        }
        DB::table('favourites')->updateOrInsert(['user_id' => $user->id, 'product_id' => $data['product_id']], ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(['product_id' => (int) $data['product_id']], 201);
    }

    public function destroy(Request $request, int $productId)
    {
        DB::table('favourites')->where('user_id', $request->user()->id)->where('product_id', $productId)->delete();

        return response()->json(['message' => 'Removed']);
    }
}
