<?php

namespace Tests\Feature;

use App\Models\PriceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PriceItemPolicy + perm_fiyat Gate'inin testi: fiyat kalemlerine yalnızca
 * perm_fiyat izni olan (ya da yönetici) erişir.
 */
class PriceItemAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_perm_fiyat_is_redirected(): void
    {
        $user = User::factory()->create(['perm_fiyat' => false]);

        $response = $this->actingAs($user)->get(route('prices.index'));

        $response->assertRedirect(route('unauthorized'));
    }

    public function test_user_with_perm_fiyat_can_view_and_manage(): void
    {
        $user = User::factory()->create(['perm_fiyat' => true]);

        $this->actingAs($user)->get(route('prices.index'))->assertOk();

        $response = $this->actingAs($user)->post(route('prices.store'), [
            'category' => 'Test Kategori',
            'name' => 'Test Kalem',
            'unit' => 'ay',
            'price' => 100,
        ]);

        $response->assertRedirect(route('prices.index'));
        $this->assertDatabaseHas('price_items', ['name' => 'Test Kalem']);
    }

    public function test_admin_can_manage_price_items_regardless_of_perm_flag(): void
    {
        $admin = User::factory()->admin()->create(['perm_fiyat' => false]);

        $this->actingAs($admin)->get(route('prices.index'))->assertOk();
    }

    public function test_deleting_a_price_item_requires_perm_fiyat(): void
    {
        $item = PriceItem::factory()->create();
        $user = User::factory()->create(['perm_fiyat' => false]);

        $response = $this->actingAs($user)->post(route('prices.destroy', $item));

        $response->assertRedirect(route('unauthorized'));
        $this->assertDatabaseHas('price_items', ['id' => $item->id]);
    }
}
