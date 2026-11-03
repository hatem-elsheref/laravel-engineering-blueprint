<?php

declare(strict_types=1);

namespace App\Modules\Shop\Data;

use App\Modules\Shop\Requests\StoreWidgetRequest;
use App\Modules\Shop\Requests\UpdateWidgetRequest;

final readonly class WidgetData
{
    public function __construct(
        public string $title,
    ) {}

    public static function fromRequest(StoreWidgetRequest|UpdateWidgetRequest $request): self
    {
        return new self(
            title: $request->string('title')->toString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
        ];
    }
}
