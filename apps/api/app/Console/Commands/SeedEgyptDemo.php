<?php

namespace App\Console\Commands;

use App\Core\Surfaces\SurfaceStore;
use App\Models\Company;
use App\Models\Industry;
use App\Models\MediaFile;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\IndustryRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * A believable Egyptian marketplace: four stores (cars, beauty, home, devices) with partners in Cairo, Giza and Alexandria,
 * local sellers, customers, products priced in EGP and illustrated hero and product pictures. Safe to run again: everything
 * is found by its slug or email before it is created. All demo logins use the @demo.eg domain.
 */
class SeedEgyptDemo extends Command
{
    protected $signature = 'platform:demo-egypt {--password=password123 : Password for every demo login} {--force : Allow it in production} {--no-site : Do not change the platform home page}';

    protected $description = 'Create an Egyptian demo marketplace: stores, partners, sellers, customers, products and images';

    /** @var array<string, string> demo asset file => /media path */
    private array $media = [];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('This creates demo data. Use --force if you really want it in production.');

            return self::FAILURE;
        }

        if (! Industry::exists() || ! \App\Modules\Automotive\Models\VehiclePart::exists()) {
            $this->callSilent('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        }

        $password = (string) $this->option('password');
        $this->importImages();

        $admin = User::firstOrCreate(['email' => 'admin@demo.eg'], ['name' => 'Mona Admin', 'password' => $password, 'email_verified_at' => now()]);
        $admin->assignRole('platform-admin');

        $stores = [];
        foreach ($this->stores() as $def) {
            $stores[$def['key']] = $this->store($def, $password);
        }
        foreach ($this->customers() as [$name, $email, $key, $lat, $lng, $city]) {
            $store = $stores[$key];
            // Customers share their location, as a real one must before ordering a service: it is how nearby partners are found.
            $u = User::firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => $password, 'email_verified_at' => now(), 'company_id' => $store['company']->id,
                'latitude' => $lat, 'longitude' => $lng, 'city' => $city, 'country' => 'EG', 'location_enabled' => true,
            ]);
            $u->assignRole('customer');
        }

        if (! $this->option('no-site')) {
            $this->publishSite();
        }

        $this->info('Egyptian demo ready: '.count($stores).' stores, '.Product::where('market', 'EG')->count().' products.');
        $this->line("Logins (password {$password}): admin@demo.eg, owner.auto@demo.eg (also .beauty .home .devices), manager.auto1@demo.eg, tech.auto1@demo.eg, seller.khan@demo.eg (also .delta .aswan .siwa .autopro .techsouq), customers like nour.fawzy@demo.eg");

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ images

    private function importImages(): void
    {
        $dir = database_path('demo-assets/egypt');
        $disk = Storage::disk(config('platform.media_disk'));

        foreach (glob($dir.'/*.jpg') ?: [] as $file) {
            $base = basename($file);
            $hex = md5('demo-egypt/'.$base);
            $name = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12).'.jpg';

            // The file is re-written when it is missing even if its row exists: a host with a temporary disk loses the files on restart.
            if (! $disk->exists("media/{$name}")) {
                $disk->put("media/{$name}", file_get_contents($file));
            }
            if (! MediaFile::where('name', $name)->exists()) {
                MediaFile::create(['name' => $name, 'original_name' => $base, 'mime' => 'image/jpeg', 'size' => filesize($file), 'sha256' => hash_file('sha256', $file)]);
            }

            $this->media[$base] = "/media/{$name}";
        }
    }

    /** @return list<string> */
    private function pics(string $key): array
    {
        return array_values(array_filter(array_map(fn ($n) => $this->media["{$key}-{$n}.jpg"] ?? null, [1, 2, 3])));
    }

    // ------------------------------------------------------------------ stores

    private function store(array $def, string $password): array
    {
        $industry = Industry::where('key', $def['industry'])->firstOrFail();
        $tenant = Tenant::firstOrCreate(['name' => $def['tenant'], 'market' => 'EG'], ['industry_id' => $industry->id]);

        $company = Company::withoutGlobalScopes()->where('slug', $def['slug'])->first() ?? Company::create([
            'tenant_id' => $tenant->id, 'name' => $def['name'], 'slug' => $def['slug'], 'status' => 'active', 'email' => "hello@{$def['slug']}.demo.eg",
            'settings' => ['storefront' => [
                'enabled' => true, 'headline' => $def['headline'], 'subheadline' => $def['sub'], 'about' => $def['about'], 'primary_color' => $def['color'],
                'hero_image_url' => $this->media[$def['hero'].'.jpg'] ?? null, 'contact_email' => "hello@{$def['slug']}.demo.eg", 'contact_phone' => '+20 2 2792 0000', 'featured_product_ids' => [],
            ]],
        ]);

        $owner = User::firstOrCreate(['email' => "owner.{$def['key']}@demo.eg"], ['name' => $def['owner'], 'password' => $password, 'email_verified_at' => now(), 'company_id' => $company->id]);
        $owner->assignRole('company-owner');

        $models = app(IndustryRegistry::class)->targetModels();
        $class = $models[$def['target']];
        foreach ($def['catalog'] ?? [] as $entry) {
            $class::firstOrCreate(['slug' => str($entry)->slug()], ['name' => $entry]);
        }

        $this->partners($def, $company, $password);
        $sellers = $this->sellers($def, $tenant, $password);
        $featured = [];

        foreach (array_merge($def['products'], $this->moreProducts()[$def['key']] ?? []) as $p) {
            $seller = isset($p['seller']) ? $sellers[$p['seller']] : null;
            $slug = 'eg-'.$p['key'];
            $product = Product::where('slug', $slug)->first();
            // The catalogue's organisation can change between releases: keep existing demo products filed under the current category.
            $product?->category !== $p['category'] && $product?->update(['category' => $p['category']]);
            $product && $product->installation !== ($p['installation'] ?? 'none') && $product->update(['installation' => $p['installation'] ?? 'none']);
            $product ??= Product::create([
                'industry_id' => $industry->id, 'market' => 'EG', 'seller_id' => $seller?->id, 'name' => $p['name'], 'slug' => $slug, 'brand' => $p['brand'] ?? null,
                'category' => $p['category'], 'description' => $p['desc'], 'images' => $this->pics($p['art']), 'base_price' => $p['price'], 'compare_at_price' => $p['was'] ?? null,
                'warranty_years' => $p['warranty'] ?? null, 'requires_shipping' => $seller !== null, 'is_active' => true, 'installation' => $p['installation'] ?? 'none',
                'attributes' => ['highlights' => $p['highlights'] ?? []],
            ]);

            // Goods have one price for the whole order option, so customers can add them to an order like anything else.
            $rules = $p['rules'] ?? ($seller ? [$def['whole'] => $p['price']] : []);

            foreach ($rules as $option => $price) {
                $target = $class::where('slug', str($option)->slug())->first();
                if ($target) {
                    ProductPriceRule::firstOrCreate(['product_id' => $product->id, 'target_type' => $def['target'], 'target_id' => $target->id], ['price' => $price, 'duration_minutes' => $p['minutes'] ?? 90, 'is_active' => true]);
                }
            }
            $featured[] = $product->id;
        }

        $settings = $company->settings;
        $settings['storefront']['featured_product_ids'] = array_slice($featured, 0, 4);
        $company->update(['settings' => $settings]);

        return ['company' => $company, 'tenant' => $tenant];
    }

    private function partners(array $def, Company $store, string $password): void
    {
        foreach ($def['partners'] as $i => [$name, $city, $lat, $lng, $about]) {
            $company = Company::withoutGlobalScopes()->where('tenant_id', $store->tenant_id)->where('name', $name)->first()
                ?? Company::create(['tenant_id' => $store->tenant_id, 'name' => $name, 'type' => 'partner', 'status' => 'active']);
            $partner = Partner::withoutGlobalScopes()->where('company_id', $company->id)->first() ?? Partner::create([
                'company_id' => $company->id, 'name' => $name, 'country' => 'EG', 'city' => $city, 'latitude' => $lat, 'longitude' => $lng,
                'location_enabled' => true, 'public_profile' => true, 'description' => $about, 'phone' => '+20 10 0000 00'.$i.'0',
            ]);
            $n = $def['key'].($i + 1);
            $manager = User::firstOrCreate(['email' => "manager.{$n}@demo.eg"], ['name' => $this->person($def['key'], $i, 'manager'), 'password' => $password, 'email_verified_at' => now(), 'company_id' => $company->id, 'partner_id' => $partner->id]);
            $manager->assignRole('partner-manager');
            $tech = User::firstOrCreate(['email' => "tech.{$n}@demo.eg"], ['name' => $this->person($def['key'], $i, 'tech'), 'password' => $password, 'email_verified_at' => now(), 'company_id' => $company->id, 'partner_id' => $partner->id]);
            $tech->assignRole('technician');
        }
    }

    /** @return array<string, Company> */
    private function sellers(array $def, Tenant $tenant, string $password): array
    {
        $out = [];
        foreach ($def['sellers'] ?? [] as $key => $name) {
            $company = Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', $name)->first()
                ?? Company::create(['tenant_id' => $tenant->id, 'name' => $name, 'type' => 'supplier', 'status' => 'active']);
            $user = User::firstOrCreate(['email' => "seller.{$key}@demo.eg"], ['name' => "{$name} Sales", 'password' => $password, 'email_verified_at' => now(), 'company_id' => $company->id]);
            $user->assignRole('seller');
            $out[$key] = $company;
        }

        return $out;
    }

    private function person(string $store, int $i, string $role): string
    {
        $names = ['Ahmed Hassan', 'Mohamed Salah', 'Omar Khaled', 'Karim Mostafa', 'Youssef Adel', 'Tarek Fathy', 'Hossam Ali', 'Mahmoud Samir', 'Amr Nabil', 'Sherif Hany'];

        return $names[(crc32($store.$i.$role) & 0x7fffffff) % count($names)];
    }

    // ------------------------------------------------------------------ platform home page

    private function publishSite(): void
    {
        $surfaces = app(SurfaceStore::class);
        $set = function (string $key, array $values) use ($surfaces) {
            foreach (['en'] as $locale) {
                $surfaces->saveDraft($key, $locale, $values);
                $surfaces->publish($key, $locale, null, null, 'Egyptian demo');
            }
        };

        $set('site.home', [
            'badge_text' => 'Egypt · مصر', 'hero_title' => 'Trusted services and made-in-Egypt goods, near you',
            'hero_subtitle' => 'Book vetted professionals in Cairo, Giza and Alexandria, and shop Egyptian crafts, cotton and care products from local sellers.',
            'hero_image_url' => $this->media['hero-cairo.jpg'] ?? '', 'banner_image_url' => $this->media['hero-cairo.jpg'] ?? '',
        ]);
    }

    /**
     * Extra products per store, in new categories, so each market's catalogue has 10-15 items.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function moreProducts(): array
    {
        return [
            'auto' => [
                ['key' => 'headlight', 'name' => 'Headlight Restoration', 'category' => 'coatings-and-detailing', 'art' => 'p-headlight', 'price' => 600, 'warranty' => 1, 'minutes' => 60,
                    'desc' => "Cloudy, yellowed headlights polished back to clear and sealed against the sun.\nإعادة تلميع الكشافات الأمامية.", 'highlights' => ['Clearer night vision', 'UV sealant included'], 'rules' => ['Headlights' => 600]],
                ['key' => 'detailing', 'name' => 'Full Interior and Exterior Detailing', 'category' => 'coatings-and-detailing', 'art' => 'p-detailing', 'price' => 1500, 'minutes' => 240,
                    'desc' => "Clay bar, machine polish, interior steam clean and leather care in one day.\nتلميع وتنظيف شامل داخلي وخارجي.", 'highlights' => ['Machine polish', 'Steam-cleaned interior'], 'rules' => ['Whole car' => 1500]],
                ['key' => 'vinylwrap', 'name' => 'Colour Change Vinyl Wrap', 'category' => 'protection-films', 'art' => 'p-vinylwrap', 'price' => 9000, 'warranty' => 3, 'minutes' => 480,
                    'desc' => "Change your car's colour without paint. Gloss, satin or matt finishes.\nتغيير لون السيارة بالفينيل بدون دهان.", 'highlights' => ['Reversible', '3-year warranty', 'Gloss, satin or matt'], 'rules' => ['Hood' => 3200, 'Roof' => 3500, 'Mirrors' => 900, 'Whole car' => 18000]],
                ['key' => 'waterless', 'seller' => 'autopro', 'name' => 'Waterless Wash and Wax Spray', 'brand' => 'AutoPro', 'category' => 'car-care', 'art' => 'p-waterless', 'price' => 260, 'was' => 330,
                    'desc' => "Clean and shine in the shade of your own street, with almost no water.\nغسيل وتلميع بدون ماء تقريبًا.", 'highlights' => ['Safe on coatings', '750 ml']],
                ['key' => 'wheelclean', 'seller' => 'autopro', 'name' => 'Wheel and Tyre Cleaner', 'brand' => 'AutoPro', 'category' => 'car-care', 'art' => 'p-wheelclean', 'price' => 180,
                    'desc' => "Lifts brake dust and road tar from alloys without scrubbing.\nمنظف للجنوط والإطارات.", 'highlights' => ['pH balanced', '500 ml']],
                ['key' => 'dashcam', 'seller' => 'autopro', 'installation' => 'optional', 'name' => '4K Dash Camera with Hardwire Kit', 'brand' => 'AutoPro', 'category' => 'electronics', 'art' => 'p-dashcam', 'price' => 2400, 'was' => 2900,
                    'desc' => "Front and rear recording with parking mode. Fit it yourself or ask a centre to hardwire it neatly.\nكاميرا سيارة 4K مع طقم توصيل مباشر.", 'highlights' => ['4K front camera', 'Parking mode', 'Installation is optional']],
                ['key' => 'oud', 'seller' => 'autopro', 'name' => 'Oud Car Air Freshener', 'brand' => 'AutoPro', 'category' => 'interior-care', 'art' => 'p-oud', 'price' => 95,
                    'desc' => "A warm oud and amber scent that lasts for weeks.\nمعطر سيارة برائحة العود والعنبر.", 'highlights' => ['Lasts up to 6 weeks', 'Spill-proof bottle']],
                ['key' => 'leather', 'seller' => 'autopro', 'name' => 'Leather Seat Conditioner', 'brand' => 'AutoPro', 'category' => 'interior-care', 'art' => 'p-leather', 'price' => 240, 'was' => 300,
                    'desc' => "Keeps leather soft and stops cracking in the Egyptian heat.\nمرطب لمقاعد الجلد.", 'highlights' => ['UV protection', '250 ml']],
            ],
            'beauty' => [
                ['key' => 'manicure', 'name' => 'Gel Manicure and Pedicure', 'category' => 'hair-and-nails', 'art' => 'p-manicure', 'price' => 650, 'minutes' => 90,
                    'desc' => "Long-lasting gel colour with a full care routine.\nمانيكير وباديكير بالجل.", 'highlights' => ['Up to 3 weeks wear', 'Sterilised tools'], 'rules' => ['Nails' => 650, 'Hands' => 400]],
                ['key' => 'rosefacial', 'name' => 'Rose Glow Facial', 'category' => 'face-and-body', 'art' => 'p-rosefacial', 'price' => 800, 'minutes' => 60,
                    'desc' => "A calming facial with Fayoum rose, for bright, hydrated skin.\nعلاج وجه بماء الورد الفيومي.", 'highlights' => ['Natural rose extracts', 'Gentle on sensitive skin'], 'rules' => ['Face' => 800]],
                ['key' => 'saltspa', 'name' => 'Siwa Salt Body Scrub and Massage', 'category' => 'face-and-body', 'art' => 'p-saltspa', 'price' => 1800, 'minutes' => 90,
                    'desc' => "A full-body salt scrub followed by a relaxing massage.\nتقشير بالملح وتدليك للجسم.", 'highlights' => ['Siwa salt', '90 minutes'], 'rules' => ['Full Body' => 1800]],
                ['key' => 'pricklypear', 'seller' => 'siwa', 'name' => 'Prickly Pear Seed Oil', 'category' => 'natural-care', 'art' => 'p-pricklypear', 'price' => 420, 'was' => 520,
                    'desc' => "A rich, light anti-ageing oil for face and neck.\nزيت بذور التين الشوكي.", 'highlights' => ['Cold pressed', '30 ml']],
                ['key' => 'jasmine', 'seller' => 'siwa', 'name' => 'Jasmine Body Butter', 'category' => 'natural-care', 'art' => 'p-jasmine', 'price' => 170,
                    'desc' => "Whipped shea and jasmine for soft skin all day.\nزبدة جسم بالياسمين.", 'highlights' => ['Vegan', '200 g jar']],
                ['key' => 'kohl', 'seller' => 'siwa', 'name' => 'Natural Kohl Eyeliner', 'category' => 'natural-care', 'art' => 'p-kohl', 'price' => 70,
                    'desc' => "Traditional kohl, made the old way and smooth to apply.\nكحل طبيعي تقليدي.", 'highlights' => ['No additives', 'Smudge resistant']],
            ],
            'home' => [
                ['key' => 'doorlock', 'seller' => 'delta', 'installation' => 'required', 'name' => 'Smart Door Lock', 'brand' => 'Delta Home', 'category' => 'smart-home', 'art' => 'p-doorlock', 'price' => 3200, 'was' => 3800,
                    'desc' => "Fingerprint, code and key. It must be fitted to your door by a professional.\nقفل ذكي بالبصمة والرمز، يحتاج تركيبًا احترافيًا.", 'highlights' => ['Fingerprint and code', 'Needs professional fitting']],
                ['key' => 'pest', 'name' => 'Pest Control Visit', 'category' => 'home-services', 'art' => 'p-pest', 'price' => 700, 'warranty' => 1, 'minutes' => 90,
                    'desc' => "Safe treatment against insects and rodents, with a follow-up visit.\nمكافحة الحشرات والقوارض.", 'highlights' => ['Family-safe products', '3-month cover'], 'rules' => ['Kitchen' => 700, 'Bathroom' => 500, 'Whole home' => 1500]],
                ['key' => 'painting', 'name' => 'Interior Painting', 'category' => 'home-services', 'art' => 'p-painting', 'price' => 1200, 'minutes' => 480,
                    'desc' => "Clean lines, covered furniture and a tidy finish.\nدهان داخلي بتشطيب نظيف.", 'highlights' => ['Furniture protected', 'Quality paint'], 'rules' => ['Bedroom' => 1200, 'Living Room' => 1800, 'Office' => 1500]],
                ['key' => 'kilim', 'seller' => 'khan', 'name' => 'Hand-Woven Kilim Cushion Cover', 'category' => 'home-textiles', 'art' => 'p-kilim', 'price' => 230,
                    'desc' => "Bright geometric weave by Egyptian artisans.\nغطاء وسادة كليم منسوج يدويًا.", 'highlights' => ['Hand woven', '45 x 45 cm']],
                ['key' => 'bakhoor', 'seller' => 'khan', 'name' => 'Bakhoor Incense Set', 'category' => 'handicrafts', 'art' => 'p-bakhoor', 'price' => 310, 'was' => 380,
                    'desc' => "Aromatic wood chips with a brass burner.\nطقم بخور مع مبخرة نحاسية.", 'highlights' => ['Brass burner included', 'Long-lasting scent']],
                ['key' => 'bedsheets', 'seller' => 'delta', 'name' => 'Egyptian Cotton Bedsheet Set', 'category' => 'home-textiles', 'art' => 'p-bedsheets', 'price' => 1450, 'was' => 1800,
                    'desc' => "Cool, crisp 400-thread-count sheets from the Nile Delta.\nطقم ملاءات قطن مصري.", 'highlights' => ['400 thread count', 'Fits double beds']],
                ['key' => 'dates', 'seller' => 'aswan', 'name' => 'Siwa Dates (1 kg)', 'category' => 'grocery', 'art' => 'p-dates', 'price' => 140,
                    'desc' => "Soft, sweet dates from the oasis palms.\nتمور سيوة الطازجة.", 'highlights' => ['No added sugar', '1 kg box']],
                ['key' => 'dukkah', 'seller' => 'aswan', 'name' => 'Egyptian Dukkah Spice Mix', 'category' => 'grocery', 'art' => 'p-dukkah', 'price' => 90,
                    'desc' => "Nuts, sesame and spices for bread, eggs and salads.\nدقة مصرية.", 'highlights' => ['Freshly ground', '200 g jar']],
            ],
            'devices' => [
                ['key' => 'screenrepair', 'name' => 'Screen Replacement', 'category' => 'repairs', 'art' => 'p-screenrepair', 'price' => 1800, 'warranty' => 1, 'minutes' => 60,
                    'desc' => "A cracked screen replaced with a tested panel.\nاستبدال شاشة الهاتف المكسورة.", 'highlights' => ['6-month to 1-year warranty', 'Same-day repair'], 'rules' => ['Screen' => 1800]],
                ['key' => 'waterdamage', 'name' => 'Water Damage Diagnostic and Clean', 'category' => 'repairs', 'art' => 'p-waterdamage', 'price' => 400, 'minutes' => 90,
                    'desc' => "Ultrasonic clean and testing of a phone that got wet.\nفحص وتنظيف هاتف تعرض للماء.", 'highlights' => ['Ultrasonic cleaning', 'Free if not fixable'], 'rules' => ['Whole device' => 400]],
                ['key' => 'lensfit', 'name' => 'Camera Lens Protector Fitting', 'category' => 'protection', 'art' => 'p-lensfit', 'price' => 120, 'minutes' => 15,
                    'desc' => "Scratch protection for your camera lenses, fitted dust-free.\nتركيب حماية عدسة الكاميرا.", 'highlights' => ['Dust-free fitting', 'Done in 15 minutes'], 'rules' => ['Camera Lens' => 120]],
                ['key' => 'phonecase', 'seller' => 'techsouq', 'name' => 'Shockproof Phone Case', 'category' => 'accessories', 'art' => 'p-phonecase', 'price' => 210, 'was' => 280,
                    'desc' => "Military-grade corners with a clear back.\nحافظة هاتف مقاومة للصدمات.", 'highlights' => ['Drop tested', 'Clear back']],
                ['key' => 'powerbank', 'seller' => 'techsouq', 'name' => '20,000 mAh Power Bank', 'category' => 'accessories', 'art' => 'p-powerbank', 'price' => 780, 'was' => 950,
                    'desc' => "Charges a phone four times, with fast-charge ports.\nباور بانك 20000 مللي أمبير.", 'highlights' => ['22.5W fast charge', '2 USB ports', '1-year warranty']],
                ['key' => 'cable', 'seller' => 'techsouq', 'name' => 'Braided USB-C Cable (2 m)', 'category' => 'accessories', 'art' => 'p-cable', 'price' => 120,
                    'desc' => "Tough nylon braid, rated for 100W charging.\nكابل USB-C مجدول.", 'highlights' => ['100W rated', '2 m long']],
            ],
        ];
    }

    // ------------------------------------------------------------------ the dataset

    /** @return list<array{string, string, string, float, float, string}> name, email, store key, latitude, longitude, city */
    private function customers(): array
    {
        return [
            ['Nour El-Din Fawzy', 'nour.fawzy@demo.eg', 'auto', 30.0626, 31.2197, 'Cairo'], ['Mariam Ashraf', 'mariam.ashraf@demo.eg', 'beauty', 30.0916, 31.3225, 'Cairo'],
            ['Khaled Abdelrahman', 'khaled.abdelrahman@demo.eg', 'home', 30.0131, 31.2089, 'Giza'], ['Salma Mahmoud', 'salma.mahmoud@demo.eg', 'beauty', 29.9602, 31.2569, 'Cairo'],
            ['Hesham Zaki', 'hesham.zaki@demo.eg', 'devices', 30.0561, 31.2000, 'Giza'], ['Dina Farouk', 'dina.farouk@demo.eg', 'home', 31.2156, 29.9553, 'Alexandria'],
            ['Ramy Nassar', 'ramy.nassar@demo.eg', 'auto', 31.2001, 29.9187, 'Alexandria'], ['Yasmin Sherif', 'yasmin.sherif@demo.eg', 'devices', 31.0409, 31.3785, 'Mansoura'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function stores(): array
    {
        return [
            [
                'key' => 'auto', 'industry' => 'automotive', 'tenant' => 'Pharaoh Auto', 'name' => 'Pharaoh Auto Care', 'slug' => 'pharaoh-auto-care', 'color' => '#9a3412', 'hero' => 'hero-auto', 'owner' => 'Hany El-Sayed',
                'headline' => 'Protect your car like a pharaoh', 'sub' => 'Paint protection film, ceramic coating and tint, fitted by certified installers in Cairo, Giza and Alexandria.',
                'about' => "Pharaoh Auto Care connects Egyptian car owners with trusted, certified installers.\nأفضل خبراء حماية السيارات في القاهرة والجيزة والإسكندرية.",
                'target' => 'vehicle_part', 'whole' => 'Whole car', 'catalog' => ['Hood', 'Bumper', 'Mirrors', 'Roof', 'Windshield', 'Headlights', 'Whole car'],
                'sellers' => ['autopro' => 'AutoPro Egypt'],
                'partners' => [
                    ['Zamalek Detailing Studio', 'Cairo', 30.0626, 31.2197, 'Detailing and paint protection film specialists in Zamalek.'],
                    ['Nasr City Auto Film', 'Cairo', 30.0561, 31.3301, 'Certified film and ceramic coating installers in Nasr City.'],
                    ['Corniche Car Care', 'Alexandria', 31.2001, 29.9187, 'Alexandria\'s favourite protection and tint studio, on the Corniche.'],
                ],
                'products' => [
                    ['key' => 'ppf', 'name' => 'Paint Protection Film (PPF)', 'brand' => 'XPEL', 'category' => 'protection-films', 'art' => 'p-ppf', 'price' => 4500, 'warranty' => 10, 'minutes' => 180,
                        'desc' => "Self-healing urethane film that shields your paint from stone chips, scratches and the Egyptian sun.\nفيلم حماية ذاتي الإصلاح يحمي الطلاء من الخدوش والحصى والشمس.",
                        'highlights' => ['10-year warranty', 'Self-healing finish', 'Installed by certified technicians'], 'rules' => ['Hood' => 9500, 'Bumper' => 7800, 'Mirrors' => 2200, 'Roof' => 8800]],
                    ['key' => 'ceramic', 'name' => 'Nano Ceramic Coating', 'brand' => 'Gtechniq', 'category' => 'coatings-and-detailing', 'art' => 'p-ceramic', 'price' => 2500, 'warranty' => 5, 'minutes' => 120,
                        'desc' => "Deep gloss and water-beading protection that lasts for years.\nطبقة سيراميك نانو تمنح لمعانًا عميقًا وحماية تدوم لسنوات.",
                        'highlights' => ['5-year warranty', 'Hydrophobic gloss', 'Easy to wash'], 'rules' => ['Hood' => 4500, 'Bumper' => 3600, 'Roof' => 4200]],
                    ['key' => 'tint', 'name' => 'Heat-Rejection Window Tint', 'brand' => '3M', 'category' => 'protection-films', 'art' => 'p-tint', 'price' => 1800, 'warranty' => 7, 'minutes' => 90,
                        'desc' => "Blocks heat and UV so your cabin stays cool through the Cairo summer.\nتظليل يعزل الحرارة والأشعة فوق البنفسجية.",
                        'highlights' => ['Rejects up to 60% of heat', '99% UV blocked', '7-year warranty'], 'rules' => ['Windshield' => 2400, 'Headlights' => 700]],
                    ['key' => 'carkit', 'seller' => 'autopro', 'name' => 'Ceramic Care Kit', 'brand' => 'AutoPro', 'category' => 'car-care', 'art' => 'p-carkit', 'price' => 1450, 'was' => 1900,
                        'desc' => "Shampoo, booster spray and applicator pads to keep coated paint perfect.\nطقم عناية بالسيراميك: شامبو وبخاخ تعزيز وإسفنج.",
                        'highlights' => ['Keeps coatings glossy', 'Ships across Egypt']],
                    ['key' => 'microfiber', 'seller' => 'autopro', 'name' => 'Premium Microfibre Detailing Set', 'brand' => 'AutoPro', 'category' => 'car-care', 'art' => 'p-microfiber', 'price' => 320, 'was' => 420,
                        'desc' => "Six plush towels for washing, drying and polishing without a scratch.\nست مناشف مايكروفايبر للغسيل والتجفيف والتلميع.",
                        'highlights' => ['6 towels, 400 GSM', 'Lint-free', 'Machine washable']],
                ],
            ],
            [
                'key' => 'beauty', 'industry' => 'beauty', 'tenant' => 'Nefertiti Beauty', 'name' => 'Nefertiti Beauty', 'slug' => 'nefertiti-beauty', 'color' => '#be185d', 'hero' => 'hero-beauty', 'owner' => 'Laila Mansour',
                'headline' => 'Beauty rituals, Egyptian at heart', 'sub' => 'Book trusted salons and shop natural products from Siwa, Fayoum and Aswan.',
                'about' => "From Hydrafacials to hand-drawn henna, book vetted beauty professionals near you.\nاحجزي أفضل خبيرات التجميل في القاهرة والإسكندرية.",
                'target' => 'treatment_area', 'whole' => 'Full Body', 'catalog' => ['Face', 'Hands', 'Hair', 'Nails', 'Full Body'],
                'sellers' => ['siwa' => 'Siwa Naturals'],
                'partners' => [
                    ['Heliopolis Glow Studio', 'Cairo', 30.0916, 31.3225, 'Skin, hair and nail studio in the heart of Heliopolis.'],
                    ['Maadi Beauty Lab', 'Cairo', 29.9602, 31.2569, 'Modern skin clinic and henna artists in Maadi.'],
                ],
                'products' => [
                    ['key' => 'hydrafacial', 'name' => 'Hydrafacial Session', 'category' => 'face-and-body', 'art' => 'p-hydrafacial', 'price' => 1200, 'minutes' => 60,
                        'desc' => "Deep cleanse, exfoliation and hydration in one glowing session.\nجلسة تنظيف وترطيب عميق للبشرة.", 'highlights' => ['Visible glow in 60 minutes', 'Suitable for all skin types'], 'rules' => ['Face' => 1200]],
                    ['key' => 'henna', 'name' => 'Bridal Henna Design', 'category' => 'hair-and-nails', 'art' => 'p-henna-design', 'price' => 450, 'minutes' => 120,
                        'desc' => "Hand-drawn traditional and modern henna for brides and celebrations.\nنقش حناء يدوي للعرائس والمناسبات.", 'highlights' => ['Natural henna', 'Bridal and party designs'], 'rules' => ['Hands' => 450, 'Full Body' => 1500]],
                    ['key' => 'keratin', 'name' => 'Keratin Smoothing Treatment', 'category' => 'hair-and-nails', 'art' => 'p-keratin', 'price' => 2800, 'minutes' => 180,
                        'desc' => "Frizz-free, silky hair that lasts for months.\nعلاج كيراتين لشعر ناعم ولامع.", 'highlights' => ['Lasts up to 4 months', 'Formaldehyde-free'], 'rules' => ['Hair' => 2800]],
                    ['key' => 'saltscrub', 'seller' => 'siwa', 'name' => 'Siwa Salt Face Scrub', 'category' => 'natural-care', 'art' => 'p-saltscrub', 'price' => 210, 'was' => 260,
                        'desc' => "Gently exfoliating scrub made with pure Siwa Oasis salt.\nمقشر لطيف من ملح واحة سيوة.", 'highlights' => ['Pure Siwa salt', 'Vegan', '100 g jar']],
                    ['key' => 'blackseed', 'seller' => 'siwa', 'name' => 'Cold-Pressed Black Seed Oil', 'category' => 'natural-care', 'art' => 'p-blackseed', 'price' => 260,
                        'desc' => "Cold-pressed nigella oil for skin and hair.\nزيت حبة البركة المعصور على البارد.", 'highlights' => ['100% cold pressed', '60 ml dropper bottle']],
                    ['key' => 'rosewater', 'seller' => 'siwa', 'name' => 'Fayoum Rose Water', 'category' => 'natural-care', 'art' => 'p-rosewater', 'price' => 95,
                        'desc' => "Fresh, steam-distilled rose water from Fayoum.\nماء ورد طبيعي من الفيوم.", 'highlights' => ['Steam distilled', 'Alcohol free', '250 ml']],
                    ['key' => 'hennapowder', 'seller' => 'siwa', 'name' => 'Natural Egyptian Henna Powder', 'category' => 'natural-care', 'art' => 'p-hennapowder', 'price' => 80, 'was' => 100,
                        'desc' => "Fine, fresh-ground henna for hair and body art.\nحناء طبيعية ناعمة للشعر والنقش.", 'highlights' => ['No additives', '100 g pack']],
                ],
            ],
            [
                'key' => 'home', 'industry' => 'home-services', 'tenant' => 'Beit Masr', 'name' => 'Beit Masr', 'slug' => 'beit-masr', 'color' => '#0f766e', 'hero' => 'hero-home', 'owner' => 'Sara Abdelaziz',
                'headline' => 'Everything for the Egyptian home', 'sub' => 'Cleaning, AC and plumbing by vetted pros, plus handmade goods from Khan El-Khalili.',
                'about' => "Beit Masr brings trusted home services and authentic Egyptian crafts to your door.\nبيت مصر: خدمات منزلية موثوقة ومنتجات مصرية أصيلة.",
                'target' => 'room_type', 'whole' => 'Whole home', 'catalog' => ['Living Room', 'Bedroom', 'Kitchen', 'Bathroom', 'Office', 'Whole home'],
                'sellers' => ['khan' => 'Khan El-Khalili Crafts', 'delta' => 'Delta Cotton House', 'aswan' => 'Aswan Spice Co'],
                'partners' => [
                    ['Giza CleanPro', 'Giza', 30.0131, 31.2089, 'Deep-cleaning teams across Giza and 6th of October.'],
                    ['Alex Cooling Experts', 'Alexandria', 31.2156, 29.9553, 'AC servicing and plumbing across Alexandria.'],
                ],
                'products' => [
                    ['key' => 'cleaning', 'name' => 'Deep Home Cleaning', 'category' => 'home-services', 'art' => 'p-cleaning', 'price' => 600, 'minutes' => 180,
                        'desc' => "A thorough top-to-bottom clean by a vetted team.\nتنظيف شامل بواسطة فريق موثوق.", 'highlights' => ['Eco-friendly products', 'Insured teams'], 'rules' => ['Living Room' => 600, 'Bedroom' => 450, 'Kitchen' => 700, 'Bathroom' => 400, 'Office' => 800]],
                    ['key' => 'ac', 'name' => 'AC Service and Deep Clean', 'category' => 'home-services', 'art' => 'p-acservice', 'price' => 350, 'minutes' => 60,
                        'desc' => "Filter wash, coil cleaning and gas check so your AC runs cold all summer.\nصيانة وتنظيف المكيفات.", 'highlights' => ['Gas pressure check', '30-day service guarantee'], 'rules' => ['Living Room' => 350, 'Bedroom' => 300, 'Office' => 400]],
                    ['key' => 'plumbing', 'name' => 'Plumbing Visit', 'category' => 'home-services', 'art' => 'p-plumbing', 'price' => 300, 'minutes' => 60,
                        'desc' => "Leaks, blockages and fittings fixed the same day.\nإصلاح التسريبات والانسدادات في نفس اليوم.", 'highlights' => ['Same-day visits', 'Parts at cost'], 'rules' => ['Kitchen' => 300, 'Bathroom' => 350]],
                    ['key' => 'lantern', 'seller' => 'khan', 'name' => 'Khan El-Khalili Brass Lantern (Fanous)', 'category' => 'handicrafts', 'art' => 'p-lantern', 'price' => 650, 'was' => 850,
                        'desc' => "A hand-cut brass lantern made by artisans in Khan El-Khalili. Casts star-shaped light across the room.\nفانوس نحاسي مقطوع يدويًا من خان الخليلي.", 'highlights' => ['Hand-cut solid brass', 'Made in Cairo', 'Ready to hang or stand']],
                    ['key' => 'basket', 'seller' => 'khan', 'name' => 'Hand-Woven Palm Basket', 'category' => 'handicrafts', 'art' => 'p-basket', 'price' => 420,
                        'desc' => "Woven from Egyptian palm fibre by Fayoum artisans.\nسلة من خوص النخيل منسوجة يدويًا.", 'highlights' => ['Natural palm fibre', 'Sturdy handle']],
                    ['key' => 'papyrus', 'seller' => 'khan', 'name' => 'Hand-Painted Papyrus Art', 'category' => 'handicrafts', 'art' => 'p-papyrus', 'price' => 290, 'was' => 360,
                        'desc' => "Genuine papyrus painted by hand with pharaonic motifs.\nبردي أصلي مرسوم يدويًا.", 'highlights' => ['Genuine papyrus', 'Signed by the artist']],
                    ['key' => 'pottery', 'seller' => 'khan', 'name' => 'Fayoum Pottery Vase', 'category' => 'handicrafts', 'art' => 'p-pottery', 'price' => 540,
                        'desc' => "Turquoise-glazed pottery from the Tunis village, Fayoum.\nفازة فخار من قرية تونس بالفيوم.", 'highlights' => ['Glazed by hand', 'Each piece unique']],
                    ['key' => 'towelset', 'seller' => 'delta', 'name' => 'Egyptian Cotton Bath Towel Set', 'category' => 'home-textiles', 'art' => 'p-towelset', 'price' => 890, 'was' => 1190,
                        'desc' => "Four heavyweight towels in long-staple Egyptian cotton.\nطقم مناشف من القطن المصري طويل التيلة.", 'highlights' => ['100% Egyptian cotton', '600 GSM', 'Set of 4']],
                    ['key' => 'hibiscus', 'seller' => 'aswan', 'name' => 'Aswan Hibiscus (Karkade) Tea', 'category' => 'grocery', 'art' => 'p-hibiscus', 'price' => 120, 'was' => 150,
                        'desc' => "Sun-dried hibiscus from Aswan, served hot or iced.\nكركديه أسواني مجفف طبيعيًا.", 'highlights' => ['Sun dried', '250 g', 'Caffeine free']],
                    ['key' => 'honey', 'seller' => 'aswan', 'name' => 'Sinai Mountain Honey', 'category' => 'grocery', 'art' => 'p-honey', 'price' => 380,
                        'desc' => "Raw, unblended honey from Sinai's mountain flowers.\nعسل جبلي خام من سيناء.", 'highlights' => ['Raw and unheated', '500 g jar']],
                ],
            ],
            [
                'key' => 'devices', 'industry' => 'devices', 'tenant' => 'Cairo Devices', 'name' => 'Cairo Devices', 'slug' => 'cairo-devices', 'color' => '#1d4ed8', 'hero' => 'hero-devices', 'owner' => 'Mostafa Reda',
                'headline' => 'Protect and power your phone', 'sub' => 'Screen protection, battery replacement and accessories, installed by experts in Cairo and Mansoura.',
                'about' => "Cairo Devices keeps Egypt's phones protected and running.\nكايرو ديفايسز: حماية وصيانة الهواتف.",
                'target' => 'device_component', 'whole' => 'Whole device', 'catalog' => ['Screen', 'Battery', 'Back Cover', 'Camera Lens', 'Whole device'],
                'sellers' => ['techsouq' => 'Tech Souq Cairo'],
                'partners' => [
                    ['Smart Fix Mohandessin', 'Giza', 30.0561, 31.2000, 'Quick, careful phone repairs in Mohandessin.'],
                    ['Mansoura Mobile Care', 'Mansoura', 31.0409, 31.3785, 'Screen protection and battery service in Mansoura.'],
                ],
                'products' => [
                    ['key' => 'screenfit', 'name' => 'Screen Protector Installation', 'category' => 'protection', 'art' => 'p-screenfit', 'price' => 250, 'minutes' => 20,
                        'desc' => "Bubble-free tempered glass fitted by an expert while you wait.\nتركيب حماية الشاشة بدون فقاعات.", 'highlights' => ['Bubble-free guarantee', 'Done in 20 minutes'], 'rules' => ['Screen' => 250, 'Camera Lens' => 120]],
                    ['key' => 'battery', 'name' => 'Battery Replacement', 'category' => 'repairs', 'art' => 'p-battery', 'price' => 900, 'warranty' => 1, 'minutes' => 45,
                        'desc' => "A fresh high-capacity battery with a 12-month warranty.\nاستبدال البطارية مع ضمان سنة.", 'highlights' => ['12-month warranty', 'Genuine-grade cells'], 'rules' => ['Battery' => 900]],
                    ['key' => 'skin', 'name' => 'Back Cover Skin Wrap', 'category' => 'protection', 'art' => 'p-skin', 'price' => 350, 'minutes' => 30,
                        'desc' => "A premium skin wrap cut precisely for your model.\nغلاف خلفي مقصوص بدقة.", 'highlights' => ['Precision cut', 'Residue-free removal'], 'rules' => ['Back Cover' => 350]],
                    ['key' => 'glass', 'seller' => 'techsouq', 'installation' => 'optional', 'name' => '9H Tempered Glass Screen Protector', 'category' => 'accessories', 'art' => 'p-glass', 'price' => 190, 'was' => 250,
                        'desc' => "9H hardness, oleophobic coating and an easy-fit frame.\nواقي شاشة زجاجي بصلابة 9H.", 'highlights' => ['9H hardness', 'Easy-fit frame', 'Pack of 2']],
                    ['key' => 'charger', 'seller' => 'techsouq', 'name' => '65W Fast Charger', 'category' => 'accessories', 'art' => 'p-charger', 'price' => 560, 'was' => 690,
                        'desc' => "GaN fast charger for phones, tablets and laptops.\nشاحن سريع 65 واط.", 'highlights' => ['GaN technology', 'USB-C PD', '1-year warranty']],
                    ['key' => 'earbuds', 'seller' => 'techsouq', 'name' => 'Wireless Earbuds Pro', 'category' => 'accessories', 'art' => 'p-earbuds', 'price' => 1250, 'was' => 1650,
                        'desc' => "Active noise cancelling and 28 hours of battery with the case.\nسماعات لاسلكية بخاصية عزل الضوضاء.", 'highlights' => ['Active noise cancelling', '28 h battery', 'IPX4 water resistant']],
                ],
            ],
        ];
    }
}
