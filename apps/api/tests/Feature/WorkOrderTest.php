<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A paid installation job as a work order: given to a technician, with the materials used. */
class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER = 'nour.fawzy@demo.eg';

    private User $tech;
    private User $otherTech;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();

        $mine = $this->centre('Nasr City Auto Film');
        $theirs = $this->centre('Zamalek Detailing Studio');
        $make = function (Partner $p, string $email) {
            $u = User::factory()->create(['email' => $email, 'partner_id' => $p->id, 'company_id' => $p->company_id]);
            $u->assignRole('technician');

            return $u;
        };
        $this->tech = $make($mine, 'tech.mine@demo.eg');
        $this->otherTech = $make($theirs, 'tech.theirs@demo.eg');
    }

    private function centre(string $name): Partner
    {
        return Partner::withoutGlobalScopes()->where('name', $name)->firstOrFail();
    }

    private function managerEmail(string $name = 'Nasr City Auto Film'): string
    {
        return User::where('partner_id', $this->centre($name)->id)->role('partner-manager')->value('email');
    }

    private function as(string $email): static
    {
        return $this->actingAs(User::where('email', $email)->firstOrFail(), 'sanctum');
    }

    private function paidJob(): int
    {
        $this->as(self::CUSTOMER);
        $id = $this->postJson('/api/installation-requests', ['industry_id' => (int) \App\Models\Industry::where('key', 'automotive')->value('id'), 'subject' => 'Toyota Camry 2020', 'parts' => ['Hood'],
            'latitude' => 30.0626, 'longitude' => 31.2197, 'location_consent' => true])->assertCreated()->json('id');
        $this->as($this->managerEmail())->postJson("/api/partner/installation-requests/{$id}/offer", ['price' => 2000, 'eta_days' => 1])->assertCreated();
        $offer = $this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}")->json('offers.0.id');
        $this->postJson("/api/installation-requests/{$id}/accept", ['offer_id' => $offer, 'accept_terms' => true])->assertOk();
        $this->postJson("/api/installation-requests/{$id}/pay")->assertOk();

        return $id;
    }

    public function test_the_manager_gives_a_job_to_one_of_its_own_technicians(): void
    {
        $id = $this->paidJob();
        $this->as($this->managerEmail());

        $list = $this->getJson('/api/partner/technicians')->assertOk()->json();
        $this->assertContains($this->tech->id, array_column($list, 'id'));
        $this->assertNotContains($this->otherTech->id, array_column($list, 'id'));            // only this centre's own

        $this->postJson("/api/partner/installation-jobs/{$id}/assign", ['technician_id' => $this->tech->id])->assertOk()->assertJsonPath('technician.id', $this->tech->id);
        $this->assertSame(1, collect($this->getJson('/api/partner/technicians')->json())->firstWhere('id', $this->tech->id)['open_jobs']);
        $this->assertSame(1, $this->tech->notifications()->count());                          // told about it

        // Another centre's technician, a customer, or nobody-at-all are refused; clearing the assignment works.
        $this->postJson("/api/partner/installation-jobs/{$id}/assign", ['technician_id' => $this->otherTech->id])->assertUnprocessable();
        $this->postJson("/api/partner/installation-jobs/{$id}/assign", ['technician_id' => User::where('email', self::CUSTOMER)->value('id')])->assertUnprocessable();
        $this->postJson("/api/partner/installation-jobs/{$id}/assign", ['technician_id' => null])->assertOk()->assertJsonPath('technician', null);
    }

    public function test_a_technician_sees_and_works_only_the_jobs_given_to_them(): void
    {
        $given = $this->paidJob();
        $other = $this->paidJob();
        $this->as($this->managerEmail())->postJson("/api/partner/installation-jobs/{$given}/assign", ['technician_id' => $this->tech->id])->assertOk();

        $this->actingAs($this->tech, 'sanctum');
        $this->assertSame([$given], array_column($this->getJson('/api/partner/installation-jobs')->assertOk()->json(), 'id'));
        $this->getJson("/api/partner/installation-jobs/{$given}")->assertOk();
        $this->getJson("/api/partner/installation-jobs/{$other}")->assertNotFound();
        $this->postJson("/api/partner/installation-jobs/{$other}/start")->assertNotFound();
        $this->getJson("/api/partner/installation-jobs/{$other}/work-order")->assertNotFound();

        // Giving out jobs is the manager's job, and a technician of another centre reaches nothing here.
        $this->postJson("/api/partner/installation-jobs/{$other}/assign", ['technician_id' => $this->tech->id])->assertForbidden();
        $this->actingAs($this->otherTech, 'sanctum')->getJson("/api/partner/installation-jobs/{$given}/work-order")->assertNotFound();
        $this->as(self::CUSTOMER)->getJson("/api/partner/installation-jobs/{$given}/work-order")->assertForbidden();

        // The manager still sees both.
        $this->as($this->managerEmail());
        $this->assertEqualsCanonicalizing([$given, $other], array_column($this->getJson('/api/partner/installation-jobs')->json(), 'id'));
    }

    public function test_materials_are_recorded_until_the_proof_is_sent(): void
    {
        $id = $this->paidJob();
        $this->as($this->managerEmail())->postJson("/api/partner/installation-jobs/{$id}/assign", ['technician_id' => $this->tech->id])->assertOk();
        $this->actingAs($this->tech, 'sanctum');

        $order = $this->postJson("/api/partner/installation-jobs/{$id}/materials", ['name' => '<b>XPEL Ultimate Plus</b> film', 'quantity' => 2.5, 'unit' => 'm', 'batch' => 'ROLL-8841'])->assertCreated()->json();
        $this->assertSame('XPEL Ultimate Plus film', $order['materials'][0]['name']);        // markup stripped
        $this->assertSame(['m', 'ROLL-8841', 2.5], [$order['materials'][0]['unit'], $order['materials'][0]['batch'], $order['materials'][0]['quantity']]);

        foreach ([['name' => '', 'quantity' => 1], ['name' => 'Film', 'quantity' => 0], ['name' => 'Film', 'quantity' => -3], ['name' => 'Call me on 01012345678', 'quantity' => 1]] as $bad) {
            $this->postJson("/api/partner/installation-jobs/{$id}/materials", $bad)->assertUnprocessable();
        }

        $material = $order['materials'][0]['id'];
        $this->deleteJson("/api/partner/installation-jobs/{$id}/materials/{$material}")->assertOk()->assertJsonCount(0, 'materials');

        // After the proof the record is closed.
        $this->postJson("/api/partner/installation-jobs/{$id}/materials", ['name' => 'Film', 'quantity' => 1])->assertCreated();
        $this->postJson("/api/partner/installation-jobs/{$id}/start")->assertOk();
        $this->postJson("/api/partner/installation-jobs/{$id}/proof", ['before' => [UploadedFile::fake()->image('b.jpg')], 'after' => [UploadedFile::fake()->image('a.jpg')], 'serial' => 'SN-1'])->assertOk()->assertJsonPath('status', 'proof_submitted');
        $this->postJson("/api/partner/installation-jobs/{$id}/materials", ['name' => 'More film', 'quantity' => 1])->assertUnprocessable();
        $this->assertFalse($this->getJson("/api/partner/installation-jobs/{$id}/work-order")->json('can_edit_materials'));
    }

    public function test_the_work_order_gathers_who_what_when_and_proof(): void
    {
        $id = $this->paidJob();
        $this->as($this->managerEmail())->postJson("/api/partner/installation-jobs/{$id}/assign", ['technician_id' => $this->tech->id])->assertOk();
        $this->postJson("/api/partner/installation-jobs/{$id}/materials", ['name' => 'Film', 'quantity' => 3, 'unit' => 'm'])->assertCreated();

        $wo = $this->getJson("/api/partner/installation-jobs/{$id}/work-order")->assertOk()->json();
        $this->assertSame('Toyota Camry 2020', $wo['subject']);
        $this->assertSame($this->tech->name, $wo['technician']['name']);
        $this->assertCount(1, $wo['materials']);
        $this->assertSame(['before' => 0, 'after' => 0, 'serial' => null], $wo['proof']);
        $this->assertArrayNotHasKey('customer', $wo);                                      // who the customer is stays out of it
        $this->assertArrayNotHasKey('latitude', $wo);
    }
}
