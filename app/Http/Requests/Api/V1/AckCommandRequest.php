<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Concerns\AuthorizesAsOnesiBox;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes command acknowledgment requests.
 *
 * @property-read string $status
 * @property-read string|null $error_code
 * @property-read string|null $error_message
 * @property-read string $executed_at
 * @property-read array<string, mixed>|null $result
 */
class AckCommandRequest extends FormRequest
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
            'result' => [
                'nullable',
                'array',
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
            'result.array' => 'Il risultato deve essere un oggetto JSON.',
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
            'result' => 'risultato',
        ];
    }
}
