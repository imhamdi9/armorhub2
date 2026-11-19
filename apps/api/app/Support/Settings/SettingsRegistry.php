<?php

namespace App\Support\Settings;

/**
 * The catalogue of settings. Core definitions live in `core()`; modules such as payment
 * providers add their own at boot via `register()`.
 */
class SettingsRegistry
{
    /** @var array<string, Definition> */
    private array $definitions = [];

    public function __construct()
    {
        foreach ($this->core() as $definition) {
            $this->register($definition);
        }
    }

    public function register(Definition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): Definition
    {
        return $this->definitions[$key] ?? throw new \InvalidArgumentException("Unknown setting [{$key}].");
    }

    /** @return array<string, Definition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return list<Definition> */
    private function core(): array
    {
        return [
            // ---- Platform -------------------------------------------------
            new Definition('platform.default_market', 'platform', 'Default market (country)', 'select', fn () => config('markets.default'),
                'Sets currency, VAT and phone rules for tenants that have not chosen a country. Leave empty to use the single currency below.',
                options: collect(config('markets.markets'))->mapWithKeys(fn ($m, $code) => [$code => $m['name']['en']])->all()),
            new Definition('platform.currency', 'platform', 'Currency (ISO code)', 'string', fn () => config('platform.currency'),
                'Three-letter code such as USD or SAR. Applies to new orders.', rules: ['size:3', 'alpha']),
            new Definition('platform.commission_percentage', 'platform', 'Commission (% of each order)', 'float',
                fn () => config('platform.commission_percentage'), 'Taken from the order total when it is paid.', rules: ['min:0', 'max:100']),
            new Definition('platform.customer_fee_percentage', 'platform', 'Customer platform fee (% of the invoice)', 'float',
                fn () => config('platform.customer_fee_percentage'), 'Added to what the customer pays and shown on the payment page. Not part of the partner commission.', rules: ['min:0', 'max:30']),
            // ---- Installation (centre jobs) ---------------------------------
            new Definition('installation.fee_percent', 'installation', 'Installation fee (% of the centre price), all markets', 'float', 5,
                'Taken from the centre\'s price when the job is released. A market below can override it.', rules: ['min:0', 'max:30']),
            new Definition('installation.objection_hours', 'installation', 'Customer objection window (hours)', 'int', 48,
                'After the centre submits its proof, the customer has this long to object before the money is released.', rules: ['min:1', 'max:336']),
            new Definition('installation.offer_hours', 'installation', 'How long a request stays open for offers (hours)', 'int', 48, rules: ['min:1', 'max:336']),
            new Definition('installation.warranty_days', 'installation', 'Platform warranty on a released job (days)', 'int', 180, 'Only for jobs done and paid through the platform.', rules: ['min:0', 'max:3650']),
            new Definition('installation.min_withdrawal', 'installation', 'Smallest centre withdrawal (in the market currency)', 'float', 100, rules: ['min:0']),
            new Definition('installation.proxy_number', 'installation', 'Platform phone number for substitute calls', 'string', '',
                'Customers and centres call this number plus a temporary extension, so real numbers are never shared. Connect it to a telephony provider that bridges calls.'),
            new Definition('installation.points_per_unit', 'installation', 'Loyalty points earned per currency unit paid', 'float', 0.1, rules: ['min:0', 'max:10']),
            new Definition('installation.point_value', 'installation', 'Value of one loyalty point (currency units)', 'float', 0.25, 'A discount can never exceed the platform fee of the job.', rules: ['min:0', 'max:10']),
            new Definition('products.require_approval', 'platform', 'A seller\'s product goes live only after the catalogue team approves it', 'bool', true, 'Sellers submit products for review; shoppers see a product only once it is approved. Editing the name, description, photos or category of an approved product sends it back for review.'),
            new Definition('products.require_both_languages', 'platform', 'Sellers must give every product a name and description in both English and Arabic', 'bool', true, 'Shoppers see the English copy when the platform is in English and the Arabic copy when it is in Arabic, so both are needed.'),
            new Definition('payouts.hold_days', 'platform', 'Days earnings are held after delivery before a seller can be paid (0 = available as soon as the goods are delivered)', 'int', 0, 'Choose 0 to release earnings the moment the goods are delivered, or a number of days (for example the return window) to hold them. A market can set its own.', rules: ['min:0', 'max:90']),
            new Definition('payouts.min_amount', 'platform', 'Smallest payout a seller may ask for (currency units)', 'float', 100, 'Sellers can ask for a payout once this much is available.', rules: ['min:0']),
            new Definition('orders.ship_remind_days', 'platform', 'Days a paid order may wait before the seller is reminded (0 = off)', 'int', 3, rules: ['min:0', 'max:60']),
            new Definition('orders.ship_cancel_days', 'platform', 'Days after which an unshipped paid order is cancelled and refunded (0 = off)', 'int', 7, rules: ['min:0', 'max:90']),
            new Definition('loyalty.goods_points_per_unit', 'platform', 'Points earned per currency unit on goods that arrived', 'float', 0.05, rules: ['min:0', 'max:10']),
            new Definition('loyalty.max_redeem_percent', 'platform', 'Largest share of a purchase points may pay for (%)', 'float', 20, rules: ['min:0', 'max:100']),
            new Definition('loyalty.referral_points', 'platform', 'Points each person gets when a referred friend receives a first order', 'int', 50, rules: ['min:0', 'max:100000']),
            new Definition('loyalty.goodwill_max_points', 'platform', 'Most goodwill points support may give a customer for one order', 'int', 500, rules: ['min:0', 'max:100000']),
            new Definition('loyalty.cart_reminder_hours', 'platform', 'Hours before a forgotten cart is reminded (0 turns reminders off)', 'int', 24, rules: ['min:0', 'max:720']),
            new Definition('installation.fee_discount.silver', 'installation', 'Fee reduction for silver centres (percentage points)', 'float', 0.5, rules: ['min:0', 'max:10']),
            new Definition('installation.fee_discount.gold', 'installation', 'Fee reduction for gold centres (percentage points)', 'float', 1, rules: ['min:0', 'max:10']),
            ...collect(config('markets.markets'))->map(fn ($m, $code) => new Definition(
                "installation.fee_percent.{$code}", 'installation', "Installation fee: {$m['name']['en']} (%)", 'float', null,
                'Leave empty to use the all-markets fee above.', rules: ['min:0', 'max:30']))->values()->all(),
            ...collect(config('markets.markets'))->flatMap(fn ($m, $code) => [
                new Definition("shipping.flat_fee.{$code}", 'shipping', "Delivery fee per seller: {$m['name']['en']} (currency units)", 'float', null, 'What the customer pays for each seller\x27s parcel. Empty or 0 means free delivery.', rules: ['min:0']),
                new Definition("shipping.free_over.{$code}", 'shipping', "Free delivery over: {$m['name']['en']} (currency units)", 'float', null, 'A seller\x27s part of the cart at or above this amount ships free. Empty means never free.', rules: ['min:0']),
                new Definition("shipping.express_fee.{$code}", 'shipping', "Express delivery extra fee per seller: {$m['name']['en']} (currency units)", 'float', null, 'What the customer adds to choose faster delivery. Empty means express delivery is not offered.', rules: ['min:0']),
                new Definition("shipping.express_eta_days.{$code}", 'shipping', "Express delivery time: {$m['name']['en']} (days)", 'int', null, 'Shown next to the express choice.', rules: ['min:1', 'max:30']),
                new Definition("shipping.eta_days.{$code}", 'shipping', "Usual delivery time: {$m['name']['en']} (days)", 'int', null, 'Shown to the customer in the cart.', rules: ['min:1', 'max:60']),
            ])->values()->all(),
            // ---- Marketing: the ad platforms' ids per market (empty = off). Secrets are never shown again after saving. ----
            new Definition('marketing.meta_pixel_id', 'marketing', 'Meta (Facebook and Instagram) Pixel ID', 'string', '', 'From Meta Events Manager. Empty turns Meta tracking off.', rules: ['nullable', 'regex:/^[0-9]{5,20}$/']),
            new Definition('marketing.meta_access_token', 'marketing', 'Meta Conversions API access token', 'secret', null, 'Lets the server report purchases to Meta even when the browser blocks the pixel.'),
            new Definition('marketing.meta_test_code', 'marketing', 'Meta test event code', 'string', '', 'While set, events go to the Test Events tab of Meta only. Remove it to go live.', rules: ['nullable', 'regex:/^[A-Za-z0-9_-]{3,40}$/']),
            new Definition('marketing.tiktok_pixel_id', 'marketing', 'TikTok Pixel ID', 'string', '', 'From TikTok Events Manager. Empty turns TikTok tracking off.', rules: ['nullable', 'regex:/^[A-Za-z0-9]{5,40}$/']),
            new Definition('marketing.tiktok_access_token', 'marketing', 'TikTok Events API access token', 'secret', null, 'Lets the server report purchases to TikTok.'),
            new Definition('marketing.tiktok_test_code', 'marketing', 'TikTok test event code', 'string', '', 'While set, events show in the Test Events tab of TikTok only.', rules: ['nullable', 'regex:/^[A-Za-z0-9_-]{3,40}$/']),
            new Definition('marketing.ga4_id', 'marketing', 'Google Analytics 4 measurement ID', 'string', '', 'Looks like G-XXXXXXXXXX. Also used for YouTube and Google Ads audiences.', rules: ['nullable', 'regex:/^G-[A-Z0-9]{4,20}$/']),
            new Definition('marketing.ga4_api_secret', 'marketing', 'GA4 Measurement Protocol secret', 'secret', null, 'Lets the server report purchases to Google Analytics.'),
            new Definition('marketing.google_ads_id', 'marketing', 'Google Ads conversion ID', 'string', '', 'Looks like AW-123456789.', rules: ['nullable', 'regex:/^AW-[0-9]{5,15}$/']),
            new Definition('marketing.google_ads_purchase_label', 'marketing', 'Google Ads purchase conversion label', 'string', '', rules: ['nullable', 'regex:/^[A-Za-z0-9_-]{5,60}$/']),
            new Definition('marketing.google_ads_lead_label', 'marketing', 'Google Ads lead conversion label (seller or centre applications)', 'string', '', rules: ['nullable', 'regex:/^[A-Za-z0-9_-]{5,60}$/']),
            new Definition('marketing.meta_ad_account_id', 'marketing', 'Meta ad account ID', 'string', '', 'The number of the ad account (Business Settings > Ad accounts). Read only: the platform reads spend, it never changes a campaign.', rules: ['nullable', 'regex:/^(act_)?[0-9]{5,20}$/']),
            new Definition('marketing.meta_ads_token', 'marketing', 'Meta ads reading token', 'secret', null, 'A system user token with permission to read ads (ads_read).'),
            new Definition('marketing.tiktok_advertiser_id', 'marketing', 'TikTok advertiser ID', 'string', '', 'The advertiser ID of the TikTok ads account.', rules: ['nullable', 'regex:/^[0-9]{5,25}$/']),
            new Definition('marketing.tiktok_ads_token', 'marketing', 'TikTok ads reporting token', 'secret', null, 'An access token that can read reports of that advertiser.'),
            new Definition('marketing.daily_spend_alert', 'marketing', 'Tell me when a day\'s ad spend passes (currency units)', 'float', null, 'Checked each time the spend is read. Empty means no limit.', rules: ['nullable', 'numeric', 'min:0']),
            new Definition('payments.cod_enabled', 'payments', 'Cash on delivery is available', 'bool', false, 'Customers can pay in cash at the door for shipped goods. The courier hands the cash over at delivery.'),
            new Definition('payments.cod_max_amount', 'payments', 'Largest purchase paid in cash on delivery (currency units)', 'float', null, 'Empty means no limit.', rules: ['min:0']),
            new Definition('platform.commission_split.platform', 'platform', 'Commission share: platform', 'float',
                fn () => config('platform.commission_split.platform'), 'The three shares must add up to 1 (e.g. 0.35).', rules: ['min:0', 'max:1']),
            new Definition('platform.commission_split.company', 'platform', 'Commission share: partner company', 'float',
                fn () => config('platform.commission_split.company'), rules: ['min:0', 'max:1']),
            new Definition('platform.commission_split.partner', 'platform', 'Commission share: partner', 'float',
                fn () => config('platform.commission_split.partner'), rules: ['min:0', 'max:1']),
            new Definition('platform.registration_open', 'platform', 'Customer signup is open', 'bool', true,
                'Turn off to stop new accounts being created (e.g. during an attack).'),
            new Definition('platform.partner_signup_open', 'platform', 'Partner applications are open', 'bool', true,
                'Turn off to stop new partner applications (e.g. during an attack).'),
            new Definition('platform.seller_signup_open', 'platform', 'Seller applications are open', 'bool', true,
                'Turn off to stop new seller applications.'),
            new Definition('platform.orders_enabled', 'platform', 'New orders are accepted', 'bool', true,
                'Turn off to pause ordering; existing orders continue normally.'),

            new Definition('content.require_approval', 'content', 'Content changes need a second admin\'s approval', 'bool', false,
                'When on, publishing site content (home page, theme, wording) is sent to another administrator to approve. Keep it off if you are the only admin.'),

            // ---- Security -------------------------------------------------
            new Definition('security.require_email_verification', 'security', 'Require verified email to order', 'select', 'auto',
                '"Automatic" requires it only when real email delivery (SMTP) is configured.',
                ['auto' => 'Automatic', 'yes' => 'Always', 'no' => 'Never']),
            new Definition('security.block_disposable_emails', 'security', 'Block disposable email addresses', 'bool', true,
                'Rejects signups from well-known throwaway email services.'),
            new Definition('security.blocked_email_domains', 'security', 'Extra blocked email domains', 'text', '',
                'One domain per line or comma-separated, e.g. spam.example'),
            new Definition('security.signups_per_ip_per_hour', 'security', 'Signups allowed per IP per hour', 'int', 5,
                rules: ['min:1', 'max:1000']),
            new Definition('security.max_open_orders', 'security', 'Max unpaid orders per customer', 'int', 5,
                'Stops one account flooding partners with quote requests.', rules: ['min:1', 'max:100']),
            new Definition('security.min_signup_seconds', 'security', 'Minimum seconds to fill the signup form', 'int', 3,
                'Bots submit instantly; humans do not.', rules: ['min:0', 'max:60']),
            new Definition('security.max_active_tokens', 'security', 'Max signed-in devices per account', 'int', 10,
                'Oldest sessions are signed out beyond this.', rules: ['min:1', 'max:100']),
            new Definition('security.captcha_provider', 'security', 'Bot challenge on signup/login', 'select', 'none',
                'Cloudflare Turnstile, hCaptcha and reCAPTCHA are supported. Keys below.',
                ['none' => 'Off', 'turnstile' => 'Cloudflare Turnstile', 'hcaptcha' => 'hCaptcha', 'recaptcha' => 'Google reCAPTCHA']),
            new Definition('security.captcha_site_key', 'security', 'Bot challenge site key (public)', 'string', ''),
            new Definition('security.captcha_secret', 'security', 'Bot challenge secret key', 'secret', null),
        ];
    }
}
