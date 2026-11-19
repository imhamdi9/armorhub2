<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The customer's address book. */
class AddressController extends Controller
{
    private const MAX = 10;

    public function index(Request $request)
    {
        return CustomerAddress::where('user_id', $request->user()->id)->orderByDesc('is_default')->orderBy('id')->get()->map(fn ($a) => $this->present($a))->values();
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (CustomerAddress::where('user_id', $user->id)->count() >= self::MAX) {
            throw ValidationException::withMessages(['address' => [__('You can keep up to :n addresses. Remove one first.', ['n' => self::MAX])]]);
        }
        $data = $this->validated($request);
        $first = ! CustomerAddress::where('user_id', $user->id)->exists();
        $address = CustomerAddress::create($data + ['user_id' => $user->id, 'is_default' => $first || $request->boolean('is_default')]);
        $this->single($address);

        return response()->json($this->present($address->fresh()), 201);
    }

    public function update(Request $request, int $id)
    {
        $address = CustomerAddress::where('user_id', $request->user()->id)->findOrFail($id);
        $address->update($this->validated($request, true) + ($request->has('is_default') ? ['is_default' => $request->boolean('is_default')] : []));
        $this->single($address);

        return $this->present($address->fresh());
    }

    public function destroy(Request $request, int $id)
    {
        $address = CustomerAddress::where('user_id', $request->user()->id)->findOrFail($id);
        $was = $address->is_default;
        $address->delete();
        if ($was) {
            CustomerAddress::where('user_id', $request->user()->id)->orderBy('id')->first()?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Removed']);
    }

    /** Only one address is the default. */
    private function single(CustomerAddress $address): void
    {
        if ($address->is_default) {
            CustomerAddress::where('user_id', $address->user_id)->where('id', '!=', $address->id)->update(['is_default' => false]);
        }
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $s = $partial ? 'sometimes|' : '';
        $data = $request->validate([
            'label' => 'nullable|string|max:40', 'name' => $s.'required|string|max:120', 'phone' => $s.'required|string|max:30', 'line1' => $s.'required|string|max:200',
            'line2' => 'nullable|string|max:200', 'city' => $s.'required|string|max:100', 'region' => 'nullable|string|max:100', 'postal_code' => 'nullable|string|max:20',
        ]);

        return array_map(fn ($v) => is_string($v) ? trim(strip_tags($v)) : $v, $data);
    }

    /** @return array<string, mixed> */
    private function present(CustomerAddress $a): array
    {
        return $a->only(['id', 'label', 'name', 'phone', 'line1', 'line2', 'city', 'region', 'postal_code', 'is_default']);
    }
}
