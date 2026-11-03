<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

use App\Modules\Orders\Requests\StoreOrderRequest;
use App\Modules\Orders\Requests\UpdateOrderRequest;

final readonly class OrderData
{
    public function __construct(
        public string $name,
        public int $quantity,
        public ?string $note,
    ) {}

    public static function fromRequest(StoreOrderRequest|UpdateOrderRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            quantity: $request->integer('quantity'),
            note: $request->validated('note') === null ? null : $request->string('note')->toString(),
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
            'note' => $this->note,
        ];
    }
}
