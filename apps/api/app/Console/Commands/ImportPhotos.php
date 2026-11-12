<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\MediaFile;
use App\Models\Product;
use App\Services\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

/**
 * Attaches your own photos to products and stores in bulk. Name each file after what it is for:
 *   <product-slug>-1.jpg, <product-slug>-2.jpg ...   the product's gallery, in order (the first is the main picture)
 *   store-<store-slug>.jpg                           a store's hero picture
 * Run with --dry-run first to see what would change. Photos replace the product's current pictures.
 */
class ImportPhotos extends Command
{
    protected $signature = 'platform:import-photos {directory : Folder with the photos} {--dry-run : Only show what would happen}';

    protected $description = 'Attach photos to products and store heroes by file name';

    public function handle(MediaLibrary $library): int
    {
        $dir = rtrim((string) $this->argument('directory'), '/');
        if (! is_dir($dir)) {
            $this->error("Folder not found: {$dir}");

            return self::FAILURE;
        }

        $galleries = [];
        $heroes = [];
        foreach (glob($dir.'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (preg_match('/^store-(.+)$/', $name, $m)) {
                $heroes[$m[1]] = $file;
            } elseif (preg_match('/^(.+)-(\d+)$/', $name, $m)) {
                $galleries[$m[1]][(int) $m[2]] = $file;
            } else {
                $this->warn("Skipped {$name}: name it <product-slug>-1.jpg or store-<store-slug>.jpg");
            }
        }

        $done = 0;
        foreach ($galleries as $slug => $files) {
            $product = Product::where('slug', $slug)->first();
            if (! $product) {
                $this->warn("No product with slug [{$slug}]: skipped ".count($files).' photo(s)');
                continue;
            }
            ksort($files);
            $this->line("{$slug}: ".count($files).' photo(s)');
            if (! $this->option('dry-run')) {
                $product->update(['images' => array_values(array_map(fn ($f) => $this->store($library, $f), $files))]);
            }
            $done++;
        }

        foreach ($heroes as $slug => $file) {
            $company = Company::withoutGlobalScopes()->where('slug', $slug)->first();
            if (! $company) {
                $this->warn("No store with slug [{$slug}]: skipped");
                continue;
            }
            $this->line("store {$slug}: hero picture");
            if (! $this->option('dry-run')) {
                $settings = $company->settings ?? [];
                $settings['storefront']['hero_image_url'] = $this->store($library, $file);
                $company->update(['settings' => $settings]);
            }
            $done++;
        }

        $this->info(($this->option('dry-run') ? 'Would update ' : 'Updated ')."{$done} item(s).");

        return self::SUCCESS;
    }

    private function store(MediaLibrary $library, string $file): string
    {
        $upload = new UploadedFile($file, basename($file), null, null, true);

        return '/media/'.$library->store($upload, null)->name;
    }
}
