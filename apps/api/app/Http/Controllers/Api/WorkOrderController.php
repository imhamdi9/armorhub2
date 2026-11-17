<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationBooking;
use App\Models\InstallationMaterial;
use App\Models\InstallationOffer;
use App\Models\InstallationRequest;
use App\Models\InstallationProof;
use App\Models\User;
use App\Services\InstallationNotifier;
use App\Support\ContactGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The work order of an installation job: who does it, what is used and where it stands. The centre's manager gives the job to one of its
 * technicians; a technician sees and works only the jobs given to them.
 */
class WorkOrderController extends Controller
{
    private const EDITABLE = ['paid', 'in_progress'];     // materials can be changed until the proof is sent

    public function __construct(private InstallationNotifier $notify)
    {
    }

    private function partnerId(Request $request): int
    {
        abort_unless($request->user()->partner_id, 422, 'Your account is not linked to a partner yet.');

        return (int) $request->user()->partner_id;
    }

    /** A manager reaches every job of the centre; a technician only the ones given to them. */
    private function job(Request $request, int $id): InstallationRequest
    {
        $offers = InstallationOffer::where('partner_id', $this->partnerId($request))->pluck('id');
        $job = InstallationRequest::whereIn('selected_offer_id', $offers)->findOrFail($id);

        $user = $request->user();
        if (! $user->hasRole('partner-manager') && (int) $job->technician_id !== $user->id) {
            abort(404);
        }

        return $job;
    }

    public function show(Request $request, int $id)
    {
        return $this->present($this->job($request, $id));
    }

    /** The centre's technicians, for the manager to choose from. */
    public function technicians(Request $request)
    {
        return User::where('partner_id', $this->partnerId($request))->role('technician')->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'open_jobs' => InstallationRequest::where('technician_id', $u->id)->whereIn('status', self::EDITABLE)->count()])->values();
    }

    public function assign(Request $request, int $id)
    {
        $job = $this->job($request, $id);
        abort_unless($request->user()->hasRole('partner-manager'), 403);
        $data = $request->validate(['technician_id' => 'nullable|integer']);

        if (! in_array($job->status, self::EDITABLE, true)) {
            throw ValidationException::withMessages(['technician_id' => ['A job can be given to a technician once it is paid and until it is finished.']]);
        }

        $tech = null;
        if (! empty($data['technician_id'])) {
            $tech = User::where('partner_id', $this->partnerId($request))->role('technician')->where('is_active', true)->find($data['technician_id'])
                ?? throw ValidationException::withMessages(['technician_id' => ['Choose one of your own technicians.']]);
        }

        $job->update(['technician_id' => $tech?->id, 'assigned_at' => $tech ? now() : null]);
        if ($tech) {
            $this->notify->user($tech->id, 'A job was given to you', "{$job->subject}: {$this->parts($job)}", "/dashboard/centre-jobs/{$job->id}");
        }

        return $this->present($job->fresh());
    }

    public function addMaterial(Request $request, int $id)
    {
        $job = $this->job($request, $id);
        $this->assertEditable($job);
        $data = $request->validate([
            'name' => 'required|string|max:160', 'quantity' => 'required|numeric|min:0.01|max:100000', 'unit' => 'nullable|string|max:20',
            'batch' => 'nullable|string|max:80', 'product_id' => 'nullable|integer|exists:products,id',
        ]);
        foreach ([$data['name'], $data['batch'] ?? ''] as $text) {
            if (ContactGuard::contains($text)) {
                throw ValidationException::withMessages(['name' => ['Please do not put contact details here.']]);
            }
        }

        InstallationMaterial::create(['request_id' => $job->id, 'product_id' => $data['product_id'] ?? null, 'name' => trim(strip_tags($data['name'])), 'quantity' => $data['quantity'],
            'unit' => trim(strip_tags($data['unit'] ?? 'pcs')) ?: 'pcs', 'batch' => isset($data['batch']) ? trim(strip_tags($data['batch'])) : null, 'recorded_by' => $request->user()->id]);

        return response()->json($this->present($job), 201);
    }

    public function removeMaterial(Request $request, int $id, int $materialId)
    {
        $job = $this->job($request, $id);
        $this->assertEditable($job);
        InstallationMaterial::where('request_id', $job->id)->whereKey($materialId)->delete();

        return $this->present($job);
    }

    private function assertEditable(InstallationRequest $job): void
    {
        if (! in_array($job->status, self::EDITABLE, true)) {
            throw ValidationException::withMessages(['materials' => ['The materials can be changed until the proof of work is sent.']]);
        }
    }

    private function parts(InstallationRequest $job): string
    {
        return implode(', ', (array) $job->parts);
    }

    /** @return array<string, mixed> */
    private function present(InstallationRequest $job): array
    {
        $booking = InstallationBooking::where('request_id', $job->id)->where('status', 'booked')->first();
        $tech = $job->technician_id ? User::whereKey($job->technician_id)->first(['id', 'name']) : null;
        $proof = InstallationProof::where('request_id', $job->id)->get();

        return [
            'job_id' => $job->id, 'status' => $job->status, 'subject' => $job->subject, 'work_type' => $job->work_type, 'parts' => $job->parts, 'notes' => $job->notes,
            'technician' => $tech ? ['id' => $tech->id, 'name' => $tech->name] : null, 'assigned_at' => $job->assigned_at ? \Carbon\Carbon::parse($job->assigned_at)->toIso8601String() : null,
            'booking' => $booking ? ['starts_at' => $booking->starts_at->toIso8601String(), 'ends_at' => $booking->ends_at->toIso8601String()] : null,
            'started_at' => $job->started_at ? \Carbon\Carbon::parse($job->started_at)->toIso8601String() : null,
            'materials' => InstallationMaterial::where('request_id', $job->id)->orderBy('id')->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'quantity' => (float) $m->quantity, 'unit' => $m->unit, 'batch' => $m->batch])->values(),
            'proof' => ['before' => $proof->where('kind', 'before')->count(), 'after' => $proof->where('kind', 'after')->count(), 'serial' => $proof->where('kind', 'serial')->first()?->text],
            'can_edit_materials' => in_array($job->status, self::EDITABLE, true),
        ];
    }
}
