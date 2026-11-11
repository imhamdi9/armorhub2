<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\IndustryRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductPriceRuleController extends Controller
{
    public function index(Product $product)
    {
        return $product->priceRules()->orderBy('target_type')->orderBy('target_id')->get();
    }

    /** Replace the product's price rules with the submitted set. */
    public function sync(Request $request, Product $product, IndustryRegistry $registry)
    {
        $data = $request->validate([
            'rules' => 'present|array',
            'rules.*.target_type' => ['required', 'string', 'in:'.implode(',', array_keys($registry->targetModels()))],
            'rules.*.target_id' => 'required|integer',
            'rules.*.price' => 'required|numeric|min:0',
            'rules.*.duration_minutes' => 'nullable|integer|min:0',
            'rules.*.is_active' => 'boolean',
        ]);

        DB::transaction(function () use ($product, $data) {
            $product->priceRules()->delete();
            foreach ($data['rules'] as $rule) {
                $product->priceRules()->create($rule);
            }
        });

        return $this->index($product);
    }
}
