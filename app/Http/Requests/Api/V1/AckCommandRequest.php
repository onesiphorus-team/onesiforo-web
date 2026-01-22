<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\OnesiBox;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes command acknowledgment requests.
 *
 * @property-read string $status
 * @property-read string|null $error_code
 * @property-read string|null $error_message
 * @property-read string $executed_at
 */
class AckCommandRequest extends FormRequest
{
    /**
     * Determine if the appliance is authorized to make this request.
     * The token must belong to an OnesiBox instance.
     */
    public function authorize(): bool
    {
        /** @var AuthenticatableContract|null $tokenable */
        $tokenable = $this->user();

        if ($tokenable instanceof OnesiBox) {
            Log::debug('AckCommand authorized', ['onesibox_id' => $tokenable->id]);

            return true;
        }

        Log::warning('AckCommand unauthorized - not an OnesiBox', [
            'tokenable_type' => $tokenable !== null ? $tokenable::class : 'null',
        ]);

        return false;
    }

    /**
     * Get the authenticated OnesiBox instance.
     */
    public function onesiBox(): OnesiBox
    {
        /** @var OnesiBox $onesiBox */
        $onesiBox = $this->user();

        return $onesiBox;
    }

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
                Rule::in(['success', 'failed', 'skipped']),
            ],
            'error_code' => [
                'nullable',
                'string',
                'max:10',
            ],
            'error_message' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'executed_at' => [
                'required',
                'date',
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
            'status.required' => 'Lo stato è obbligatorio.',
            'status.in' => 'Lo stato deve essere "success", "failed" o "skipped".',
            'error_code.max' => 'Il codice errore non può superare 10 caratteri.',
            'error_message.max' => 'Il messaggio di errore non può superare 1000 caratteri.',
            'executed_at.required' => 'Il timestamp di esecuzione è obbligatorio.',
            'executed_at.date' => 'Il timestamp di esecuzione deve essere una data valida.',
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
            'error_code' => 'codice errore',
            'error_message' => 'messaggio di errore',
            'executed_at' => 'timestamp di esecuzione',
        ];
    }
}
