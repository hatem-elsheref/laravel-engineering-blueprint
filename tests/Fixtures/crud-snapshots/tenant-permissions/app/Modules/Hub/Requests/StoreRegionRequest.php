<?php

declare(strict_types=1);

namespace App\Modules\Hub\Requests;

use App\Modules\Hub\Models\Region;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Region::class) ?? false;
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
