<?php

declare(strict_types=1);

namespace App\Modules\Shop\Requests;

use App\Modules\Shop\Models\Widget;
use Illuminate\Foundation\Http\FormRequest;

final class StoreWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Widget::class) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
