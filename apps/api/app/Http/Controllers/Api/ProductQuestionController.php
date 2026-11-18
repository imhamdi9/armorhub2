<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\User;
use App\Notifications\SimpleNotice;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Questions about a product: any signed-in shopper may ask (a few an hour), the selling company or a platform administrator answers,
 * and only answered questions appear on the product page. The asker is told when it is answered.
 */
class ProductQuestionController extends Controller
{
    public const MAX_PER_HOUR = 5;

    public function ask(Request $request, int $id)
    {
        $product = Product::withoutGlobalScopes()->where('is_active', true)->findOrFail($id);
        $data = $request->validate(['question' => 'required|string|min:8|max:300']);
        $user = $request->user();

        if (ProductQuestion::where('user_id', $user->id)->where('created_at', '>=', now()->subHour())->count() >= self::MAX_PER_HOUR) {
            return response()->json(['message' => 'You have asked a lot of questions. Please try again later.'], 429);
        }

        $question = ProductQuestion::create(['product_id' => $product->id, 'user_id' => $user->id, 'question' => trim(strip_tags($data['question']))]);

        // The selling company is told; a question about a shared-catalog product waits in the administrators' list.
        if ($product->seller_id) {
            User::where('company_id', $product->seller_id)->where('is_active', true)->get()->filter(fn (User $u) => $u->can('products.update'))
                ->each(fn (User $u) => $u->notify(new SimpleNotice('A customer asked about :product', $question->question, '/dashboard/questions', ['product' => $product->name])));
        }

        return response()->json($this->row($question), 201);
    }

    /** What this shopper asked about the product, answered or not. */
    public function mine(Request $request, int $id)
    {
        return ProductQuestion::where('product_id', $id)->where('user_id', $request->user()->id)->latest()->get()->map(fn ($q) => $this->row($q))->values();
    }

    /** The questions the signed-in person may answer: their company's products (all of them for an administrator), unanswered first. */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasRole('platform-admin') || ($user->company_id && $user->can('products.update')), 403);

        return ProductQuestion::query()->with(['product:id,name,seller_id', 'user:id,name'])
            ->when(! $user->hasRole('platform-admin'), fn ($q) => $q->whereIn('product_id', Product::withoutGlobalScopes()->where('seller_id', $user->company_id)->select('id')))
            ->orderByRaw('answered_at is not null')->latest('id')->limit(100)->get()
            ->map(fn ($q) => $this->row($q) + ['product' => ['id' => $q->product?->id, 'name' => $q->product?->name], 'asker' => explode(' ', trim((string) $q->user?->name))[0] ?: 'Customer'])->values();
    }

    public function answer(Request $request, int $id)
    {
        $question = ProductQuestion::with('product')->findOrFail($id);
        $user = $request->user();
        $product = $question->product;
        $mine = $user->hasRole('platform-admin') || ($product?->seller_id && $user->company_id === $product->seller_id && $user->can('products.update'));
        abort_unless($mine, 404);

        $data = $request->validate(['answer' => 'required|string|min:2|max:1000']);
        $question->update(['answer' => trim(strip_tags($data['answer'])), 'answered_by' => $user->id, 'answered_at' => now()]);

        $asker = $question->user;
        $store = $asker?->company_id ? \App\Models\Company::withoutGlobalScopes()->find($asker->company_id) : null;
        if ($asker && $store?->slug) {
            $asker->notify(new SimpleNotice('Your question was answered', ($product?->name ?? 'A product').': '.$question->answer, "/s/{$store->slug}/products/{$question->product_id}"));
        }

        return $this->row($question->fresh());
    }

    /** Hide or show a question. Platform administrators only. */
    public function moderate(Request $request, int $id)
    {
        abort_unless($request->user()->hasRole('platform-admin'), 403);
        $data = $request->validate(['status' => 'required|in:published,hidden']);
        $question = ProductQuestion::findOrFail($id);
        $question->update(['status' => $data['status']]);
        Audit::record('product_question.'.$data['status'], $question, ['product_id' => $question->product_id], $request->user());

        return response()->json(['status' => $question->status]);
    }

    /** @return array<string, mixed> */
    private function row(ProductQuestion $q): array
    {
        return ['id' => $q->id, 'question' => $q->question, 'answer' => $q->answer, 'answered_at' => $q->answered_at?->toDateString(), 'status' => $q->status, 'asked_at' => $q->created_at?->toDateString()];
    }
}
