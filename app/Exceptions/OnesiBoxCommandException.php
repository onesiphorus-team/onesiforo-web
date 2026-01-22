<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class OnesiBoxCommandException extends Exception
{
    public function __construct(string $message = 'Failed to send command to OnesiBox')
    {
        parent::__construct($message);
    }
}
