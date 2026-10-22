<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateQuoteJob;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * SOHBETE MESAJ GÖNDERME.
 *
 * `reply()` da `store()` gibi Form Request alır: gövde `$request->validated()`
 * çağırır ve imza düz `Request` olursa her deneme 500 verir ("Method
 * Illuminate\Http\Request::validated does not exist"). Bu sınama o ucu
 * korur.
 */
class QuoteMessageTest extends TestCase
{
    use RefreshDatabase;

    private function konusma(User $sahip, string $durum = 'hazir'): Conversation
    {
        $konusma = $sahip->conversations()->create(['ilk_talep' => 'Kabin fiyatı', 'durum' => $durum]);
        $konusma->messages()->create(['role' => 'user', 'content' => 'Kabin fiyatı']);

        return $konusma;
    }

    public function test_mesaj_gonderilebilir_ve_is_kuyruga_girer(): void
    {
        Queue::fake();

        $kullanici = User::factory()->admin()->create();
        $konusma = $this->konusma($kullanici);

        $this->actingAs($kullanici)
            ->post(route('quote.reply', $konusma), ['mesaj' => 'Peki 2 kabin olursa?'])
            ->assertRedirect(route('quote.show', $konusma));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $konusma->id,
            'role' => 'user',
            'content' => 'Peki 2 kabin olursa?',
        ]);

        $this->assertSame('bekliyor', $konusma->fresh()->durum);

        Queue::assertPushed(GenerateQuoteJob::class);
    }

    /** Boş mesaj kabul edilmez; kuyruğa da bir şey girmez. */
    public function test_bos_mesaj_reddedilir(): void
    {
        Queue::fake();

        $kullanici = User::factory()->admin()->create();
        $konusma = $this->konusma($kullanici);

        $this->actingAs($kullanici)
            ->post(route('quote.reply', $konusma), ['mesaj' => ''])
            ->assertSessionHasErrors('mesaj');

        Queue::assertNothingPushed();
    }

    /**
     * ÜRETİM SÜRERKEN İKİNCİ MESAJ ALINMAZ.
     *
     * Aksi hâlde aynı konuşma için iki iş koşar ve yanıtlar birbirine
     * karışır.
     */
    public function test_uretim_surerken_mesaj_alinmaz(): void
    {
        Queue::fake();

        $kullanici = User::factory()->admin()->create();
        $konusma = $this->konusma($kullanici, 'bekliyor');

        $this->actingAs($kullanici)
            ->post(route('quote.reply', $konusma), ['mesaj' => 'acele et'])
            ->assertRedirect(route('quote.show', $konusma));

        Queue::assertNothingPushed();
    }

    /**
     * BAŞKASININ KONUŞMASINA YAZILAMAZ.
     *
     * Panel 403 çizmek yerine "yetkiniz yok" ekranına yönlendiriyor; kapı
     * yine kapalı, yalnızca kullanıcıya boş bir hata sayfası göstermiyor.
     */
    public function test_baskasinin_konusmasina_yazilamaz(): void
    {
        Queue::fake();

        $sahip = User::factory()->create();
        $yabanci = User::factory()->create();

        $konusma = $this->konusma($sahip);

        $this->actingAs($yabanci)
            ->post(route('quote.reply', $konusma), ['mesaj' => 'merhaba'])
            ->assertRedirect();

        $this->assertDatabaseMissing('messages', ['content' => 'merhaba']);

        Queue::assertNothingPushed();
    }

    /**
     * TOPLU SİLME de Form Request alır: `$request->validated()` düz
     * `Request` üzerinde yoktur.
     */
    public function test_toplu_silme_calisir(): void
    {
        $kullanici = User::factory()->admin()->create();
        $a = $this->konusma($kullanici);
        $b = $this->konusma($kullanici);

        $this->actingAs($kullanici)
            ->post(route('quote.bulk-destroy'), ['secili_id' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('conversations', ['id' => $a->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $b->id]);
    }
}
