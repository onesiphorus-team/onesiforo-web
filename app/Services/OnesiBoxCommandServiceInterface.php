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

    /**
     * Invia comando di riavvio del dispositivo.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendRebootCommand(OnesiBox $onesiBox): void;

    /**
     * Invia comando per uscire dalla chiamata Zoom.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendLeaveZoomCommand(OnesiBox $onesiBox): void;

    /**
     * Invia comando per avviare chiamata Zoom tramite URL.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendZoomUrlCommand(OnesiBox $onesiBox, string $zoomUrl, string $participantName): void;

    /**
     * Invia comando generico di riproduzione media.
     *
     * @param  string  $mediaType  'audio' or 'video'
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendMediaCommand(OnesiBox $onesiBox, string $mediaUrl, string $mediaType): void;

    /**
     * Invia comando di riavvio del servizio OnesiBox.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendRestartServiceCommand(OnesiBox $onesiBox): void;

    /**
     * Invia comando di riproduzione media con ID sessione.
     *
     * Used by the session advance mechanism to link commands to a playback session.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendSessionMediaCommand(OnesiBox $onesiBox, string $mediaUrl, string $mediaType, string $sessionId): void;

    /**
     * Invia comando di impostazione volume.
     *
     * @param  int  $level  Volume level (0-100, step 5)
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendVolumeCommand(OnesiBox $onesiBox, int $level): void;
}
