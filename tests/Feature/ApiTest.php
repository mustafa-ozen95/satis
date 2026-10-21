<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateTeklifJob;
use App\Models\ApiToken;
use App\Models\BilgiKaydi;
use App\Models\PriceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * API: jetonla girilir, jeton sahibini AŞAMAZ.
 *
 * Jeton bir kez üretilip bir yere yazılıyor ve orada unutuluyor. Sahibinin
 * yetkisi daraltıldığında jeton da daralmazsa, kimsenin haberi olmayan bir
 * arka kapı kalırdı.
 */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} */
    private function jeton(array $yetenekler = ['fiyat' => ['view']], ?User $sahip = null): array
    {
        $sahip ??= User::factory()->rol('muhasebe')->create();
        $sir = ApiToken::mint();

        ApiToken::create([
            'name' => 'deneme',
            'user_id' => $sahip->id,
            'token_hash' => $sir['hash'],
            'prefix' => $sir['prefix'],
            'abilities' => $yetenekler,
            'expires_at' => now()->addYear(),
        ]);

        return [$sahip, $sir['plain']];
    }

    private function istek(string $jeton): array
    {
        return ['Authorization' => 'Bearer '.$jeton, 'Accept' => 'application/json'];
    }

    public function test_jetonsuz_istek_reddedilir(): void
    {
        PriceItem::factory()->create();

        $this->getJson('/api/v1/fiyatlar')->assertUnauthorized();
    }

    public function test_gecerli_jetonla_fiyatlar_okunur(): void
    {
        [, $jeton] = $this->jeton();
        PriceItem::factory()->create(['name' => 'Kabin', 'category' => 'Alan']);

        $this->getJson('/api/v1/fiyatlar', $this->istek($jeton))
            ->assertOk()
            ->assertJsonPath('data.0.ad', 'Kabin')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_jeton_kendi_listesini_asamaz(): void
    {
        /* Sahibi bilgi bankasını görebiliyor ama jetonun listesinde yok. */
        [, $jeton] = $this->jeton(['fiyat' => ['view']]);
        BilgiKaydi::create(['baslik' => 'Bir', 'kategori' => 'Alan', 'metin' => 'metin']);

        $this->getJson('/api/v1/bilgi', $this->istek($jeton))->assertForbidden();
    }

    public function test_jeton_sahibini_asamaz(): void
    {
        /*
         * Jetonun listesinde var ama SAHİBİNİN yetkisi yok: kesişim boş.
         * Sahibinin yetkisi daraltılınca jeton da daralmalı.
         */
        $sahip = User::factory()->rol('muhasebe')->create(['perm_fiyat' => false]);
        [, $jeton] = $this->jeton(['fiyat' => ['view']], $sahip);

        PriceItem::factory()->create();

        $this->getJson('/api/v1/fiyatlar', $this->istek($jeton))->assertForbidden();
    }

    public function test_iptal_edilmis_jeton_calismaz(): void
    {
        [, $jeton] = $this->jeton();
        ApiToken::query()->update(['revoked_at' => now()]);

        $this->getJson('/api/v1/fiyatlar', $this->istek($jeton))->assertUnauthorized();
    }

    public function test_suresi_dolmus_jeton_calismaz(): void
    {
        [, $jeton] = $this->jeton();
        ApiToken::query()->update(['expires_at' => now()->subDay()]);

        $this->getJson('/api/v1/fiyatlar', $this->istek($jeton))->assertUnauthorized();
    }

    public function test_pasif_hesabin_jetonu_calismaz(): void
    {
        [$sahip, $jeton] = $this->jeton();
        $sahip->forceFill(['aktif' => false])->save();

        $this->getJson('/api/v1/fiyatlar', $this->istek($jeton))->assertUnauthorized();
    }

    public function test_teklif_kuyruga_alinir(): void
    {
        Queue::fake();

        [$sahip, $jeton] = $this->jeton(['teklif' => ['create', 'view']]);

        $yanit = $this->postJson('/api/v1/teklif', ['talep' => 'Bir kabin fiyatı'], $this->istek($jeton))
            ->assertStatus(202)
            ->assertJsonPath('data.durum', 'bekliyor');

        Queue::assertPushed(GenerateTeklifJob::class);

        /* Kayıt SAHİPLİDİR: "kim istedi" sorusu sonradan sorulacak. */
        $this->assertDatabaseHas('conversations', [
            'id' => $yanit->json('data.id'),
            'user_id' => $sahip->id,
        ]);
    }

    public function test_kendi_teklifi_mesajlariyla_okunur(): void
    {
        [$sahip, $jeton] = $this->jeton(['teklif' => ['view']]);

        $konusma = $sahip->conversations()->create(['ilk_talep' => 'Bir kabin fiyatı']);
        $konusma->messages()->create(['role' => 'user', 'content' => 'Bir kabin fiyatı']);
        $konusma->messages()->create(['role' => 'assistant', 'content' => 'Teklif metni']);

        $this->getJson("/api/v1/teklif/{$konusma->id}", $this->istek($jeton))
            ->assertOk()
            ->assertJsonPath('data.durum', 'bekliyor')
            ->assertJsonPath('data.mesajlar.1.rol', 'assistant')
            ->assertJsonPath('data.mesajlar.1.metin', 'Teklif metni');
    }

    public function test_baskasinin_teklifi_okunamaz(): void
    {
        [, $jeton] = $this->jeton(['teklif' => ['view']]);

        $baskasi = User::factory()->create();
        $konusma = $baskasi->conversations()->create(['ilk_talep' => 'gizli', 'durum' => 'hazir']);

        $this->getJson("/api/v1/teklif/{$konusma->id}", $this->istek($jeton))->assertForbidden();
    }
}
