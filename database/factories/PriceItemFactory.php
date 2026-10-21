<?php

namespace Database\Factories;

use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceItem>
 */
class PriceItemFactory extends Factory
{
    protected $model = PriceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(['Dedicated Sunucu', 'Bulut Sunucu', 'IP Tahsis', 'Backup']),
            'name' => fake()->words(3, true),
            'unit' => 'ay',
            'price' => fake()->randomFloat(2, 50, 5000),
            'currency' => 'TL',
            'kdv_dahil' => false,
            'aktif' => true,
            'notlar' => null,
        ];
    }
}
