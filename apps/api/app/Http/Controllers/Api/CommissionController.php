<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    private const RELATIONS = ['order', 'distributions.company', 'distributions.partner'];

    public function index(Request $request)
    {
        return response()->json(
            $this->visible($request)->with(self::RELATIONS)->latest()->get()
        );
    }

    public function show(Request $request, $id)
    {
        return response()->json(
            $this->visible($request)->with(self::RELATIONS)->findOrFail($id)
        );
    }

    private function visible(Request $request)
    {
        $user = $request->user();

        return Commission::query()->when(
            ! $user->hasRole('platform-admin'),
            fn ($q) => $q->whereHas(
                'distributions',
                fn ($d) => $d->visibleTo($user)
            )
        );
    }
}
