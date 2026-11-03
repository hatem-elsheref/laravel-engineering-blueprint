<?php

declare(strict_types=1);

namespace Database\Factories\Shop;

use App\Models\User;
use App\Modules\Shop\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Widget>
 */
final class WidgetFactory extends Factory
{
    protected $model = Widget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
        ];
    }
}
