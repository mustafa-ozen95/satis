<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Giriş ekranı NEDEN giremediğini söyler.
 *
 * Yönlendirmeyle dönen hatalar (merkezden red, pasif hesap, doğrulama)
 * oturumdaki torbada durur; şablon onu çizmezse kullanıcı bomboş bir giriş
 * sayfası görür ve sebebini bilmez.
 */
class GirisHatasiGorunurTest extends TestCase
{
    use RefreshDatabase;

    public function test_pasife_alinan_kullaniciya_sebebi_soylenir(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->get(route('teklif.create'))->assertOk();

        $kullanici->forceFill(['aktif' => false])->save();

        /* Gerçek akış: ara katman dışarı atıyor, giriş ekranı sebebi yazıyor. */
        $this->followingRedirects()
            ->get(route('teklif.create'))
            ->assertOk()
            ->assertSee('Hesabınız etkin değil', false);
    }

    public function test_dogrulama_hatasi_ekranda_cizilir(): void
    {
        $this->from(route('login'))
            ->followingRedirects()
            ->post(route('login.attempt'), ['name' => '', 'password' => ''])
            ->assertOk()
            ->assertSee('alert--danger', false);
    }

    /**
     * Yanlış parola YÖNLENDİRMEYLE döner ve mesaj giriş ekranında çizilir.
     *
     * Kardeş panellerdeki kalıp: hata doğrulama hatası olarak oturuma
     * konur, giriş ekranı onu `x-ui.flash` ile gösterir; böylece giriş
     * ekranı şablondan ayrı bir değişken taşımak zorunda kalmaz.
     */
    public function test_yanlis_parola_mesaji_cizilir(): void
    {
        $kullanici = User::factory()->create();

        /*
         * `assertSessionHasErrors` OTURUMDAKİ HATAYI TÜKETİYOR: aynı
         * sınamada ardından gelen istek artık hiçbir hata görmüyor. Burada
         * yalnızca ekranda çizildiğine bakılıyor; hatanın oturuma konduğunu
         * AuthenticationTest ayrıca sınıyor.
         */
        $this->from(route('login'))->post(route('login.attempt'), [
            'email' => $kullanici->email,
            'password' => 'yanlis-parola',
        ])->assertRedirect(route('login'));

        $this->get(route('login'))->assertSee('Bu bilgilerle giriş yapılamadı.');
    }

    public function test_hata_yokken_uyari_kutusu_cizilmez(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('alert--danger');
    }
}
