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
                'min:0',
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
            'temperature.min' => 'La temperatura deve essere almeno 0°C.',
            'temperature.max' => 'La temperatura non può superare 150°C.',
            'uptime.min' => 'L\'uptime non può essere negativo.',
            'current_media.url.required_with' => 'L\'URL del media è obbligatorio quando si fornisce current_media.',
            'current_media.url.url' => 'L\'URL del media deve essere un URL valido.',
            'current_media.type.required_with' => 'Il tipo di media è obbligatorio quando si fornisce current_media.',
            'current_media.type.in' => 'Il tipo di media deve essere audio o video.',
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
