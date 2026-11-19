<?php

namespace App\Services\Shipping;

/** The carrier cannot be used right now (not configured, unreachable, refused). The message is safe to show staff. */
class ShippingUnavailable extends \RuntimeException
{
}
