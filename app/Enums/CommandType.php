<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CommandType: string implements HasColor, HasIcon, HasLabel
{
    // Media
    case PlayMedia = 'play_media';
    case StopMedia = 'stop_media';
    case PauseMedia = 'pause_media';
    case ResumeMedia = 'resume_media';
    case SetVolume = 'set_volume';

    // Video Calls
    case JoinZoom = 'join_zoom';
    case LeaveZoom = 'leave_zoom';
    case StartJitsi = 'start_jitsi';
    case StopJitsi = 'stop_jitsi';

    // Communication
    case SpeakText = 'speak_text';
    case ShowMessage = 'show_message';

    // System
    case Reboot = 'reboot';
    case Shutdown = 'shutdown';
    case RestartService = 'restart_service';

    // Remote Access
    case StartVnc = 'start_vnc';
    case StopVnc = 'stop_vnc';

    // Configuration
    case UpdateConfig = 'update_config';

    // Diagnostics
    case GetSystemInfo = 'get_system_info';
    case GetLogs = 'get_logs';

    /**
     * Get the default expiration time in minutes for this command type.
     *
     * - Urgent commands (reboot, shutdown, restart_service, VNC): 5 minutes
     * - Media commands: 60 minutes
     * - Configuration commands: 1440 minutes (24 hours)
     */
    public function defaultExpiresInMinutes(): int
    {
        return match ($this) {
            self::Reboot,
            self::Shutdown,
            self::RestartService,
            self::StartVnc,
            self::StopVnc => 5,

            self::UpdateConfig => 1440,

            self::GetSystemInfo,
            self::GetLogs => 5,

            default => 60,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PlayMedia => __('Riproduci media'),
            self::StopMedia => __('Ferma media'),
            self::PauseMedia => __('Pausa media'),
            self::ResumeMedia => __('Riprendi media'),
            self::SetVolume => __('Imposta volume'),
            self::JoinZoom => __('Entra in Zoom'),
            self::LeaveZoom => __('Esci da Zoom'),
            self::StartJitsi => __('Avvia Jitsi'),
            self::StopJitsi => __('Termina Jitsi'),
            self::SpeakText => __('Sintesi vocale'),
            self::ShowMessage => __('Mostra messaggio'),
            self::Reboot => __('Riavvia'),
            self::Shutdown => __('Spegni'),
            self::RestartService => __('Riavvia servizio'),
            self::StartVnc => __('Avvia VNC'),
            self::StopVnc => __('Termina VNC'),
            self::UpdateConfig => __('Aggiorna configurazione'),
            self::GetSystemInfo => __('Info sistema'),
            self::GetLogs => __('Recupera log'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PlayMedia => 'heroicon-o-play',
            self::StopMedia => 'heroicon-o-stop',
            self::PauseMedia => 'heroicon-o-pause',
            self::ResumeMedia => 'heroicon-o-play-circle',
            self::SetVolume => 'heroicon-o-speaker-wave',
            self::JoinZoom, self::StartJitsi => 'heroicon-o-video-camera',
            self::LeaveZoom, self::StopJitsi => 'heroicon-o-video-camera-slash',
            self::SpeakText => 'heroicon-o-microphone',
            self::ShowMessage => 'heroicon-o-chat-bubble-left',
            self::Reboot => 'heroicon-o-arrow-path',
            self::Shutdown => 'heroicon-o-power',
            self::RestartService => 'heroicon-o-arrow-path-rounded-square',
            self::StartVnc => 'heroicon-o-computer-desktop',
            self::StopVnc => 'heroicon-o-x-circle',
            self::UpdateConfig => 'heroicon-o-cog-6-tooth',
            self::GetSystemInfo => 'heroicon-o-cpu-chip',
            self::GetLogs => 'heroicon-o-document-text',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PlayMedia, self::ResumeMedia => 'success',
            self::StopMedia, self::PauseMedia => 'warning',
            self::SetVolume => 'info',
            self::JoinZoom, self::StartJitsi => 'primary',
            self::LeaveZoom, self::StopJitsi => 'gray',
            self::SpeakText, self::ShowMessage => 'info',
            self::Reboot, self::RestartService => 'warning',
            self::Shutdown => 'danger',
            self::StartVnc, self::StopVnc => 'gray',
            self::UpdateConfig => 'info',
            self::GetSystemInfo => 'info',
            self::GetLogs => 'info',
        };
    }
}
