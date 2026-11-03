<?php

declare(strict_types=1);

namespace App\Modules\Hub\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('region')) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
