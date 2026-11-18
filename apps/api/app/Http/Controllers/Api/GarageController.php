<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerVehicle;
use App\Modules\Automotive\Models\VehicleModel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The customer's cars. */
class GarageController extends Controller
{
    private const MAX_VEHICLES = 10;

    public function index(Request $request)
    {
        return CustomerVehicle::where('customer_id', $request->user()->id)->with('model.make')->orderByDesc('is_default')->orderBy('id')->get()->map(fn ($v) => $this->present($v))->values();
    }

    /** Whether one product fits each of the customer's cars, so the product page can say so. */
    public function fit(Request $request, int $productId, \App\Services\Fitment\FitmentService $fitment)
    {
        $product = \App\Models\Product::withoutGlobalScopes()->where('is_active', true)->findOrFail($productId);

        return CustomerVehicle::where('customer_id', $request->user()->id)->with('model.make')->orderByDesc('is_default')->orderBy('id')->get()->map(fn ($v) => [
            'vehicle_id' => $v->id, 'label' => $v->nickname ?: $v->label(), 'is_default' => $v->is_default, 'status' => $fitment->checkVehicle($product, $v),
        ])->values();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = $request->user();

        if (CustomerVehicle::where('customer_id', $user->id)->count() >= self::MAX_VEHICLES) {
            throw ValidationException::withMessages(['vehicle' => [__('You can keep up to :n cars. Remove one first.', ['n' => self::MAX_VEHICLES])]]);
        }

        $first = ! CustomerVehicle::where('customer_id', $user->id)->exists();
        $vehicle = CustomerVehicle::create($data + ['customer_id' => $user->id, 'is_default' => $first]);

        return response()->json($this->present($vehicle->load('model.make')), 201);
    }

    public function update(Request $request, int $id)
    {
        $vehicle = CustomerVehicle::where('customer_id', $request->user()->id)->findOrFail($id);
        $vehicle->update($this->validated($request, $vehicle));

        if ($request->boolean('is_default')) {
            CustomerVehicle::where('customer_id', $vehicle->customer_id)->where('id', '!=', $vehicle->id)->update(['is_default' => false]);
            $vehicle->update(['is_default' => true]);
        }

        return $this->present($vehicle->fresh()->load('model.make'));
    }

    public function destroy(Request $request, int $id)
    {
        $vehicle = CustomerVehicle::where('customer_id', $request->user()->id)->findOrFail($id);
        $wasDefault = $vehicle->is_default;
        $vehicle->delete();

        if ($wasDefault) {
            CustomerVehicle::where('customer_id', $request->user()->id)->orderBy('id')->first()?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Removed']);
    }

    private function validated(Request $request, ?CustomerVehicle $vehicle = null): array
    {
        $sometimes = $vehicle ? 'sometimes|' : '';
        $data = $request->validate([
            'vehicle_model_id' => $sometimes.'required|integer|exists:vehicle_models,id',
            'year' => $sometimes.'required|integer|min:1980|max:'.(now()->year + 1),
            'nickname' => 'nullable|string|max:60',
            // A VIN is 17 letters and digits, never I, O or Q.
            'vin' => ['nullable', 'string', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'],
            'color' => 'nullable|string|max:30',
        ], ['vin.regex' => 'A VIN is 17 letters and digits (no I, O or Q).']);

        $modelId = $data['vehicle_model_id'] ?? $vehicle?->vehicle_model_id;
        if ($modelId && ! VehicleModel::whereKey($modelId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['vehicle_model_id' => ['This model is not available.']]);
        }
        if (isset($data['vin'])) {
            $data['vin'] = strtoupper($data['vin']);
        }
        foreach (['nickname', 'color'] as $text) {
            if (isset($data[$text])) {
                $data[$text] = trim(strip_tags($data[$text]));
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function present(CustomerVehicle $v): array
    {
        return ['id' => $v->id, 'vehicle_model_id' => $v->vehicle_model_id, 'make' => $v->model?->make?->name, 'model' => $v->model?->name, 'year' => $v->year, 'label' => $v->label(),
            'nickname' => $v->nickname, 'color' => $v->color, 'vin' => $v->maskedVin(), 'is_default' => $v->is_default];
    }
}
