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

        $response = $this->actingAs($user)->get(route('fiyatlar.index'));

        $response->assertRedirect(route('yetkisiz'));
    }

    public function test_user_with_perm_fiyat_can_view_and_manage(): void
    {
        $user = User::factory()->create(['perm_fiyat' => true]);

        $this->actingAs($user)->get(route('fiyatlar.index'))->assertOk();

        $response = $this->actingAs($user)->post(route('fiyatlar.store'), [
            'category' => 'Test Kategori',
            'name' => 'Test Kalem',
            'unit' => 'ay',
            'price' => 100,
        ]);

        $response->assertRedirect(route('fiyatlar.index'));
        $this->assertDatabaseHas('price_items', ['name' => 'Test Kalem']);
    }

    public function test_admin_can_manage_price_items_regardless_of_perm_flag(): void
    {
        $admin = User::factory()->admin()->create(['perm_fiyat' => false]);

        $this->actingAs($admin)->get(route('fiyatlar.index'))->assertOk();
    }

    public function test_deleting_a_price_item_requires_perm_fiyat(): void
    {
        $item = PriceItem::factory()->create();
        $user = User::factory()->create(['perm_fiyat' => false]);

        $response = $this->actingAs($user)->post(route('fiyatlar.destroy', $item));

        $response->assertRedirect(route('yetkisiz'));
        $this->assertDatabaseHas('price_items', ['id' => $item->id]);
    }
}
