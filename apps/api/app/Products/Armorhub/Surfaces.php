<?php

namespace App\Products\Armorhub;

use App\Core\Modules\ModuleContext;

/** What admins may edit on ArmorHub's public site and app. Everything else stays locked. */
final class Surfaces
{
    /** Fonts come from a fixed list of safe stacks (no external font files, nothing an admin types is ever used as CSS). */
    public const FONTS = ['default', 'system', 'serif', 'rounded', 'mono'];

    /** Home page blocks an admin can reorder (the hero stays first and the closing banner last). */
    public const SECTIONS = [
        'partner_strip', 'industries', 'stores', 'quote_feature', 'dashboard_feature', 'value_props', 'partners', 'use_cases', 'why', 'how', 'bento',
        'developers', 'compat', 'markets', 'faq',
    ];

    public static function register(ModuleContext $c): void
    {
        $c->surface('site.brand', 'Brand', [
            'name' => ['type' => 'text', 'label' => 'Platform name', 'default' => 'ArmorHub', 'max' => 40, 'required' => false],
            'tagline' => ['type' => 'text', 'label' => 'Tagline', 'max' => 120],
            'logo_url' => ['type' => 'url', 'label' => 'Logo address (https)'],
            'brand_color' => ['type' => 'color', 'label' => 'Accent colour (buttons)', 'default' => '#9fe870'],
            'dark_color' => ['type' => 'color', 'label' => 'Dark colour (panels, footer)', 'default' => '#163300'],
            'font_heading' => ['type' => 'select', 'label' => 'Headline font', 'options' => self::FONTS],
            'font_body' => ['type' => 'select', 'label' => 'Text font', 'options' => self::FONTS],
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
            'hero_image_url' => ['type' => 'url', 'label' => 'Hero image (https address; replaces the illustration)'],
            'banner_image_url' => ['type' => 'url', 'label' => 'Closing banner background image (https address)'],
            'section_order' => ['type' => 'list', 'label' => 'Section order (sections not listed follow in the built-in order)', 'maxItems' => 15, 'items' => [
                'section' => ['type' => 'select', 'label' => 'Section', 'options' => self::SECTIONS, 'required' => true],
            ]],
            'footer_blurb' => ['type' => 'longtext', 'label' => 'Footer description', 'max' => 300],
            'quote_title' => ['type' => 'text', 'label' => 'Compare quotes: title', 'max' => 120],
            'quote_text' => ['type' => 'longtext', 'label' => 'Compare quotes: text', 'max' => 300],
            'quote_cta' => ['type' => 'text', 'label' => 'Compare quotes: button', 'max' => 40],
            'biz_title' => ['type' => 'text', 'label' => 'For businesses: title', 'max' => 120],
            'biz_text' => ['type' => 'longtext', 'label' => 'For businesses: text', 'max' => 300],
            'biz_cta' => ['type' => 'text', 'label' => 'For businesses: button', 'max' => 40],
            'values' => ['type' => 'list', 'label' => 'Promises (replaces the three built-in ones when not empty)', 'maxItems' => 4, 'items' => [
                'title' => ['type' => 'text', 'label' => 'Title', 'max' => 60, 'required' => true],
                'text' => ['type' => 'longtext', 'label' => 'Text', 'max' => 200, 'required' => true],
            ]],
            'usecases_title' => ['type' => 'text', 'label' => 'Use cases: title', 'max' => 80],
            'usecases_text' => ['type' => 'longtext', 'label' => 'Use cases: text', 'max' => 250],
            'why_title' => ['type' => 'text', 'label' => 'Why choose us: title', 'max' => 100],
            'why_card_title' => ['type' => 'text', 'label' => 'Why choose us: card title', 'max' => 80],
            'why_card_text' => ['type' => 'longtext', 'label' => 'Why choose us: card text', 'max' => 250],
            'why_points' => ['type' => 'list', 'label' => 'Why choose us: bullet points (replaces built-in when not empty)', 'maxItems' => 8, 'items' => [
                'text' => ['type' => 'text', 'label' => 'Point', 'max' => 100, 'required' => true],
            ]],
            'how_title' => ['type' => 'text', 'label' => 'How it works: title', 'max' => 80],
            'how_steps' => ['type' => 'list', 'label' => 'How it works: steps (replaces built-in when not empty)', 'maxItems' => 6, 'items' => [
                'title' => ['type' => 'text', 'label' => 'Step title', 'max' => 60, 'required' => true],
                'text' => ['type' => 'longtext', 'label' => 'Step text', 'max' => 200, 'required' => true],
            ]],
            'bento_title' => ['type' => 'text', 'label' => 'Numbers band: title', 'max' => 120],
            'markets_title' => ['type' => 'text', 'label' => 'ArmorHub Markets section: title', 'max' => 60],
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

        $c->surface('app.messages', 'Notification wording', [
            'templates' => ['type' => 'list', 'label' => 'Message wording (a message without an entry keeps the built-in wording)', 'maxItems' => 15, 'items' => [
                'kind' => ['type' => 'select', 'label' => 'Message', 'options' => \App\Services\MessageTemplates::KINDS, 'required' => true],
                'title' => ['type' => 'text', 'label' => 'Title (placeholders: {name} {order_id} {price} {partner} {tracking} {amount} {reference})', 'max' => 100],
                'body' => ['type' => 'longtext', 'label' => 'Text (same placeholders)', 'max' => 400],
            ]],
        ], 'Wording of in-app and email notifications, in English. Placeholders in {braces} are filled in.', public: false);

        $c->surface('app.sidebar', 'Dashboard menu', [
            'labels' => ['type' => 'list', 'label' => 'Menu item names', 'maxItems' => 40, 'items' => [
                'item' => ['type' => 'text', 'label' => 'Menu item (as it is now, in English)', 'max' => 40, 'required' => true],
                'label' => ['type' => 'text', 'label' => 'Shown as', 'max' => 40, 'required' => true],
            ]],
        ], 'Rename items in the signed-in menu.', public: false);
    }
}
