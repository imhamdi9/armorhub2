<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->buildMarketplace();
    }

    private function realFile(string $bytes, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }

    public function test_only_admins_upload(): void
    {
        foreach ([$this->customer, $this->manager] as $nobody) {
            $this->actingAs($nobody, 'sanctum')->postJson('/api/admin/media', ['image' => $this->realFile($this->png(), 'a.png')])->assertForbidden();
        }
    }

    public function test_an_image_is_stored_served_publicly_and_usable_in_a_surface(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $path = $this->postJson('/api/admin/media', ['image' => $this->realFile($this->png(), 'my hero.png')])->assertCreated()->json('path');
        $this->assertMatchesRegularExpression('~^/media/[0-9a-f-]{36}\.png$~', $path);

        $served = $this->get('/api/public/'.ltrim($path, '/'))->assertOk();
        $this->assertSame('image/png', $served->headers->get('Content-Type'));
        $this->assertSame('nosniff', $served->headers->get('X-Content-Type-Options'));

        $this->putJson('/api/admin/surfaces/site.home', ['locale' => 'en', 'values' => ['hero_image_url' => $path]])->assertOk();
        $this->putJson('/api/admin/surfaces/site.home', ['locale' => 'en', 'values' => ['hero_image_url' => '/media/../../etc/passwd']])->assertStatus(422);
        $this->putJson('/api/admin/surfaces/site.home', ['locale' => 'en', 'values' => ['hero_image_url' => '/media/'.str_repeat('a', 36).'.svg']])->assertStatus(422);
    }

    public function test_dangerous_files_are_refused(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->postJson('/api/admin/media', ['image' => $this->realFile($svg, 'x.png')])->assertStatus(422);
        $this->postJson('/api/admin/media', ['image' => $this->realFile('<?php echo 1;', 'shell.php')])->assertStatus(422);
        $this->postJson('/api/admin/media', ['image' => $this->realFile($this->png().str_repeat('0', 4 * 1024 * 1024), 'big.png')])->assertStatus(422);

        $this->get('/api/public/media/not-a-real-name.png')->assertNotFound();
        $this->get('/api/public/media/'.str_repeat('a', 36).'.png')->assertNotFound();
    }

    public function test_sellers_and_owners_upload_product_photos_but_customers_cannot(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/product-images', ['image' => $this->realFile($this->png(), 'a.png')])->assertForbidden();
        $this->actingAs($this->manager, 'sanctum');
        $this->postJson('/api/product-images', ['image' => $this->realFile($this->png(), 'a.png')])->assertStatus(403);

        $this->actingAs($this->admin, 'sanctum');
        $this->postJson('/api/product-images', ['image' => $this->realFile('<svg xmlns="http://www.w3.org/2000/svg"/>', 'x.png')])->assertStatus(422);
        $path = $this->postJson('/api/product-images', ['image' => $this->realFile($this->png(), 'photo.png')])->assertCreated()->json('path');
        $this->postJson('/api/products', ['name' => 'Photo product', 'slug' => 'photo-product', 'base_price' => 5, 'images' => [$path]])->assertCreated();
    }

    public function test_the_bulk_import_attaches_photos_by_file_name(): void
    {
        $product = \App\Models\Product::create(['name' => 'Lamp', 'slug' => 'eg-lamp', 'base_price' => 10, 'is_active' => true]);
        $dir = sys_get_temp_dir().'/photos-'.uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/eg-lamp-2.png", $this->png());
        file_put_contents("{$dir}/eg-lamp-1.png", $this->png().'x');
        file_put_contents("{$dir}/unrelated.png", $this->png());

        $this->artisan('platform:import-photos', ['directory' => $dir, '--dry-run' => true])->assertSuccessful();
        $this->assertEmpty($product->fresh()->images);

        $this->artisan('platform:import-photos', ['directory' => $dir])->assertSuccessful();
        $images = $product->fresh()->images;
        $this->assertCount(2, $images);
        foreach ($images as $path) {
            $this->assertTrue(\App\Support\ImageRef::valid($path));
            $this->assertTrue(Storage::disk('local')->exists('media/'.basename($path)));
        }
    }
}
