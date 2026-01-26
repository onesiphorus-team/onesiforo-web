<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * API error codes for consistent error reporting across Onesiforo and OnesiBox.
 *
 * Standard codes (E001-E010) are used by both server and client.
 * Client-specific codes (E1xx) are used only by OnesiBox client.
 */
enum ApiErrorCode: string
{
    // ===========================================
    // Standard Error Codes (E001-E010)
    // ===========================================

    /** Invalid or missing authentication token */
    case InvalidToken = 'E001';

    /** Resource not found (command, appliance, etc.) */
    case NotFound = 'E002';

    /** Unauthorized access (appliance disabled, wrong owner) */
    case Unauthorized = 'E003';

    /** Command has expired before execution */
    case CommandExpired = 'E004';

    /** Media URL is invalid or not whitelisted */
    case InvalidMediaUrl = 'E005';

    /** Command type is not supported */
    case UnsupportedCommandType = 'E006';

    /** Appliance is offline and unreachable */
    case ApplianceOffline = 'E007';

    /** Rate limit exceeded */
    case RateLimitExceeded = 'E008';

    /** Internal server/client error */
    case InternalError = 'E009';

    /** Command execution timeout */
    case ExecutionTimeout = 'E010';

    // ===========================================
    // Client-Specific Error Codes (E1xx)
    // Used by OnesiBox client only
    // ===========================================

    /** Media handler failed (playback error) */
    case MediaHandlerFailed = 'E101';

    /** Zoom handler failed (meeting error) */
    case ZoomHandlerFailed = 'E102';

    /** Volume handler failed (audio error) */
    case VolumeHandlerFailed = 'E103';

    /** System handler failed (reboot/shutdown error) */
    case SystemHandlerFailed = 'E104';

    /** Diagnostics handler failed (system info error) */
    case DiagnosticsHandlerFailed = 'E105';

    /** Service handler failed (restart service error) */
    case ServiceHandlerFailed = 'E106';

    /** Invalid command structure received */
    case InvalidCommandStructure = 'E107';

    /** Invalid command payload */
    case InvalidPayload = 'E108';

    /**
     * Get the HTTP status code associated with this error.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidToken => 401,
            self::NotFound => 404,
            self::Unauthorized => 403,
            self::CommandExpired => 410,
            self::InvalidMediaUrl, self::UnsupportedCommandType, self::InvalidCommandStructure, self::InvalidPayload => 422,
            self::ApplianceOffline => 503,
            self::RateLimitExceeded => 429,
            self::InternalError, self::MediaHandlerFailed, self::ZoomHandlerFailed, self::VolumeHandlerFailed,
            self::SystemHandlerFailed, self::DiagnosticsHandlerFailed, self::ServiceHandlerFailed => 500,
            self::ExecutionTimeout => 408,
        };
    }

    /**
     * Get a human-readable description of the error.
     */
    public function description(): string
    {
        return match ($this) {
            self::InvalidToken => 'Token di autenticazione non valido o mancante',
            self::NotFound => 'Risorsa non trovata',
            self::Unauthorized => 'Accesso non autorizzato',
            self::CommandExpired => 'Comando scaduto',
            self::InvalidMediaUrl => 'URL media non valido o non autorizzato',
            self::UnsupportedCommandType => 'Tipo di comando non supportato',
            self::ApplianceOffline => 'Appliance non raggiungibile',
            self::RateLimitExceeded => 'Limite richieste superato',
            self::InternalError => 'Errore interno',
            self::ExecutionTimeout => 'Timeout esecuzione comando',
            self::MediaHandlerFailed => 'Errore riproduzione media',
            self::ZoomHandlerFailed => 'Errore connessione Zoom',
            self::VolumeHandlerFailed => 'Errore controllo volume',
            self::SystemHandlerFailed => 'Errore comando di sistema',
            self::DiagnosticsHandlerFailed => 'Errore recupero diagnostica',
            self::ServiceHandlerFailed => 'Errore riavvio servizio',
            self::InvalidCommandStructure => 'Struttura comando non valida',
            self::InvalidPayload => 'Payload comando non valido',
        };
    }
}
