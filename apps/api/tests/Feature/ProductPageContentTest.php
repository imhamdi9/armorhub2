<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The content a page layout asks for: stored cleanly, shown on the public page, and impossible to abuse. */
class ProductPageContentTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Company $store;
    private Company $sellerCo;
    private User $seller;
    private User $rivalSeller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $this->store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);

        $make = function (string $name, string $email) {
            $co = Company::create(['tenant_id' => $this->store->tenant_id, 'name' => $name, 'type' => 'supplier', 'status' => 'active']);
            $u = User::factory()->create(['email' => $email, 'company_id' => $co->id]);
            $u->assignRole('seller');

            return [$co, $u];
        };
        [$this->sellerCo, $this->seller] = $make('Gulf Parts', 'gulf@parts.example.org');
        [, $this->rivalSeller] = $make('Rival Parts', 'rival@parts.example.org');
    }

    private function body(array $extra = []): array
    {
        return $extra + ['name' => 'Pads', 'slug' => 'pads-'.uniqid(), 'category' => 'spare-parts', 'base_price' => 100];
    }

    private function page(int $id): array
    {
        return $this->getJson("/api/public/storefronts/main-store/products/{$id}")->assertOk()->json('product');
    }

    public function test_the_content_is_saved_cleaned_and_shown_on_the_public_page(): void
    {
        $content = [
            'headline' => '<script>alert(1)</script>Built to last',
            'steps' => [['title' => 'Choose'], ['title' => 'Fit']],
            'faqs' => [['q' => 'Is it <b>safe</b>?', 'a' => 'Yes']],
            'specs' => [['label' => 'Size', 'value' => 'M']],
            'tiers' => [['name' => 'Basic', 'text' => 'Simple']],
            'free_delivery_from' => 250,
            'warranty_terms' => 'Covers defects',
            'lead_time' => '2 days',
            'quote_note' => 'Estimate only',
            'evil' => 'dropped',
        ];
        $res = $this->actingAs($this->admin, 'sanctum')->postJson('/api/products', $this->body(['page_template' => 'story', 'attributes' => ['highlights' => ['One'], 'page' => $content, 'junk' => ['x' => 1]]]))->assertCreated();
        $id = $res->json('id');

        $stored = Product::find($id)->attributes;
        $this->assertSame(['highlights', 'page'], array_keys($stored));
        $this->assertSame('alert(1)Built to last', $stored['page']['headline']);
        $this->assertSame('Is it safe?', $stored['page']['faqs'][0]['q']);
        $this->assertArrayNotHasKey('evil', $stored['page']);

        Product::whereKey($id)->update(['is_active' => true, 'requires_shipping' => true]);
        $page = $this->page($id);
        $this->assertSame('Choose', $page['page']['steps'][0]['title']);
        $this->assertSame(250, $page['page']['free_delivery_from']);
        $this->assertArrayNotHasKey('addon_ids', $page['page']);
    }

    public function test_sizes_and_shapes_are_limited(): void
    {
        $admin = $this->actingAs($this->admin, 'sanctum');
        foreach ([
            ['attributes' => ['page' => ['faqs' => array_fill(0, 9, ['q' => 'q', 'a' => 'a'])]]],
            ['attributes' => ['page' => ['faqs' => [['q' => str_repeat('q', 161), 'a' => 'a']]]]],
            ['attributes' => ['page' => ['steps' => [['title' => '']]]]],
            ['attributes' => ['page' => ['specs' => 'not a list']]],
            ['attributes' => ['page' => ['addon_ids' => [999999]]]],
            ['description' => str_repeat('d', 10001)],
            ['base_price' => '99999999999999'],
            ['warranty_years' => 5000],
            ['slug' => '../../etc'],
        ] as $bad) {
            $admin->postJson('/api/products', $this->body($bad))->assertUnprocessable();
        }
    }

    public function test_a_seller_fills_in_the_content_but_cannot_choose_the_layout_or_pick_a_rivals_product(): void
    {
        $mine = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', $this->body(['page_template' => 'immersive', 'attributes' => ['page' => ['headline' => 'Mine']]]))->assertCreated();
        $this->assertSame('classic', Product::find($mine->json('id'))->page_template ?? 'classic');

        $theirs = $this->actingAs($this->rivalSeller, 'sanctum')->postJson('/api/products', $this->body())->assertCreated()->json('id');
        $this->actingAs($this->seller, 'sanctum')->putJson('/api/products/'.$mine->json('id'), ['attributes' => ['page' => ['addon_ids' => [$theirs]]]])->assertUnprocessable();

        $own = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', $this->body())->assertCreated()->json('id');
        $this->actingAs($this->seller, 'sanctum')->putJson('/api/products/'.$mine->json('id'), ['attributes' => ['page' => ['addon_ids' => [$own]]]])->assertOk();
    }

    public function test_chosen_add_ons_show_only_while_they_are_really_for_sale(): void
    {
        $main = $this->actingAs($this->admin, 'sanctum')->postJson('/api/products', $this->body())->assertCreated()->json('id');
        $extra = $this->actingAs($this->admin, 'sanctum')->postJson('/api/products', $this->body(['name' => 'Extra']))->assertCreated()->json('id');
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/products/{$main}", ['attributes' => ['page' => ['addon_ids' => [$extra]]]])->assertOk();
        Product::whereKey([$main, $extra])->update(['is_active' => true]);
        \Illuminate\Support\Facades\Cache::flush();

        $names = fn () => array_column($this->page($main)['addons'] ?? [], 'name');
        $this->assertContains('Extra', $names());

        // Hidden from customers: it disappears from the page instead of leaking.
        Product::whereKey($extra)->update(['is_active' => false]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertNotContains('Extra', $names());
    }

    public function test_the_bulk_layout_change_is_for_administrators_and_known_layouts_only(): void
    {
        $this->actingAs($this->seller, 'sanctum')->postJson('/api/products/bulk-page-template', ['ids' => [$this->product->id], 'page_template' => 'story'])->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/products/bulk-page-template', ['ids' => [$this->product->id], 'page_template' => 'nope'])->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/products/bulk-page-template', ['ids' => [$this->product->id], 'page_template' => 'story'])->assertOk()->assertJsonPath('updated', 1);
    }
}
