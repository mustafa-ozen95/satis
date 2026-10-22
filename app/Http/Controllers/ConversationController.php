<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quote\BulkDeleteRequest;
use App\Http\Requests\Quote\DemandRequest;
use App\Http\Requests\Quote\MessageRequest;
use App\Jobs\GenerateQuoteJob;
use App\Models\Conversation;
use App\Models\PriceItem;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\KnowledgeBaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

/**
 * Standartlar dokumanindaki kural: "Controller sadece request'i yonetmeli,
 * business logic Service icinde olmali." Bu yuzden burada sadece:
 * yetki kontrolu + validasyon (Form Request) + Job dispatch var.
 *
 * "Tekliflerim" listesi ile sohbet ekranı tek bir view'da (teklif.index)
 * birleşik: index() hiçbir konuşma seçilmemiş hâli, show() belirli bir
 * konuşma seçilmiş hâli basar - ikisi de aynı sütun düzenini kullanır.
 */
class ConversationController extends Controller
{
    public function index(Request $request, KnowledgeBaseService $knowledgeBase): View
    {
        $this->authorize('perm_teklif', User::class);

        $teklifler = $this->myQuotes($request);

        return view('quote.index', [
            'tekliflerim' => $teklifler,
            'devamiVar' => $teklifler->isNotEmpty()
                && $this->quoteQuery($request)->where('id', '<', $teklifler->last()->id)->exists(),
            'arama' => $request->string('q')->toString(),
            'secili' => null,
            'istatistik' => $this->statistics($knowledgeBase),
        ]);
    }

    public function store(DemandRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $conversation = $request->user()->conversations()->create([
            'ilk_talep' => $validated['talep'],
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['talep'],
        ]);

        app(ActivityRecorder::class)->log('teklif_olustur', 'Teklif üretimi başlatıldı', $conversation);

        GenerateQuoteJob::dispatch($conversation, $validated['talep']);

        return redirect()->route('quote.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation, KnowledgeBaseService $knowledgeBase): View
    {
        $this->authorize('view', $conversation); // ConversationPolicy@view - IDOR korumasi

        /*
         * AÇILDI = OKUNDU.
         *
         * İşaret listeden ÖNCE konur: aynı istekte çizilen liste, az önce
         * açılan konuşmayı okunmamış göstermesin.
         */
        $conversation->markRead();

        $teklifler = $this->myQuotes($request);

        return view('quote.index', [
            'tekliflerim' => $teklifler,
            'devamiVar' => $teklifler->isNotEmpty()
                && $this->quoteQuery($request)->where('id', '<', $teklifler->last()->id)->exists(),
            'arama' => $request->string('q')->toString(),
            'secili' => $conversation->load('messages'),
            'istatistik' => $this->statistics($knowledgeBase),
        ]);
    }

    /**
     * Sohbete devam mesajı: yeni mesaj kutusundan gönderilir, aynı job +
     * durum/polling mekanizmasını kullanır (bkz. GenerateQuoteJob).
     */
    public function reply(MessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('reply', $conversation); // ConversationPolicy@reply

        if ($conversation->durum === 'bekliyor') {
            return redirect()->route('quote.show', $conversation)
                ->with('error', 'Önceki mesaj hâlâ hazırlanıyor, lütfen bekleyin.');
        }

        $validated = $request->validated();

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['mesaj'],
        ]);

        $conversation->update(['durum' => 'bekliyor', 'hata_mesaji' => null]);

        GenerateQuoteJob::dispatch($conversation, $validated['mesaj']);

        return redirect()->route('quote.show', $conversation);
    }

    /**
     * Teklif işinin durumu. Bekleme sayfasındaki JS bunu 1.5 saniyede bir polling ile sorgular.
     */
    public function status(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return response()->json([
            'status' => $conversation->durum,
            'error_message' => $conversation->hata_mesaji,
        ]);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->messages()->delete();
        $conversation->delete();

        return redirect()->route('quote.create')->with('status', 'Teklif silindi.');
    }

