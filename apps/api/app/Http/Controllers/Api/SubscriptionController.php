<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A partner's or seller's own plan, and the platform administrator's control of the plans themselves. */
class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subs)
    {
    }

    private function subject(Request $request): array
    {
        return $this->subs->subjectOf($request->user()) ?? abort(403, 'Plans are for partners and sellers.');
    }

    private function view(array $subject): array
    {
        [$type, $id] = $subject;
        $sub = $this->subs->subscriptionFor($type, $id);
        $plan = $this->subs->planFor($type, $id);
        $used = $this->subs->customersThisMonth($type, $id);

        return [
            'plan' => $plan,
            'usage' => ['customers' => $used, 'limit' => $plan->customer_limit, 'reached' => $plan->customer_limit !== null && $used >= $plan->customer_limit],
            'period_end' => $sub->period_end?->toDateString(),
            'auto_renew' => $sub->auto_renew,
            'plans' => $this->subs->plans()->where('is_active', true)->values(),
            'invoices' => $sub->invoices()->limit(6)->get(),
        ];
    }

    public function show(Request $request)
    {
        return response()->json($this->view($this->subject($request)));
    }

    public function choose(Request $request)
    {
        $data = $request->validate(['plan' => 'required|string|max:20']);
        $subject = $this->subject($request);
        $this->subs->choose($subject[0], $subject[1], $data['plan']);
        Audit::record('subscription.changed', null, ['subject' => $subject, 'plan' => $data['plan']], $request->user());

        return response()->json($this->view($subject));
    }

    public function cancel(Request $request)
    {
        $subject = $this->subject($request);
        $this->subs->cancel($subject[0], $subject[1]);

        return response()->json($this->view($subject));
    }

    // ---- administration -------------------------------------------------

    public function plans()
    {
        return response()->json($this->subs->plans());
    }

    public function update(Request $request, int $id)
    {
        abort_unless($request->user()->hasRole('platform-admin'), 403);

        $plan = SubscriptionPlan::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:60',
            'price_monthly' => 'sometimes|numeric|min:0|max:1000000',
            'customer_limit' => 'sometimes|nullable|integer|min:1|max:1000000',
            'commission_percentage' => 'sometimes|numeric|min:0|max:100',
            'team_seats' => 'sometimes|integer|min:1|max:1000',
            'is_active' => ['sometimes', 'boolean', Rule::when($plan->key === 'free', ['accepted'])],
        ]);
        $plan->update($data);
        Audit::record('subscription_plan.updated', $plan, $data, $request->user());

        return response()->json($plan->fresh());
    }
}
