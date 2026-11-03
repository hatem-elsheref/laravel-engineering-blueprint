<?php

declare(strict_types=1);

namespace App\Modules\Shop\Requests;

use App\Modules\Shop\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

final class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'between:-2147483648,2147483647'],
            'price' => ['nullable', 'numeric', 'between:-9999999999.99,9999999999.99'],
            'active' => ['required', 'boolean'],
            'tags' => ['present', 'array', 'max:100'],
            'tags.*' => ['nullable', 'string', 'max:255'],
            'shipped_at' => ['nullable', 'date'],
        ];
    }
}
