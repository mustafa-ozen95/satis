<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yönetim ekranları YALNIZCA yöneticide.
 *
 * "Kullanıcıları düzenle" yetkisi, kendini yönetici yapma yetkisi değildir;
 * bu yüzden kapı yetki bayrağı değil `admin` rolüdür. Kural artık Form
 * Request'in authorize()'ında duruyor — controller'a hiç ulaşmadan kapanır.
 */
class AdminLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_yonetici_olmayan_kullanici_acamaz(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.users.store'), [
                'ad' => 'Yeni Kişi',
                'eposta' => 'yeni@ornek.test',
                'sifre' => 'uzun-parola',
                'rol' => 'admin',
            ])
            ->assertRedirect(route('unauthorized'));

        $this->assertDatabaseMissing('users', ['email' => 'yeni@ornek.test']);
    }

    public function test_yonetici_olmayan_baskasini_guncelleyemez(): void
    {
        $hedef = User::factory()->create(['perm_fiyat' => false]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.users.update', $hedef), [
                'ad' => $hedef->name,
                'eposta' => $hedef->email,
                'rol' => 'admin',
                'aktif' => '1',
                'perm_fiyat' => '1',
            ])
            ->assertRedirect(route('unauthorized'));

        $this->assertFalse($hedef->fresh()->perm_fiyat);
        $this->assertNull($hedef->fresh()->rol_id, 'rol değişmemeli');
    }

    public function test_kisa_parola_kabul_edilmez(): void
    {
        /*
         * Kısa parola, parolayı denemeyle bulmayı gerçekçi kılar. Sayı tek
         * yerde: config/panel.php.
         */
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), [
                'ad' => 'Kısa Parola',
                'eposta' => 'kisa@ornek.test',
                'sifre' => '1234',
                'rol' => 'teknik',
            ])
            ->assertSessionHasErrors('sifre');

        $this->assertDatabaseMissing('users', ['email' => 'kisa@ornek.test']);
    }

    public function test_yonetici_kullanici_acabilir(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), [
                'ad' => 'Yeni Kişi',
                'eposta' => 'yeni@ornek.test',
                'sifre' => 'yeterince-uzun',
                'rol' => 'teknik',
                'perm_teklif' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'yeni@ornek.test']);
    }
}
