<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\MediaLibrary;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Ratings from people who really bought the product: only a customer with a completed order for it may review it,
 * once, and may change that review later, with up to four photos of what they received. A platform administrator can hide a review.
 */
class ProductReviewController extends Controller
{
    public const MAX_PHOTOS = 4;

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
            'review' => $review ? ['rating' => $review->rating, 'comment' => $review->comment, 'status' => $review->status, 'photos' => $review->photos ?? []] : null,
        ]);
    }

    /** One picture for the review. Only someone who received the product may add one; the file is kept like any other uploaded image. */
    public function photo(Request $request, int $id, MediaLibrary $media)
    {
        Product::withoutGlobalScopes()->findOrFail($id);
        abort_unless($this->bought($request->user(), $id), 403, 'Only customers who received this product can add photos.');
        $request->validate(['image' => 'required|file|max:'.MediaLibrary::MAX_KB]);

        try {
            $file = $media->store($request->file('image'), $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['image' => [$e->getMessage()]]);
        }

        return response()->json(['path' => "/media/{$file->name}"], 201);
    }

    public function store(Request $request, int $id)
    {
        Product::withoutGlobalScopes()->findOrFail($id);
        $data = $request->validate([
            'rating' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:1000',
            'photos' => 'nullable|array|max:'.self::MAX_PHOTOS, 'photos.*' => ['string', 'regex:#^/media/[0-9a-f-]{36}\.(jpg|png|webp|gif)$#'],
        ]);

        abort_unless($this->bought($request->user(), $id), 403, 'Only customers who received this product can review it.');

        // Only pictures this person uploaded themselves can be attached.
        $photos = array_values(array_unique($data['photos'] ?? []));
        $owned = MediaFile::whereIn('name', array_map(fn ($p) => substr($p, 7), $photos))->where('uploaded_by', $request->user()->id)->count();
        if ($owned !== count($photos)) {
            throw ValidationException::withMessages(['photos' => ['Only photos you uploaded can be added.']]);
        }

        $existing = ProductReview::where('product_id', $id)->where('user_id', $request->user()->id)->first();
        $review = ProductReview::updateOrCreate(
            ['product_id' => $id, 'user_id' => $request->user()->id],
            ['rating' => $data['rating'], 'comment' => isset($data['comment']) ? trim(strip_tags($data['comment'])) ?: null : null, 'photos' => $photos ?: null, 'status' => $existing?->status ?? 'published'],
        );

        return response()->json(['rating' => $review->rating, 'comment' => $review->comment, 'photos' => $review->photos ?? []], $existing ? 200 : 201);
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
