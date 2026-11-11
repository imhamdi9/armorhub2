<?php

namespace App\Products\Armorhub;

use App\Core\Modules\ModuleContext;

/** What admins may edit on ArmorHub's public site and app. Everything else stays locked. */
final class Surfaces
{
    public static function register(ModuleContext $c): void
    {
        $c->surface('site.brand', 'Brand', [
            'name' => ['type' => 'text', 'label' => 'Platform name', 'default' => 'ArmorHub', 'max' => 40, 'required' => false],
            'tagline' => ['type' => 'text', 'label' => 'Tagline', 'max' => 120],
            'logo_url' => ['type' => 'url', 'label' => 'Logo address (https)'],
            'brand_color' => ['type' => 'color', 'label' => 'Accent colour (buttons)', 'default' => '#9fe870'],
            'dark_color' => ['type' => 'color', 'label' => 'Dark colour (panels, footer)', 'default' => '#163300'],
        ], 'The name, logo and colours of the public site.', public: true);

        $sections = [];
        foreach ([
            'show_partner_strip' => 'Trusted partners strip', 'show_industries' => 'Industries and products', 'show_quote_feature' => 'Compare quotes section',
            'show_dashboard_feature' => 'For businesses section', 'show_value_props' => 'Three promises', 'show_partners' => 'Partners by industry', 'show_use_cases' => 'Use cases',
            'show_why' => 'Why choose us', 'show_how' => 'How it works', 'show_bento' => 'Numbers band', 'show_developers' => 'For developers', 'show_compat' => 'Works with your tools',
            'show_markets' => 'Local from day one', 'show_faq' => 'FAQ',
        ] as $key => $label) {
            $sections[$key] = ['type' => 'bool', 'label' => "Show: {$label}", 'default' => true];
        }

        $c->surface('site.home', 'Home page', [
            'badge_text' => ['type' => 'text', 'label' => 'Badge above the headline', 'max' => 60],
            'hero_title' => ['type' => 'text', 'label' => 'Headline', 'max' => 120],
            'hero_subtitle' => ['type' => 'longtext', 'label' => 'Sub-headline', 'max' => 400],
            'primary_cta' => ['type' => 'text', 'label' => 'Main button text', 'max' => 40],
            'banner_title' => ['type' => 'text', 'label' => 'Closing banner: title', 'max' => 100],
            'banner_text' => ['type' => 'longtext', 'label' => 'Closing banner: text', 'max' => 300],
            'banner_cta' => ['type' => 'text', 'label' => 'Closing banner: button', 'max' => 40],
            'footer_blurb' => ['type' => 'longtext', 'label' => 'Footer description', 'max' => 300],
            'faq' => ['type' => 'list', 'label' => 'FAQ (replaces the built-in questions when not empty)', 'maxItems' => 12, 'items' => [
                'question' => ['type' => 'text', 'label' => 'Question', 'max' => 150, 'required' => true],
                'answer' => ['type' => 'longtext', 'label' => 'Answer', 'max' => 600, 'required' => true],
            ]],
        ] + $sections, 'The platform home page: wording, sections and FAQ.', public: true);

        $c->surface('site.navigation', 'Header menu', [
            'nav_markets' => ['type' => 'text', 'label' => 'Markets link', 'max' => 30],
            'nav_partners' => ['type' => 'text', 'label' => 'Partners link', 'max' => 30],
            'nav_how' => ['type' => 'text', 'label' => 'How it works link', 'max' => 30],
            'nav_faq' => ['type' => 'text', 'label' => 'FAQ link', 'max' => 30],
        ], 'The words in the public header menu.', public: true);

        $c->surface('app.sidebar', 'Dashboard menu', [
            'labels' => ['type' => 'list', 'label' => 'Menu item names', 'maxItems' => 40, 'items' => [
                'item' => ['type' => 'text', 'label' => 'Menu item (as it is now, in English)', 'max' => 40, 'required' => true],
                'label' => ['type' => 'text', 'label' => 'Shown as', 'max' => 40, 'required' => true],
            ]],
        ], 'Rename items in the signed-in menu.', public: false);
    }
}
