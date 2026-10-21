<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MERKEZLE GİRİŞİN ANAHTARI E-POSTADIR.
 *
 * Kullanıcı ekranında e-posta alanı olmazsa hiçbir hesabın adresi olmaz ve
 * merkezden gelen herkes "eşleşen hesap yok" duvarına çarpar; entegrasyon
 * yalnız kâğıt üzerinde çalışır.
 */
class MatchByEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'identity.acik' => true,
            'identity.yapilandirildi' => true,
            'identity.adres' => 'https://merkez.test',
            'identity.istemci' => 'istemci',
            'identity.sir' => 'sir',
        ]);

        Cache::flush();
    }

    public function test_yonetici_kullaniciya_eposta_verebilir(): void
    {
        $yonetici = User::factory()->admin()->create();

        $this->actingAs($yonetici)->post(route('admin.kullanicilar.store'), [
            'ad' => 'Deniz Örnek',
            'sifre' => 'yeterince-uzun',
            'rol' => 'teknik',
            'eposta' => 'deniz@ornek.test',
        ])->assertRedirect(route('admin.kullanicilar.index'));

        $this->assertDatabaseHas('users', ['name' => 'Deniz Örnek', 'email' => 'deniz@ornek.test']);
    }

    public function test_ayni_eposta_iki_hesaba_verilemez(): void
    {
        $yonetici = User::factory()->admin()->create();
        User::factory()->create(['email' => 'ayni@ornek.test']);

        /*
         * Eşleştirici "tam olarak bir karşılık" arıyor; iki hesap aynı adresi
         * taşırsa merkezden gelen kimse giremezdi.
         */
        $this->actingAs($yonetici)->post(route('admin.kullanicilar.store'), [
            'ad' => 'İkinci Kişi',
            'sifre' => 'yeterince-uzun',
            'rol' => 'teknik',
            'eposta' => 'ayni@ornek.test',
        ])->assertSessionHasErrors('eposta');
    }

    public function test_epostasi_olan_hesap_merkezle_girebilir(): void
    {
        $kullanici = User::factory()->admin()->create(['email' => 'admin@ornek.test']);

        Http::fake([
            'merkez.test/oauth/token' => Http::response(['access_token' => 'jeton']),
            'merkez.test/oauth/kullanici' => Http::response([
                'sub' => 'kimlik-1',
                'name' => 'Yönetici',
                'email' => 'admin@ornek.test',
                'email_verified' => true,
                'rol' => 'admin',
            ]),
        ]);

        $this->withSession(['kimlik_durum' => 'durum', 'kimlik_dogrulayici' => 'dogrulayici'])
            ->get('/giris/merkez/geri?state=durum&code=kod')
            ->assertRedirect(route('teklif.create'));

        $this->assertAuthenticatedAs($kullanici);
        $this->assertDatabaseHas('user_identities', ['subject' => 'kimlik-1', 'user_id' => $kullanici->id]);
    }

    public function test_baska_adresli_hesap_eslesmez(): void
    {
        /*
         * Eşleşme adresin KENDİSİNDEN geçer: merkezdeki adresin panelde
         * karşılığı yoksa hesap bulunmaz. (Hesabın kendiliğinden açılması
         * ayrı bir karar ve onu merkez veriyor.)
         */
        User::factory()->admin()->create(['email' => 'baska@ornek.test']);

        Http::fake([
            'merkez.test/oauth/token' => Http::response(['access_token' => 'jeton']),
            'merkez.test/oauth/kullanici' => Http::response([
                'sub' => 'kimlik-1',
                'name' => 'Yönetici',
                'email' => 'admin@ornek.test',
                'email_verified' => true,
                'rol' => 'admin',
                'hesap_acilabilir' => false,
            ]),
        ]);

        $this->withSession(['kimlik_durum' => 'durum', 'kimlik_dogrulayici' => 'dogrulayici'])
            ->get('/giris/merkez/geri?state=durum&code=kod')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
