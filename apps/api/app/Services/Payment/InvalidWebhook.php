<?php

namespace App\Services\Payment;

use RuntimeException;

/** The webhook was not signed correctly, is stale, or is malformed. */
class InvalidWebhook extends RuntimeException
{
}
