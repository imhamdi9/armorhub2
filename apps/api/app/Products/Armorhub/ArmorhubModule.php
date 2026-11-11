<?php

namespace App\Products\Armorhub;

use App\Core\Modules\Module;
use App\Core\Modules\ModuleContext;
use App\Core\Modules\ModuleManifest;
use App\Products\Armorhub\Blueprints\ArmorhubBlueprints;
use App\Products\Armorhub\Blueprints\CatalogStep;
use App\Products\Armorhub\Blueprints\MarketStep;
use App\Products\Armorhub\Blueprints\ProductsStep;
use App\Products\Armorhub\Blueprints\StoreStep;
use App\Products\Armorhub\Subscribers\OrderNotificationSubscriber;
use App\Products\Armorhub\Subscribers\PayoutNotificationSubscriber;
use App\Products\Armorhub\Subscribers\SellerLedgerSubscriber;
use App\Products\Armorhub\Workflows\GrantWarranty;
use App\Products\Armorhub\Workflows\OrderWorkflow;
use App\Products\Armorhub\Workflows\RejectOpenQuotes;

/**
 * ArmorHub, the first product: request a service or product, compare quotes, pay, fulfil, warranty.
 * It owns its events and reactions and reaches the platform only through the module contract.
 */
class ArmorhubModule implements Module
{
    /** The steps of an order's life that are announced as events. */
    public const ORDER_EVENTS = [
        'created' => 'A customer placed an order',
        'quote_submitted' => 'A partner sent a quote',
        'quote_accepted' => 'The customer accepted a quote',
        'paid' => 'The order was paid',
        'scheduled' => 'An appointment was scheduled',
        'started' => 'The work started',
        'completed' => 'The work was completed',
        'cancelled' => 'The order was cancelled',
        'shipped' => 'A shipment was booked',
        'shipment_out_for_delivery' => 'The parcel is out for delivery',
        'shipment_delivered' => 'The parcel was delivered',
        'shipment_failed' => 'A delivery attempt failed',
        'shipment_returned' => 'The parcel was returned',
    ];

    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            id: 'armorhub',
            name: 'ArmorHub',
            version: '1.0.0',
            description: 'Marketplace for services and products: quotes, payment, fulfilment and warranty.',
        );
    }

    public function register(ModuleContext $context): void
    {
        foreach (self::ORDER_EVENTS as $type => $description) {
            $context->event("order.{$type}", [
                'order_event_id' => 'int',
                'order_id' => 'int',
                'type' => 'string',
                'data' => 'array?',
            ], $description);
        }

        $context->event('payout.created', ['payout_id' => 'int', 'company_id' => 'int', 'amount' => 'float', 'currency' => 'string'], 'A payout to a seller was prepared');
        $context->event('payout.paid', ['payout_id' => 'int', 'company_id' => 'int', 'amount' => 'float', 'currency' => 'string', 'reference' => 'string?'], 'A payout to a seller was paid');

        $context->subscribe(OrderNotificationSubscriber::class);
        $context->subscribe(SellerLedgerSubscriber::class);
        $context->subscribe(PayoutNotificationSubscriber::class);

        $context->action(GrantWarranty::class);
        $context->action(RejectOpenQuotes::class);
        $context->workflow(OrderWorkflow::definition());

        foreach ([MarketStep::class, StoreStep::class, CatalogStep::class, ProductsStep::class] as $step) {
            $context->blueprintStep($step);
        }
        foreach (ArmorhubBlueprints::all() as $blueprint) {
            $context->blueprint($blueprint);
        }
    }
}
