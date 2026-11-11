<?php

namespace Tests\Architecture;

use App\Core\Events\Models\EventRecord;
use App\Core\Modules\ModuleRegistry;
use App\Core\Surfaces\SurfaceInvalid;
use App\Core\Surfaces\SurfaceStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Clinic\ClinicModule;
use Tests\TestCase;

/** Editable content for a second product, with draft, publish, rollback and strict validation, and no change to the core. */
class SurfaceTest extends TestCase
{
    use RefreshDatabase;

    private SurfaceStore $store;

    private int $by;

    protected function setUp(): void
    {
        parent::setUp();
        app(ModuleRegistry::class)->load([ClinicModule::class]);
        $this->store = app(SurfaceStore::class);
        $this->by = \App\Models\User::factory()->create()->id;
    }

    public function test_defaults_show_until_something_is_published_and_drafts_stay_private(): void
    {
        $this->assertSame('Welcome to the clinic', $this->store->published('clinic.landing')['welcome']);

        $this->store->saveDraft('clinic.landing', 'en', ['welcome' => 'Hello, patients'], null, $this->by);
        $this->assertSame('Welcome to the clinic', $this->store->published('clinic.landing')['welcome']);   // visitors never see a draft
        $this->assertSame(['welcome' => 'Hello, patients'], $this->store->working('clinic.landing', 'en')['draft']);

        $live = $this->store->publish('clinic.landing', 'en', null, $this->by, 'New greeting');
        $this->assertSame(1, $live->version);
        $this->assertSame('Hello, patients', $this->store->published('clinic.landing')['welcome']);
        $this->assertTrue($this->store->published('clinic.landing')['show_hours']);                          // fields not edited keep their built-in default
    }

    public function test_each_language_has_its_own_content(): void
    {
        $this->store->saveDraft('clinic.landing', 'en', ['welcome' => 'Welcome'], null, $this->by);
        $this->store->publish('clinic.landing', 'en');
        $this->store->saveDraft('clinic.landing', 'ar', ['welcome' => 'أهلًا بكم'], null, $this->by);
        $this->store->publish('clinic.landing', 'ar');

        $all = $this->store->publishedAll('clinic.landing');
        $this->assertSame('Welcome', $all['en']['welcome']);
        $this->assertSame('أهلًا بكم', $all['ar']['welcome']);
    }

    public function test_every_change_is_kept_and_any_version_can_be_restored(): void
    {
        foreach (['One', 'Two', 'Three'] as $i => $text) {
            $this->store->saveDraft('clinic.landing', 'en', ['welcome' => $text], null, $this->by);
            $this->store->publish('clinic.landing', 'en', null, $this->by, "edit {$i}");
        }
        $this->assertSame('Three', $this->store->published('clinic.landing')['welcome']);
        $this->assertCount(3, $this->store->history('clinic.landing', 'en'));

        $restored = $this->store->rollback('clinic.landing', 'en', 1, null, $this->by);
        $this->assertSame('One', $this->store->published('clinic.landing')['welcome']);
        $this->assertSame(4, $restored->version);                                                           // history is added to, never rewritten
        $this->assertSame('Restored version 1', $restored->note);
        $this->assertSame(['archived', 'archived', 'archived', 'published'], collect($this->store->history('clinic.landing', 'en'))->sortBy('version')->pluck('status')->values()->all());
        $this->assertSame(4, EventRecord::where('name', 'surface.published')->count());                      // every publish is announced
    }

    public function test_content_is_validated_strictly(): void
    {
        $bad = [
            'script in text' => ['welcome' => 'Hi <script>alert(1)</script>'],
            'too long' => ['welcome' => str_repeat('x', 61)],
            'http link' => ['phone_link' => 'http://clinic.example.com/book'],
            'javascript link' => ['phone_link' => 'javascript:alert(1)'],
            'bad colour' => ['accent' => 'red'],
            'colour injection' => ['accent' => '#fff; background:url(//evil)'],
            'not a bool' => ['show_hours' => 'yes'],
            'unknown field' => ['surprise' => 'x'],
            'too many items' => ['doctors' => array_fill(0, 4, ['name' => 'Dr X'])],
            'item missing name' => ['doctors' => [['speciality' => 'Heart']]],
        ];

        foreach ($bad as $why => $values) {
            try {
                $this->store->saveDraft('clinic.landing', 'en', $values);
                $this->fail("Accepted: {$why}");
            } catch (SurfaceInvalid $e) {
                $this->assertNotEmpty($e->errors, $why);
            }
        }

        $ok = $this->store->saveDraft('clinic.landing', 'en', ['welcome' => ' Hello ', 'phone_link' => 'https://clinic.example.com/book', 'accent' => '#0B4C72', 'doctors' => [['name' => 'Dr Mona', 'speciality' => 'Pediatrics']]]);
        $this->assertSame('Hello', $ok->content['welcome']);                                                  // trimmed
        $this->assertSame('#0b4c72', $ok->content['accent']);
        $this->assertSame([['name' => 'Dr Mona', 'speciality' => 'Pediatrics']], $ok->content['doctors']);
    }

    public function test_blank_means_use_the_default_and_only_declared_things_are_editable(): void
    {
        $this->store->saveDraft('clinic.landing', 'en', ['welcome' => '']);
        $this->store->publish('clinic.landing', 'en');
        $this->assertSame('Welcome to the clinic', $this->store->published('clinic.landing')['welcome']);

        $this->expectException(\DomainException::class);
        $this->store->saveDraft('payments.keys', 'en', ['secret' => 'x']);                                   // not declared by any module: locked
    }

    public function test_there_must_be_a_draft_to_publish_and_languages_are_known(): void
    {
        try {
            $this->store->publish('clinic.landing', 'en');
            $this->fail('Published nothing.');
        } catch (\DomainException $e) {
            $this->assertSame('There is no draft to publish.', $e->getMessage());
        }

        $this->expectException(\DomainException::class);
        $this->store->saveDraft('clinic.landing', 'fr', ['welcome' => 'Bonjour']);
    }
}
