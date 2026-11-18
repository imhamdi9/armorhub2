<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use App\Services\InstallationNotifier;
use App\Support\ContactGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Talking about an order without leaving the platform: the customer and the seller write to each other, and ArmorHub can join. Phone numbers, e-mails
 * and links are refused (everyone stays reachable through the platform), and the customer can flag a message as a problem for ArmorHub to look at.
 */
class OrderMessageController extends Controller
{
    public function __construct(private InstallationNotifier $notify)
    {
    }

    public function index(Request $request, int $id)
    {
        [$order] = $this->access($request, $id);

        return OrderMessage::where('order_id', $order->id)->orderBy('id')->limit(300)->get()->map(fn ($m) => $this->present($m, $request->user()))->values();
    }

    public function store(Request $request, int $id)
    {
        [$order, $side] = $this->access($request, $id);
        $data = $request->validate(['body' => 'required|string|min:2|max:1000', 'problem' => 'sometimes|boolean']);
        $body = trim(strip_tags($data['body']));

        if ($side !== 'support' && ContactGuard::contains($body)) {
            throw ValidationException::withMessages(['body' => ['Please do not share phone numbers, e-mails or links. Everyone is reachable here on the platform.']]);
        }
        $problem = $side === 'customer' && $request->boolean('problem');
        $message = OrderMessage::create(['order_id' => $order->id, 'user_id' => $request->user()->id, 'side' => $side, 'kind' => $problem ? 'problem' : 'message', 'body' => $body]);

        $path = $side === 'seller' ? "/dashboard/seller-orders" : "/dashboard/orders/{$order->id}";
        $title = $problem ? "A problem with order #{$order->id}" : "New message about order #{$order->id}";
        if ($side !== 'customer') {
            $this->notify->user($order->customer_id, $title, mb_substr($body, 0, 120), "/dashboard/orders/{$order->id}");
        }
        if ($side !== 'seller') {
            foreach ($this->sellerUsers($order) as $u) {
                $this->notify->user($u->id, $title, mb_substr($body, 0, 120), '/dashboard/seller-orders');
            }
        }
        if ($problem) {
            $this->notify->admins($title, mb_substr($body, 0, 120), '/dashboard/admin/tools');
        }

        return response()->json($this->present($message, $request->user()), 201);
    }

    /** @return array{0: Order, 1: string} the order and which side the signed-in person is on; anyone else gets a 404 */
    private function access(Request $request, int $id): array
    {
        $user = $request->user();
        $order = Order::withoutGlobalScopes()->with('product:id,seller_id')->findOrFail($id);

        if ($order->customer_id !== null && $order->customer_id === $user->id) {
            return [$order, 'customer'];
        }
        if ($user->hasRole('platform-admin')) {
            return [$order, 'support'];
        }
        if ($user->hasRole('seller') && $user->company_id && (int) $order->product?->seller_id === (int) $user->company_id) {
            return [$order, 'seller'];
        }

        abort(404);
    }

    private function sellerUsers(Order $order)
    {
        $seller = $order->product?->seller_id;

        return $seller ? User::withoutGlobalScopes()->where('company_id', $seller)->role('seller')->get() : collect();
    }

    /** @return array<string, mixed> */
    private function present(OrderMessage $m, User $viewer): array
    {
        $name = $m->side === 'support' ? 'ArmorHub' : ($m->side === 'seller' ? null : null);

        return ['id' => $m->id, 'side' => $m->side, 'mine' => $m->user_id === $viewer->id, 'kind' => $m->kind, 'body' => $m->body, 'name' => $name, 'handled' => $m->handled_at !== null, 'created_at' => $m->created_at?->toIso8601String()];
    }
}
