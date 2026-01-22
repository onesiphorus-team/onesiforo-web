<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class OnesiBoxOfflineException extends Exception
{
    public function __construct(string $message = 'OnesiBox is offline and cannot receive commands')
    {
        parent::__construct($message);
    }
}
