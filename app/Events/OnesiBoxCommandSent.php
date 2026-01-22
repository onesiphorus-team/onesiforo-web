<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OnesiBoxCommandSent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public OnesiBox $onesiBox,
        public User $user,
        public Command $command
    ) {}
}
