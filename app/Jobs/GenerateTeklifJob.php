<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\TeklifService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * Teklif metnini kuyrukta üreten iş. Laravel'in yerleşik, kalıcı, yeniden
 * denenebilir Queue sistemini kullanır (php artisan queue:work).
 */
class GenerateTeklifJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * DENEME SAYISI VE ARALARI.
     *
     * Sağlayıcı kotası (429) ve geçici arıza (5xx) sahada sıradan. İki
     * denemeyi arka arkaya yapmak aynı duvara iki kez çarpmaktı; arada
     * beklemek çoğu zaman yetiyor. LlmService kendi içinde de deniyor
     * (saniyeler), buradaki bekleme daha uzun bir dalgayı aşmak için.
     */
    public int $tries = 3;

    /**
     * İŞ ZAMAN AŞIMI.
     *
     * Varsayılan işçi sınırı (60 sn) uzun bir teklif metnini kesiyordu: model
     * hâlâ yazarken iş "başarısız" sayılıyor, kullanıcı hata görüyordu.
     * LlmService'in kendi sınırı 120 sn; iş onun iki katını tanır ki
     * yeniden denemeler de sığsın.
     */
    public int $timeout = 300;

    /** Konuşma geçmişinden isteme girecek en fazla mesaj. */
    private const GECMIS_SINIRI = 12;

    public function __construct(public Conversation $conversation, public string $talep) {}

    /** @return list<int> saniye */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(TeklifService $teklifService): void
    {
        $sonuc = $teklifService->generateTeklif($this->buildTalep());

        $this->conversation->messages()->create([
            'role' => 'assistant',
            'content' => "MÜŞTERİ MAİLİ\n\n{$sonuc['musteri_maili']}\n\n---\n\nİÇ AÇIKLAMA (satış ekibi)\n\n{$sonuc['ic_aciklama']}",
        ]);

        $this->conversation->update(['durum' => 'hazir', 'hata_mesaji' => null]);
    }

    /**
     * Sohbet ekranında bir konuşmaya devam mesajı gönderildiğinde LLM'in
     * önceki mesajlardan habersiz kalmaması için tüm geçmiş tek bir
     * "MÜŞTERİ TALEBİ" metnine dönüştürülür. İlk mesajda (tek mesaj varken)
     * davranış eskisiyle birebir aynıdır: yalnızca ham talep metni.
     */
    protected function buildTalep(): string
    {
        $mesajlar = $this->conversation->messages()->orderBy('created_at')->get();

        if ($mesajlar->count() <= 1) {
            return $this->talep;
        }

        /*
         * GEÇMİŞ SINIRLI.
         *
         * Uzayan bir konuşmanın tamamı her seferinde isteme giriyordu: istem
         * büyüdükçe fiyat listesi ve bilgi bankası kuralları dikkatte
         * geriye düşüyor, maliyet ve gecikme de artıyordu. Son mesajlar
         * konuşmanın bağlamını zaten taşıyor; ilk talep ayrıca başa yazılır
         * çünkü asıl soru odur.
         */
        $son = $mesajlar->take(-self::GECMIS_SINIRI);

        $metin = $son
            ->map(fn ($mesaj) => ($mesaj->role === 'user' ? 'MÜŞTERİ' : 'TEKLİF AI')." : {$mesaj->content}")
            ->implode("\n\n");

        if ($mesajlar->count() > $son->count()) {
            $ilk = (string) $mesajlar->first()->content;

            $metin = "İLK TALEP: {$ilk}\n\n(…önceki mesajların bir kısmı atlandı…)\n\n{$metin}";
        }

        return $metin;
    }

    /**
     * Tum denemeler (tries) tukendikten sonra Laravel bunu cagirir. Bekleme
     * sayfasındaki polling bu durumu "hata" olarak gorup kullaniciya gosterir.
     */
    public function failed(Throwable $exception): void
    {
        /*
         * KULLANICIYA KISA, KAYDA TAM.
         *
         * Sağlayıcının hata gövdesi olduğu gibi buraya yazılıyor ve ekranda
         * kilobaytlarca JSON görünüyordu. Ayrıntı hata kaydına düşer
         * (yönetici oradan okur), ekranda ne olduğunu söyleyen tek satır
         * kalır.
         */
        report($exception);

        $this->conversation->update([
            'durum' => 'hata',
            'hata_mesaji' => Str::limit(trim($exception->getMessage()), 200),
        ]);
    }
}
