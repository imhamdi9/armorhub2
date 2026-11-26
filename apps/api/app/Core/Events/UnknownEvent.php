<?php

namespace App\Core\Events;

/** Publishing an event nobody declared: events are a governed contract, not free text. */
class UnknownEvent extends \DomainException
{
}
