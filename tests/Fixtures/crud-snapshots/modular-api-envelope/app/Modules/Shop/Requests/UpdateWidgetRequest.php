<?php

declare(strict_types=1);

namespace App\Modules\Shop\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateWidgetRequest extends FormRequest
{
    /**
     * The policy's answer as is, so another user's widget is "not found" (404), not "forbidden".
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('widget'));
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
