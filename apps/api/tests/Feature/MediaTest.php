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
}
