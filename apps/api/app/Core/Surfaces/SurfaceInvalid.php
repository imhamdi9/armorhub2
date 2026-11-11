<?php

namespace App\Core\Surfaces;

class SurfaceInvalid extends \DomainException
{
    /** @param array<string, list<string>> $errors field => messages */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The content is not valid.');
    }
}
