<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'aktif' => true,
            'perm_teklif' => true,
            'perm_bilgi' => true,
            'perm_fiyat' => true,
        ];
    }

    /** Yönetici rolü; yoksa yaratılır (sınamalar göçe bağlı kalmasın). */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'rol_id' => Role::firstOrCreate(
                ['slug' => 'admin'],
                ['label' => 'Yönetici', 'badge' => 'purple', 'yonetici' => true, 'yerlesik' => true, 'sira' => 1],
            )->id,
        ]);
    }

    /** Belirli bir rolle. */
    public function rol(string $slug): static
    {
        return $this->state(fn (array $attributes): array => [
            'rol_id' => Role::where('slug', $slug)->value('id'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'aktif' => false,
        ]);
    }
}
