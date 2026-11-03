<?php

declare(strict_types=1);

namespace Database\Factories\Shop;

use App\Modules\Shop\Models\PurchaseOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
final class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 1, 1000),
            'active' => fake()->boolean(),
            'tags' => [],
            'shipped_at' => CarbonImmutable::now(),
        ];
    }
}
