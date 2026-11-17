<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Ratings from people who really bought the product: only a customer with a completed order for it may review it,
 * once, and may change that review later. A platform administrator can hide a review.
 */
class ProductReviewController extends Controller
{
    /** Whether this person bought and received the product. */
    private function bought($user, int $productId): bool
    {
        return Order::withoutGlobalScopes()->where('customer_id', $user->id)->where('product_id', $productId)->where('status', OrderStatus::Completed->value)->exists();
    }

    /** What the signed-in customer may do on this product: leave a review, or edit the one they left. */
    public function mine(Request $request, int $id)
    {
        Product::withoutGlobalScopes()->findOrFail($id);
        $review = ProductReview::where('product_id', $id)->where('user_id', $request->user()->id)->first();

        return response()->json([
            'eligible' => $this->bought($request->user(), $id),
            'review' => $review ? ['rating' => $review->rating, 'comment' => $review->comment, 'status' => $review->status] : null,
        ]);
    }

    public function store(Request $request, int $id)
    {
        Product::withoutGlobalScopes()->findOrFail($id);
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:1000']);

        abort_unless($this->bought($request->user(), $id), 403, 'Only customers who received this product can review it.');

        $existing = ProductReview::where('product_id', $id)->where('user_id', $request->user()->id)->first();
        $review = ProductReview::updateOrCreate(
            ['product_id' => $id, 'user_id' => $request->user()->id],
            ['rating' => $data['rating'], 'comment' => isset($data['comment']) ? trim(strip_tags($data['comment'])) ?: null : null, 'status' => $existing?->status ?? 'published'],
        );

        return response()->json(['rating' => $review->rating, 'comment' => $review->comment], $existing ? 200 : 201);
    }

    /** Hide or show a review. Platform administrators only. */
    public function moderate(Request $request, int $id)
    {
        abort_unless($request->user()->hasRole('platform-admin'), 403);
        $data = $request->validate(['status' => 'required|in:published,hidden']);

        $review = ProductReview::findOrFail($id);
        $review->update(['status' => $data['status']]);
        Audit::record('product_review.'.$data['status'], $review, ['product_id' => $review->product_id], $request->user());

        return response()->json(['status' => $review->status]);
    }
}
