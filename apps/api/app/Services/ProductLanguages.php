<?php

namespace App\Services;

use App\Models\Product;

/**
 * Every product speaks both languages, each in its own field. Older listings (and the demo catalogue) carried the English text and its Arabic
 * translation glued together in one description: this pulls them apart, and gives the demo products their Arabic names.
 */
class ProductLanguages
{
    /** English name => Arabic name, for the demo catalogue. */
    public const DEMO_NAMES = [
        'Ultimate Plus' => 'التميّز بلس', 'Ceramic Care Kit' => 'طقم العناية بالسيراميك', 'Paint Protection Film (PPF)' => 'فيلم حماية الطلاء (PPF)',
        'Nano Ceramic Coating' => 'طلاء النانو سيراميك', 'Heat-Rejection Window Tint' => 'تظليل زجاج عازل للحرارة', 'Premium Microfibre Detailing Set' => 'طقم تنظيف ميكروفايبر فاخر',
        'Headlight Restoration' => 'تجديد المصابيح الأمامية', 'Full Interior and Exterior Detailing' => 'تنظيف شامل للمقصورة والهيكل', 'Colour Change Vinyl Wrap' => 'تغيير لون السيارة بالفينيل',
        'Waterless Wash and Wax Spray' => 'بخاخ غسيل وتلميع بدون ماء', 'Wheel and Tyre Cleaner' => 'منظف الجنوط والإطارات', '4K Dash Camera with Hardwire Kit' => 'كاميرا داش كام 4K مع طقم توصيل مباشر',
        'Oud Car Air Freshener' => 'معطر سيارة بالعود', 'Leather Seat Conditioner' => 'مرطّب مقاعد الجلد', 'Hydrafacial Session' => 'جلسة هيدرافيشيال',
        'Bridal Henna Design' => 'نقش حنّة للعروس', 'Keratin Smoothing Treatment' => 'جلسة تمليس بالكيراتين', 'Siwa Salt Face Scrub' => 'مقشّر وجه بملح سيوة',
        'Cold-Pressed Black Seed Oil' => 'زيت حبة البركة المعصور على البارد', 'Fayoum Rose Water' => 'ماء ورد الفيوم', 'Natural Egyptian Henna Powder' => 'مسحوق الحنّة المصرية الطبيعية',
        'Gel Manicure and Pedicure' => 'مانيكير وباديكير جل', 'Rose Glow Facial' => 'جلسة تنظيف بشرة بالورد', 'Siwa Salt Body Scrub and Massage' => 'مقشّر جسم بملح سيوة مع مساج',
        'Prickly Pear Seed Oil' => 'زيت بذور التين الشوكي', 'Jasmine Body Butter' => 'زبدة الجسم بالياسمين', 'Natural Kohl Eyeliner' => 'كحل طبيعي',
        'Deep Home Cleaning' => 'تنظيف المنزل بعمق', 'AC Service and Deep Clean' => 'صيانة وتنظيف المكيف', 'Plumbing Visit' => 'زيارة سباكة',
        'Khan El-Khalili Brass Lantern (Fanous)' => 'فانوس نحاس من خان الخليلي', 'Hand-Woven Palm Basket' => 'سلة سعف نخيل منسوجة يدوياً', 'Hand-Painted Papyrus Art' => 'لوحة بردي مرسومة يدوياً',
        'Fayoum Pottery Vase' => 'مزهرية فخار من الفيوم', 'Egyptian Cotton Bath Towel Set' => 'طقم مناشف حمام من القطن المصري', 'Aswan Hibiscus (Karkade) Tea' => 'شاي كركديه أسوان',
        'Sinai Mountain Honey' => 'عسل جبل سيناء', 'Smart Door Lock' => 'قفل باب ذكي', 'Pest Control Visit' => 'زيارة مكافحة حشرات', 'Interior Painting' => 'دهانات داخلية',
        'Hand-Woven Kilim Cushion Cover' => 'غطاء وسادة كليم منسوج يدوياً', 'Bakhoor Incense Set' => 'طقم بخور', 'Egyptian Cotton Bedsheet Set' => 'طقم ملاءات سرير من القطن المصري',
        'Siwa Dates (1 kg)' => 'تمر سيوة (1 كجم)', 'Egyptian Dukkah Spice Mix' => 'خلطة دُقّة مصرية', 'Screen Protector Installation' => 'تركيب واقي شاشة',
        'Battery Replacement' => 'تغيير البطارية', 'Back Cover Skin Wrap' => 'ملصق حماية للغطاء الخلفي', '9H Tempered Glass Screen Protector' => 'واقي شاشة زجاج مقوّى 9H',
        '65W Fast Charger' => 'شاحن سريع 65 وات', 'Wireless Earbuds Pro' => 'سماعات لاسلكية برو', 'Screen Replacement' => 'تغيير الشاشة',
        'Water Damage Diagnostic and Clean' => 'فحص وتنظيف أضرار المياه', 'Camera Lens Protector Fitting' => 'تركيب واقي عدسة الكاميرا', 'Shockproof Phone Case' => 'جراب هاتف مقاوم للصدمات',
        '20,000 mAh Power Bank' => 'باور بانك 20,000 مللي أمبير', 'Braided USB-C Cable (2 m)' => 'كابل USB-C مضفّر (2 متر)', 'Cabin Air Filter (Corolla and Elantra)' => 'فلتر مكيّف (كورولا وإلنترا)',
    ];

    /** Splits a text that holds an English part followed by an Arabic one. @return array{0: string, 1: string} [English, Arabic] */
    public static function split(?string $text): array
    {
        $text = trim((string) $text);
        if ($text === '' || ! preg_match('/\p{Arabic}/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            return [$text, ''];
        }
        $at = $m[0][1];

        return [trim(substr($text, 0, $at)), trim(substr($text, $at))];
    }

    /** @return int products changed */
    public function backfill(): int
    {
        $changed = 0;
        foreach (Product::withoutGlobalScopes()->get() as $p) {
            $update = [];
            [$en, $ar] = self::split($p->description);
            if ($ar !== '' && $en !== '' && ! $p->description_ar) {
                $update = ['description' => $en, 'description_ar' => $ar];
            }
            $attrs = (array) $p->attributes;
            $en = array_values($attrs['highlights'] ?? []);
            if ($en !== [] && empty($attrs['highlights_ar']) && collect($en)->every(fn ($h) => isset(DemoHighlightsAr::MAP[$h]))) {
                $attrs['highlights_ar'] = array_map(fn ($h) => DemoHighlightsAr::MAP[$h], $en);
                $update['attributes'] = $attrs;
            }
            if (! $p->name_ar && isset(self::DEMO_NAMES[$p->name])) {
                $update['name_ar'] = self::DEMO_NAMES[$p->name];
            }
            if ($update) {
                $p->forceFill($update)->save();
                $changed++;
            }
        }

        return $changed;
    }
}
