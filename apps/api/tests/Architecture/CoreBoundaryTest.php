<?php

namespace Tests\Architecture;

use App\Core\Events\EventCatalog;
use App\Core\Modules\ModuleRegistry;
use Tests\TestCase;

/**
 * The rules that keep the platform a platform. They fail the build when someone takes a shortcut:
 *  - the core must not know any product, any product model, or any vendor;
 *  - nothing may reach into a product except through the module registry;
 *  - event names and owners must be clean and accounted for.
 */
class CoreBoundaryTest extends TestCase
{
    /** Names of external vendors. They belong in adapters and configuration data, never in the core or in event names. */
    private const VENDORS = ['stripe', 'moyasar', 'paymob', 'fawry', 'tap payments', 'smsa', 'aramex', 'bosta', 'dhl', 'fedex', 'twilio', 'sendgrid', 'mailgun', 'paypal', 'aws', 'cloudflare'];

    /** @return list<string> */
    private function phpFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_the_core_depends_on_nothing_but_itself_and_the_framework(): void
    {
        $forbidden = ['App\\Models\\', 'App\\Products\\', 'App\\Modules\\', 'App\\Services\\', 'App\\Http\\', 'App\\Support\\', 'App\\Enums\\', 'App\\Policies\\', 'App\\Notifications\\'];
        $violations = [];

        foreach ($this->phpFiles(app_path('Core')) as $file) {
            $source = file_get_contents($file);
            foreach ($forbidden as $namespace) {
                if (str_contains($source, $namespace)) {
                    $violations[] = str_replace(base_path().'/', '', $file)." refers to {$namespace}";
                }
            }
        }

        $this->assertSame([], $violations, "The core must not depend on a product:\n".implode("\n", $violations));
    }

    public function test_no_vendor_is_named_in_the_core_or_in_event_names(): void
    {
        $violations = [];
        $pattern = '/\b('.implode('|', array_map('preg_quote', self::VENDORS)).')\b/i';

        foreach ($this->phpFiles(app_path('Core')) as $file) {
            if (preg_match($pattern, file_get_contents($file), $m)) {
                $violations[] = str_replace(base_path().'/', '', $file)." names the vendor \"{$m[1]}\"";
            }
        }
        foreach (array_keys(app(EventCatalog::class)->all()) as $event) {
            if (preg_match($pattern, $event, $m)) {
                $violations[] = "event {$event} names the vendor \"{$m[1]}\"";
            }
        }

        $this->assertSame([], $violations, "Business capabilities must not depend on a provider:\n".implode("\n", $violations));
    }

    public function test_products_are_reached_only_through_the_module_registry(): void
    {
        $allowed = [app_path('Products'), config_path('modules.php')];
        $violations = [];

        foreach (array_merge($this->phpFiles(app_path()), $this->phpFiles(base_path('routes'))) as $file) {
            if (collect($allowed)->contains(fn ($a) => str_starts_with($file, $a))) {
                continue;
            }
            if (str_contains(file_get_contents($file), 'App\\Products\\')) {
                $violations[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame([], $violations, "These files reach into a product directly:\n".implode("\n", $violations));
    }

    public function test_every_event_has_a_registered_owner_and_a_description(): void
    {
        $modules = array_keys(app(ModuleRegistry::class)->all());
        $problems = [];

        foreach (app(EventCatalog::class)->all() as $name => $definition) {
            if (! in_array($definition->owner, $modules, true)) {
                $problems[] = "{$name} is owned by [{$definition->owner}], which is not an installed module";
            }
            if (trim($definition->description) === '') {
                $problems[] = "{$name} has no description";
            }
        }

        $this->assertSame([], $problems);
        $this->assertContains('armorhub', $modules);
    }

    public function test_armorhub_is_just_a_module(): void
    {
        $module = app(ModuleRegistry::class)->get('armorhub');

        $this->assertNotNull($module);
        $this->assertSame('armorhub', $module->manifest()->id);
        $this->assertTrue(app(EventCatalog::class)->has('order.paid'));
        $this->assertSame('armorhub', app(EventCatalog::class)->require('order.paid')->owner);
    }
}
