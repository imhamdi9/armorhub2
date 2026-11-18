<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ProductLanguages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Older listings kept the English text and its Arabic translation glued in one field: they are pulled apart, one language per field. */
class ProductLanguagesTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    public function test_a_mixed_description_is_split_and_demo_products_get_arabic_names(): void
    {
        $this->buildMarketplace();
        $p = Product::create(['name' => '65W Fast Charger', 'slug' => 'charger-65', 'base_price' => 560, 'description' => 'Charges a laptop and a phone at once. شاحن سريع يشحن اللابتوب والهاتف معاً.']);

        $this->assertSame(1, app(ProductLanguages::class)->backfill());
        $p->refresh();
        $this->assertSame('Charges a laptop and a phone at once.', $p->description);
        $this->assertSame('شاحن سريع يشحن اللابتوب والهاتف معاً.', $p->description_ar);
        $this->assertSame('شاحن سريع 65 وات', $p->name_ar);
        $this->assertSame(0, app(ProductLanguages::class)->backfill());                              // safe to run again
        $this->assertSame(['Only English here.', ''], ProductLanguages::split('Only English here.'));
    }
}
