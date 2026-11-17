<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use Illuminate\Http\Request;

/** The customer's cart. */
class CartController extends Controller
{
    public function __construct(private CartService $carts)
    {
    }

    public function show(Request $request)
    {
        return $this->carts->view($request->user());
    }

    public function add(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|integer', 'option_id' => 'nullable|integer', 'quantity' => 'nullable|integer|min:1|max:'.CartService::MAX_QUANTITY, 'vehicle_id' => 'nullable|integer']);
        $this->carts->add($request->user(), (int) $data['product_id'], isset($data['option_id']) ? (int) $data['option_id'] : null, (int) ($data['quantity'] ?? 1), isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null);

        return response()->json($this->carts->view($request->user()), 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:'.CartService::MAX_QUANTITY]);
        $this->carts->setQuantity($request->user(), $id, (int) $data['quantity']);

        return $this->carts->view($request->user());
    }

    public function destroy(Request $request, int $id)
    {
        $this->carts->remove($request->user(), $id);

        return $this->carts->view($request->user());
    }

    public function clear(Request $request)
    {
        $this->carts->clear($request->user());

        return $this->carts->view($request->user());
    }
}
