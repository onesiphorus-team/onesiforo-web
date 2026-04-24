<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreScreenshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'captured_at' => ['required', 'date', 'before_or_equal:now', 'after:-5 minutes'],
            'width'       => ['required', 'integer', 'between:320,4096'],
            'height'      => ['required', 'integer', 'between:180,2160'],
            'screenshot'  => ['required', 'file', 'mimes:webp', 'max:2048'],
        ];
    }
}
