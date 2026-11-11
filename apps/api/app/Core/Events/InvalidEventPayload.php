<?php

namespace App\Core\Events;

/** The payload does not match the event's declared contract. */
class InvalidEventPayload extends \DomainException
{
}
