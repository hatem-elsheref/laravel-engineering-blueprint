<?php

declare(strict_types=1);

namespace App\Modules\Shop\Data;

use App\Modules\Shop\Requests\StorePurchaseOrderRequest;
use App\Modules\Shop\Requests\UpdatePurchaseOrderRequest;
use Carbon\CarbonImmutable;

final readonly class PurchaseOrderData
{
    public function __construct(
        public string $name,
        public int $quantity,
        public ?float $price,
        public bool $active,
        public array $tags,
        public ?CarbonImmutable $shippedAt,
    ) {}

    public static function fromRequest(StorePurchaseOrderRequest|UpdatePurchaseOrderRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            quantity: $request->integer('quantity'),
            price: $request->validated('price') === null ? null : $request->float('price'),
            active: $request->boolean('active'),
            tags: array_values(array_filter((array) $request->validated('tags'), fn ($value) => $value !== null && $value !== '')),
            shippedAt: $request->validated('shipped_at') === null ? null : CarbonImmutable::parse($request->validated('shipped_at'))->setTimezone(config('app.timezone')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'active' => $this->active,
            'tags' => $this->tags,
            'shipped_at' => $this->shippedAt,
        ];
    }
}
