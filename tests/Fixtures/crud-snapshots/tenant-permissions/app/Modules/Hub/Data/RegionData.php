<?php

declare(strict_types=1);

namespace App\Modules\Hub\Data;

use App\Modules\Hub\Requests\StoreRegionRequest;
use App\Modules\Hub\Requests\UpdateRegionRequest;

final readonly class RegionData
{
    public function __construct(
        public string $name,
    ) {}

    public static function fromRequest(StoreRegionRequest|UpdateRegionRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
