<?php

namespace App\Services\SocialFetch;

use App\Enums\FetchStatus;
use RuntimeException;

class FetchException extends RuntimeException
{
    public function __construct(public readonly FetchStatus $status, string $message)
    {
        parent::__construct($message);
    }
}