    /**
     * Sol sütundaki toplu seçim şeridi (data-bulk-* sözleşmesi, tasarim.js).
     */
    public function bulkDestroy(BulkDeleteRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $konusmalar = auth()->user()
            ->conversations()
            ->whereIn('id', $validated['secili_id'])
            ->get();

        foreach ($konusmalar as $konusma) {
            $this->authorize('delete', $konusma);
            $konusma->messages()->delete();
            $konusma->delete();
        }

        return redirect()->route('quote.create')
            ->with('status', $konusmalar->count().' teklif silindi.');
    }

    /**
     * Sol sütundaki liste: her zaman OTURUM AÇAN kullanıcının kendi
     * konuşmaları - admin başka birinin konuşmasını doğrudan bağlantıyla
     * görebilir (ConversationPolicy@view) ama "Tekliflerim" listesi
     * kişiseldir.
     */
    /** Listede bir seferde kaç teklif gösterilir (bkz. daha()). */
    public const SAYFA_BOYU = 15;

    /**
     * Listedeki teklifler.
     *
     * KİŞİYE ÖZELDİR: herkes kendi tekliflerini görür. Teklif metni müşteri
     * yazışmasıdır ve iç açıklama satış notu taşır; ortak bir liste, onu
     * yazmayan herkese açmak olurdu.
     *
     * YÖNETİCİ HEPSİNİ GÖRÜR: panelin sorumlusu odur ve "kim ne istemiş"
     * sorusunu ancak tam listeden yanıtlayabilir.
     *
     * SAYFA BOYU 15: liste zamanla binlerce satıra çıkabilir ve hepsini
     * tek seferde çizmek telefonda sayfayı kilitler. Gerisi "Devamını
     * gör" ile, on beşer on beşer gelir.
     */
    protected function myQuotes(Request $request, ?int $oncesi = null): Collection
    {
        return $this->quoteQuery($request)
            ->when($oncesi !== null, fn ($q) => $q->where('id', '<', $oncesi))
            ->orderByDesc('id')
            ->limit(self::SAYFA_BOYU)
            ->get();
    }

    /** @return Builder<Conversation> */
    protected function quoteQuery(Request $request)
    {
        $kullanici = auth()->user();

        $sorgu = $kullanici->isAdmin()
            ? Conversation::query()->with('user')
            : $kullanici->conversations()->getQuery();

        return $sorgu->when(
            $request->filled('q'),
            fn ($q) => $q->where('ilk_talep', 'like', '%'.$request->string('q').'%'),
        );
    }

    /**
     * "Devamını gör" — sonraki on beş teklif.
     *
     * Kimlik sınırı BURADA DA GEÇERLİ: sorgu aynı yerden kuruluyor, yönetici
     * olmayan yalnızca kendi tekliflerini alır. Ayrı bir sorgu yazılsaydı
     * bir gün biri unutulur ve uç, listenin arkasından herkesin teklifini
     * verirdi.
     */
    public function more(Request $request): View
    {
        $this->authorize('perm_teklif', User::class);

        $oncesi = $request->integer('oncesi') ?: null;
        $teklifler = $this->myQuotes($request, $oncesi);

        return view('quote.partials.rows', [
            'tekliflerim' => $teklifler,
            'secili' => null,
            'devamiVar' => $teklifler->isNotEmpty()
                && $this->quoteQuery($request)->where('id', '<', $teklifler->last()->id)->exists(),
        ]);
    }

    protected function statistics(KnowledgeBaseService $knowledgeBase): array
    {
        try {
            $bilgiSayisi = count($knowledgeBase->listEntries());
        } catch (Throwable) {
            $bilgiSayisi = 0;
        }

        return [
            'fiyat_sayisi' => PriceItem::active()->count(),
            'bilgi_sayisi' => $bilgiSayisi,
            'kullanici_sayisi' => User::count(),
        ];
    }
}
