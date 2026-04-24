<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ApplianceScreenshot;
use Illuminate\Foundation\Events\Dispatchable;

class ApplianceScreenshotReceived
{
    use Dispatchable;

    public function __construct(public readonly ApplianceScreenshot $screenshot)
    {
    }
}
