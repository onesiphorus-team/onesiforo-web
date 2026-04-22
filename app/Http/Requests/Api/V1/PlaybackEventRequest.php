<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Concerns\AuthorizesAsOnesiBox;
use App\Enums\PlaybackEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes playback event requests.
 *
 * @property-read string $event
 * @property-read string $media_url
 * @property-read string $media_type
 * @property-read int|null $position
 * @property-read int|null $duration
 * @property-read string|null $error_message
 * @property-read string|null $error_code
 * @property-read string|null $session_id
 */
class PlaybackEventRequest extends FormRequest
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
            'event' => [
                'required',
                'string',
                Rule::enum(PlaybackEventType::class),
            ],
            'media_url' => [
                'required',
                'string',
                'url',
                'max:2048',
            ],
            'media_type' => [
                'required',
                'string',
                Rule::in(['audio', 'video']),
            ],
            'position' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'duration' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'error_message' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'error_code' => [
                'nullable',
                'string',
                'regex:/^E\d{3}$/',
                'max:10',
            ],
            'session_id' => [
                'nullable',
                'string',
                'uuid',
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
            'event.required' => 'Il tipo di evento è obbligatorio.',
            'event.Illuminate\Validation\Rules\Enum' => 'Il tipo di evento deve essere uno tra: started, paused, resumed, stopped, completed, error.',
            'media_url.required' => 'L\'URL del media è obbligatorio.',
            'media_url.url' => 'L\'URL del media deve essere un URL valido.',
            'media_url.max' => 'L\'URL del media non può superare 2048 caratteri.',
            'media_type.required' => 'Il tipo di media è obbligatorio.',
            'media_type.in' => 'Il tipo di media deve essere audio o video.',
            'position.min' => 'La posizione non può essere negativa.',
            'position.integer' => 'La posizione deve essere un numero intero.',
            'duration.min' => 'La durata non può essere negativa.',
            'duration.integer' => 'La durata deve essere un numero intero.',
            'error_message.max' => 'Il messaggio di errore non può superare 1000 caratteri.',
            'error_code.regex' => 'Il codice errore deve essere nel formato E### (es. E110, E112).',
            'error_code.max' => 'Il codice errore non può superare 10 caratteri.',
            'session_id.uuid' => 'Il session_id deve essere un UUID valido.',
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
            'event' => 'tipo di evento',
            'media_url' => 'URL media',
            'media_type' => 'tipo media',
            'position' => 'posizione',
            'duration' => 'durata',
            'error_message' => 'messaggio di errore',
            'error_code' => 'codice errore',
            'session_id' => 'ID sessione',
        ];
    }
}
