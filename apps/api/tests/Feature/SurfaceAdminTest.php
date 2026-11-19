<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SurfaceAdminTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    private function edit(string $key, string $locale, array $values)
    {
        return $this->putJson("/api/admin/surfaces/{$key}", ['locale' => $locale, 'values' => $values]);
    }

    public function test_only_platform_admins_edit(): void
    {
        foreach ([$this->customer, $this->manager, $this->tech] as $nobody) {
            $this->actingAs($nobody, 'sanctum')->getJson('/api/admin/surfaces')->assertForbidden();
            $this->edit('site.home', 'en', ['hero_title' => 'Hacked'])->assertForbidden();
            $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en'])->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/surfaces')->assertUnauthorized();
    }

    public function test_the_admin_lists_what_is_editable_and_nothing_else(): void
    {
        $list = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/surfaces')->assertOk()->json('surfaces');
        $keys = array_column($list, 'key');

        $this->assertEqualsCanonicalizing(['site.brand', 'site.home', 'site.navigation', 'app.sidebar', 'app.messages'], $keys);
        $home = collect($list)->firstWhere('key', 'site.home');
        $this->assertSame('text', $home['fields']['hero_title']['type']);
        $this->assertSame('bool', $home['fields']['show_faq']['type']);

        // Security-sensitive areas are not surfaces at all.
        foreach (['payments.keys', 'security.settings', 'auth.roles'] as $locked) {
            $this->getJson("/api/admin/surfaces/{$locked}")->assertNotFound();
            $this->edit($locked, 'en', ['x' => 'y'])->assertNotFound();
        }
    }

    public function test_edit_preview_in_draft_then_publish_then_visitors_see_it(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $public = fn () => $this->getJson('/api/public/surfaces/site.home')->assertOk();

        $this->assertSame('', $public()->json('values.en.hero_title'));            // built-in default: the page uses its own wording

        $this->edit('site.home', 'en', ['hero_title' => 'Services you can trust', 'show_faq' => false, 'faq' => [['question' => 'Is it free?', 'answer' => 'To ask for quotes, yes.']]])->assertOk();
        $this->assertSame('', $public()->json('values.en.hero_title'));            // still a draft

        $work = $this->getJson('/api/admin/surfaces/site.home?locale=en')->assertOk();
        $this->assertSame('Services you can trust', $work->json('draft.hero_title'));
        $this->assertTrue($work->json('has_draft'));

        $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en', 'note' => 'Launch wording'])->assertOk()->assertJsonPath('version', 1);

        $live = $public();
        $live->assertHeader('Cache-Control', 'max-age=30, public, s-maxage=30');
        $this->assertSame('Services you can trust', $live->json('values.en.hero_title'));
        $this->assertFalse($live->json('values.en.show_faq'));
        $this->assertSame('Is it free?', $live->json('values.en.faq.0.question'));
        $this->assertTrue($live->json('values.en.show_how'));                      // untouched settings keep their default
        $this->assertSame('', $live->json('values.ar.hero_title'));                // Arabic is edited separately

        $this->edit('site.home', 'ar', ['hero_title' => 'خدمات تثق بها'])->assertOk();
        $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'ar'])->assertOk();
        $this->assertSame('خدمات تثق بها', $public()->json('values.ar.hero_title'));

        $this->assertSame(2, AuditLog::where('action', 'surface.published')->count());
        $this->assertSame('Launch wording', AuditLog::where('action', 'surface.published')->orderBy('id')->first()->meta['note']);
    }

    public function test_bad_content_is_refused_with_the_field_named(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->edit('site.home', 'en', ['hero_title' => '<b>Hi</b>'])->assertUnprocessable()->assertJsonValidationErrors('values.hero_title');
        $this->edit('site.brand', 'en', ['logo_url' => 'http://insecure.example.com/l.png', 'brand_color' => 'lime'])->assertUnprocessable()->assertJsonValidationErrors(['values.logo_url', 'values.brand_color']);
        $this->edit('site.home', 'en', ['not_a_field' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('values.not_a_field');
        $this->edit('site.home', 'en', ['faq' => [['question' => 'No answer']]])->assertUnprocessable();
        $this->edit('site.home', 'fr', ['hero_title' => 'Bonjour'])->assertUnprocessable();
        $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en'])->assertUnprocessable();       // nothing to publish
    }

    public function test_a_published_version_can_be_restored(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        foreach (['First', 'Second'] as $title) {
            $this->edit('site.navigation', 'en', ['nav_markets' => $title])->assertOk();
            $this->postJson('/api/admin/surfaces/site.navigation/publish', ['locale' => 'en'])->assertOk();
        }
        $this->assertSame('Second', $this->getJson('/api/public/surfaces/site.navigation')->json('values.en.nav_markets'));

        $this->postJson('/api/admin/surfaces/site.navigation/rollback', ['locale' => 'en', 'version' => 1])->assertOk()->assertJsonPath('version', 3);
        $this->assertSame('First', $this->getJson('/api/public/surfaces/site.navigation')->json('values.en.nav_markets'));
        $this->postJson('/api/admin/surfaces/site.navigation/rollback', ['locale' => 'en', 'version' => 99])->assertUnprocessable();

        $history = $this->getJson('/api/admin/surfaces/site.navigation?locale=en')->json('history');
        $this->assertSame([3, 2, 1], array_column($history, 'version'));
        $this->assertSame(1, AuditLog::where('action', 'surface.rolled_back')->count());
    }

    public function test_non_public_surfaces_need_a_signed_in_user_and_drafts_never_leak(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->edit('app.sidebar', 'en', ['labels' => [['item' => 'Orders', 'label' => 'Jobs board']]])->assertOk();
        $this->postJson('/api/admin/surfaces/app.sidebar/publish', ['locale' => 'en'])->assertOk();
        $this->edit('app.sidebar', 'en', ['labels' => [['item' => 'Orders', 'label' => 'DRAFT ONLY']]])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/public/surfaces/app.sidebar')->assertNotFound();                       // not a public surface
        $this->getJson('/api/surfaces/app.sidebar')->assertUnauthorized();

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/surfaces/app.sidebar')->assertOk()->assertJsonPath('values.en.labels.0.label', 'Jobs board');
        $this->getJson('/api/surfaces/not.declared')->assertNotFound();
    }

    public function test_theme_fields_are_validated_strictly(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        // Fonts come from a fixed list: arbitrary CSS is refused.
        $this->edit('site.brand', 'en', ['font_heading' => 'red; background:url(//evil)'])->assertStatus(422);
        $this->edit('site.brand', 'en', ['font_heading' => 'serif', 'font_body' => 'system'])->assertOk();

        // Images must be https; sections must be real ones.
        $this->edit('site.home', 'en', ['hero_image_url' => 'javascript:alert(1)'])->assertStatus(422);
        $this->edit('site.home', 'en', ['hero_image_url' => 'http://example.com/a.png'])->assertStatus(422);
        $this->edit('site.home', 'en', ['section_order' => [['section' => 'not_a_section']]])->assertStatus(422);
        $this->edit('site.home', 'en', ['hero_image_url' => 'https://example.com/a.png', 'section_order' => [['section' => 'faq'], ['section' => 'how']]])->assertOk();
        $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en'])->assertOk();

        $published = $this->getJson('/api/public/surfaces/site.home')->json('values.en');
        $this->assertSame('https://example.com/a.png', $published['hero_image_url']);
        $this->assertSame([['section' => 'faq'], ['section' => 'how']], $published['section_order']);
    }
}
