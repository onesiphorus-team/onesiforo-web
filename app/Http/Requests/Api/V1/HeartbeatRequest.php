<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Concerns\AuthorizesAsOnesiBox;
use App\Enums\OnesiBoxStatus;
use App\Models\OnesiBox;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates heartbeat data sent by OnesiBox appliances.
 *
 * @property-read int $onesibox_id
 * @property-read string $status
 * @property-read int|null $cpu_usage
 * @property-read int|null $memory_usage
 * @property-read int|null $disk_usage
 * @property-read float|null $temperature
 * @property-read int|null $uptime
 * @property-read array<string, mixed>|null $current_media
 */
class HeartbeatRequest extends FormRequest
{
    use AuthorizesAsOnesiBox;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::enum(OnesiBoxStatus::class),
            ],
            'cpu_usage' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'memory_usage' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'disk_usage' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'temperature' => [
                'nullable',
                'numeric',
                'min:-50',
                'max:150',
            ],
            'uptime' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'current_media' => [
                'nullable',
                'array',
            ],
            'current_media.url' => [
                'required_with:current_media',
                'string',
                'url',
            ],
            'current_media.type' => [
                'required_with:current_media',
                'string',
                Rule::in(['audio', 'video']),
            ],
            'current_media.position' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'current_media.duration' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'current_media.title' => [
                'nullable',
                'string',
                'max:255',
            ],
            'current_meeting' => [
                'nullable',
                'array',
            ],
            'current_meeting.meeting_id' => [
                'nullable',
                'string',
                'max:50',
            ],
            'current_meeting.meeting_url' => [
                'nullable',
                'string',
                'url',
                'max:500',
            ],
            'current_meeting.joined_at' => [
                'nullable',
                'string',
                'date',
            ],
            'volume' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            // App version
            'app_version' => [
                'nullable',
                'string',
                'max:20',
            ],
            // Network info
            'network' => [
                'nullable',
                'array',
            ],
            'network.type' => [
                'nullable',
                'string',
                Rule::in(['wifi', 'ethernet']),
            ],
            'network.interface' => [
                'nullable',
                'string',
                'max:20',
            ],
            'network.ip' => [
                'nullable',
                'ip',
            ],
            'network.netmask' => [
                'nullable',
                'string',
                'max:45',
            ],
            'network.gateway' => [
                'nullable',
                'ip',
            ],
            'network.mac' => [
                'nullable',
                'string',
                'max:17',
            ],
            'network.dns' => [
                'nullable',
                'array',
                'max:3',
            ],
            'network.dns.*' => [
                'nullable',
                'string',
            ],
            // WiFi info
            'wifi' => [
                'nullable',
                'array',
            ],
            'wifi.ssid' => [
                'nullable',
                'string',
                'max:64',
            ],
            'wifi.signal_dbm' => [
                'nullable',
                'numeric',
                'min:-150',
                'max:0',
            ],
            'wifi.signal_percent' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'wifi.channel' => [
                'nullable',
                'integer',
                'min:1',
                'max:200',
            ],
            'wifi.frequency' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'wifi.security' => [
                'nullable',
                'string',
                'max:50',
            ],
            // Detailed memory
            'memory' => [
                'nullable',
                'array',
            ],
            'memory.total' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'memory.used' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'memory.free' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'memory.available' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'memory.buffers' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'memory.cached' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'memory.percent' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Lo stato dell\'appliance è obbligatorio.',
            'status.Illuminate\Validation\Rules\Enum' => 'Lo stato deve essere uno tra: idle, playing, calling, error.',
            'cpu_usage.min' => 'L\'utilizzo CPU deve essere almeno 0%.',
            'cpu_usage.max' => 'L\'utilizzo CPU non può superare 100%.',
            'memory_usage.min' => 'L\'utilizzo memoria deve essere almeno 0%.',
            'memory_usage.max' => 'L\'utilizzo memoria non può superare 100%.',
            'disk_usage.min' => 'L\'utilizzo disco deve essere almeno 0%.',
            'disk_usage.max' => 'L\'utilizzo disco non può superare 100%.',
            'temperature.min' => 'La temperatura deve essere almeno -50°C.',
            'temperature.max' => 'La temperatura non può superare 150°C.',
            'uptime.min' => 'L\'uptime non può essere negativo.',
            'current_media.url.required_with' => 'L\'URL del media è obbligatorio quando si fornisce current_media.',
            'current_media.url.url' => 'L\'URL del media deve essere un URL valido.',
            'current_media.type.required_with' => 'Il tipo di media è obbligatorio quando si fornisce current_media.',
            'current_media.type.in' => 'Il tipo di media deve essere audio o video.',
            'current_media.title.max' => 'Il titolo del media non può superare 255 caratteri.',
            'current_meeting.meeting_id.max' => 'L\'ID meeting non può superare 50 caratteri.',
            'current_meeting.meeting_url.url' => 'L\'URL del meeting deve essere un URL valido.',
            'current_meeting.meeting_url.max' => 'L\'URL del meeting non può superare 500 caratteri.',
            'current_meeting.joined_at.date' => 'La data di ingresso deve essere una data valida.',
            'volume.min' => 'Il volume deve essere almeno 0.',
            'volume.max' => 'Il volume non può superare 100.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'stato',
            'cpu_usage' => 'utilizzo CPU',
            'memory_usage' => 'utilizzo memoria',
            'disk_usage' => 'utilizzo disco',
            'temperature' => 'temperatura',
            'uptime' => 'tempo di attività',
            'current_media' => 'media corrente',
            'current_media.url' => 'URL media',
            'current_media.type' => 'tipo media',
            'current_media.position' => 'posizione media',
            'current_media.duration' => 'durata media',
            'current_media.title' => 'titolo media',
            'current_meeting' => 'meeting corrente',
            'current_meeting.meeting_id' => 'ID meeting',
            'current_meeting.meeting_url' => 'URL meeting',
            'current_meeting.joined_at' => 'data ingresso meeting',
            'volume' => 'volume',
            'app_version' => 'versione app',
            'network' => 'rete',
            'network.type' => 'tipo connessione',
            'network.interface' => 'interfaccia di rete',
            'network.ip' => 'indirizzo IP',
            'network.netmask' => 'netmask',
            'network.gateway' => 'gateway',
            'network.mac' => 'indirizzo MAC',
            'network.dns' => 'server DNS',
            'wifi' => 'WiFi',
            'wifi.ssid' => 'SSID WiFi',
            'wifi.signal_dbm' => 'segnale WiFi (dBm)',
            'wifi.signal_percent' => 'segnale WiFi (%)',
            'wifi.channel' => 'canale WiFi',
            'wifi.frequency' => 'frequenza WiFi',
            'memory' => 'memoria',
            'memory.total' => 'memoria totale',
            'memory.used' => 'memoria usata',
            'memory.free' => 'memoria libera',
            'memory.available' => 'memoria disponibile',
            'memory.buffers' => 'buffers',
            'memory.cached' => 'cached',
        ];
    }

    /**
     * Prepare the data for validation by injecting the OnesiBox ID from the token.
     */
    protected function prepareForValidation(): void
    {
        /** @var AuthenticatableContract|null $tokenable */
        $tokenable = $this->user();

        if ($tokenable instanceof OnesiBox) {
            $this->merge([
                'onesibox_id' => $tokenable->id,
            ]);
        }
    }
}
