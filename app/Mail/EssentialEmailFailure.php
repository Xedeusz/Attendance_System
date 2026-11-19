<?php

namespace App\Mail;

use RuntimeException;

class EssentialEmailFailure extends RuntimeException
{
    public function __construct(string $reason, public readonly string $outcome = 'failed', public readonly bool $retryable = false)
    {
        parent::__construct($reason);
    }
}
