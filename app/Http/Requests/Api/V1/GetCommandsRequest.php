<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\OnesiBox;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes requests for retrieving commands.
 *
 * @property-read string|null $status
 * @property-read int|null $limit
 */
class GetCommandsRequest extends FormRequest
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
            Log::debug('GetCommands authorized', ['onesibox_id' => $tokenable->id]);

            return true;
        }

        Log::warning('GetCommands unauthorized - not an OnesiBox', [
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
                'nullable',
                'string',
                Rule::in(['pending', 'all']),
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
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
            'status.in' => 'Lo stato deve essere "pending" o "all".',
            'limit.min' => 'Il limite deve essere almeno 1.',
            'limit.max' => 'Il limite non può superare 50.',
            'limit.integer' => 'Il limite deve essere un numero intero.',
        ];
    }

    /**
     * Get the default status filter value.
     */
    public function getStatusFilter(): string
    {
        return $this->input('status', 'pending');
    }

    /**
     * Get the limit value with default.
     */
    public function getLimit(): int
    {
        return (int) $this->input('limit', 10);
    }
}
