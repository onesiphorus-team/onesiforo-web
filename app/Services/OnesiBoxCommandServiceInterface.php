<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\OnesiBoxCommandException;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\OnesiBox;

interface OnesiBoxCommandServiceInterface
{
    /**
     * Invia comando di riproduzione audio.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendAudioCommand(OnesiBox $onesiBox, string $audioUrl): void;

    /**
     * Invia comando di riproduzione video.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendVideoCommand(OnesiBox $onesiBox, string $videoUrl): void;

    /**
     * Invia comando di avvio chiamata Zoom.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendZoomCommand(OnesiBox $onesiBox, string $meetingId, ?string $password = null): void;

    /**
     * Invia comando di stop/terminazione.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendStopCommand(OnesiBox $onesiBox): void;
}
