<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A customer's own details: profile, password and saved addresses. */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'nour.fawzy@demo.eg';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();
    }

    private function me(): static
    {
        return $this->actingAs(User::where('email', self::EMAIL)->firstOrFail(), 'sanctum');
    }

    private array $home = ['label' => 'Home', 'name' => 'Nour', 'phone' => '01012345678', 'line1' => '12 Test St', 'city' => 'Cairo'];

    public function test_the_profile_is_edited_and_the_phone_is_checked_for_the_country(): void
    {
        $this->me()->putJson('/api/me/profile', ['name' => 'Nour <b>El-Din</b>', 'phone' => '01012345678'])->assertOk()->assertJsonPath('name', 'Nour El-Din')->assertJsonPath('phone', '+201012345678');
        $this->putJson('/api/me/profile', ['phone' => '12'])->assertUnprocessable();
        $this->putJson('/api/me/profile', ['phone' => ''])->assertOk()->assertJsonPath('phone', null);
        $this->putJson('/api/me/profile', ['name' => ''])->assertUnprocessable();
        $this->putJson('/api/me/profile', [])->assertOk();
    }

    public function test_the_password_changes_only_with_the_current_one_and_signs_out_other_devices(): void
    {
        $user = User::where('email', self::EMAIL)->firstOrFail();
        $user->forceFill(['password' => 'password123'])->save();
        $other = $user->createToken('phone')->plainTextToken;

        $this->actingAs($user, 'sanctum')->putJson('/api/me/password', ['current_password' => 'wrong', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123'])->assertUnprocessable();
        $this->putJson('/api/me/password', ['current_password' => 'password123', 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable();
        $this->putJson('/api/me/password', ['current_password' => 'password123', 'password' => 'Newpass123', 'password_confirmation' => 'different1'])->assertUnprocessable();
        $this->putJson('/api/me/password', ['current_password' => 'password123', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123'])->assertOk();

        $this->assertTrue(Hash::check('Newpass123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());                                                   // the other device was signed out
        $this->assertNotNull($other);
    }

    public function test_addresses_are_kept_one_default_at_a_time_and_belong_to_their_owner(): void
    {
        $id = $this->me()->postJson('/api/addresses', $this->home)->assertCreated()->assertJsonPath('is_default', true)->json('id');
        $work = $this->postJson('/api/addresses', ['label' => 'Work', 'name' => 'Nour', 'phone' => '01012345678', 'line1' => '1 Office Rd', 'city' => 'Giza', 'is_default' => true])->assertCreated()->assertJsonPath('is_default', true)->json('id');

        $list = $this->getJson('/api/addresses')->assertOk()->json();
        $this->assertSame([$work, $id], array_column($list, 'id'));                                      // the default comes first
        $this->assertSame([true, false], array_column($list, 'is_default'));

        $this->putJson("/api/addresses/{$id}", ['city' => 'Maadi', 'is_default' => true])->assertOk()->assertJsonPath('city', 'Maadi');
        $this->assertFalse(CustomerAddress::find($work)->is_default);
        $this->postJson('/api/addresses', ['name' => 'x'])->assertUnprocessable();

        $stranger = User::where('email', 'seller.khan@demo.eg')->firstOrFail();
        $this->actingAs($stranger, 'sanctum')->putJson("/api/addresses/{$id}", ['city' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/addresses/{$id}")->assertNotFound();
        $this->getJson('/api/addresses')->assertOk()->assertJsonCount(0);

        $this->me()->deleteJson("/api/addresses/{$id}")->assertOk();
        $this->assertTrue(CustomerAddress::find($work)->fresh()->is_default);                             // another one becomes the default
    }

    public function test_favourites_are_saved_listed_and_removed_per_person(): void
    {
        $this->getJson('/api/favourites')->assertUnauthorized();
        $product = \App\Models\Product::withoutGlobalScopes()->where('market', 'EG')->where('is_active', true)->whereNull('seller_id')->orWhereNotNull('seller_id')->firstOrFail();

        $this->me()->postJson('/api/favourites', ['product_id' => $product->id])->assertCreated();
        $this->postJson('/api/favourites', ['product_id' => $product->id])->assertCreated();                 // saving twice keeps one
        $this->getJson('/api/favourites/ids')->assertOk()->assertExactJson([$product->id]);
        $this->getJson('/api/favourites')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $product->id);
        $this->postJson('/api/favourites', ['product_id' => 99999999])->assertUnprocessable();

        $stranger = User::where('email', 'seller.khan@demo.eg')->firstOrFail();
        $this->actingAs($stranger, 'sanctum')->getJson('/api/favourites/ids')->assertOk()->assertExactJson([]);   // not shared

        $this->me()->deleteJson("/api/favourites/{$product->id}")->assertOk();
        $this->getJson('/api/favourites')->assertOk()->assertJsonCount(0);
    }

    public function test_the_car_fit_of_a_product_is_told_per_car(): void
    {
        $product = \App\Models\Product::withoutGlobalScopes()->where('is_active', true)->firstOrFail();
        $this->me()->getJson("/api/garage/fit/{$product->id}")->assertOk()->assertJsonCount(0);             // no cars yet
        $model = \App\Modules\Automotive\Models\VehicleModel::where('is_active', true)->firstOrFail();
        $this->postJson('/api/garage', ['vehicle_model_id' => $model->id, 'year' => 2020])->assertCreated();
        $this->getJson("/api/garage/fit/{$product->id}")->assertOk()->assertJsonCount(1)->assertJsonPath('0.status', 'universal');
    }

    public function test_at_most_ten_addresses_and_only_signed_in_people(): void
    {
        $this->getJson('/api/addresses')->assertUnauthorized();
        $this->me();
        foreach (range(1, 10) as $i) {
            $this->postJson('/api/addresses', ['name' => "N{$i}"] + $this->home)->assertCreated();
        }
        $this->postJson('/api/addresses', $this->home)->assertUnprocessable();
    }
}
