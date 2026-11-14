<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OnesiBox;
use App\Services\BoxAlerts\BoxActivityMonitor;
use Illuminate\Console\Command;

/**
 * Announces on Telegram the boxes that stopped sending heartbeats (turned off or offline).
 *
 * A box is offline when it has been silent longer than OnesiBox::isOnline() allows; the alert
 * is sent once, and its return is announced by the next heartbeat.
 */
#[\Illuminate\Console\Attributes\Description('Announce the boxes that went offline')]
#[\Illuminate\Console\Attributes\Signature('onesibox:check-connections')]
class CheckOnesiBoxConnectionsCommand extends Command
{
    public function handle(BoxActivityMonitor $activityMonitor): int
    {
        $wentOffline = OnesiBox::query()
            ->where('is_active', true)
            ->where('connection_state', 'online')
            ->with('recipient.congregation')
            ->get()
            ->reject(fn (OnesiBox $onesiBox): bool => $onesiBox->isOnline());

        foreach ($wentOffline as $onesiBox) {
            $activityMonitor->connectionLost($onesiBox);
        }

        $this->info("Boxes gone offline: {$wentOffline->count()}");

        return self::SUCCESS;
    }
}
