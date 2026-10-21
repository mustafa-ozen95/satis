<?php

namespace App\Http\Controllers;

use App\Http\Requests\Teklif\MesajRequest;
use App\Http\Requests\Teklif\TalepRequest;
use App\Http\Requests\Teklif\TopluSilRequest;
use App\Jobs\GenerateTeklifJob;
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

        $teklifler = $this->tekliflerim($request);

        return view('teklif.index', [
            'tekliflerim' => $teklifler,
            'devamiVar' => $teklifler->isNotEmpty()
                && $this->teklifSorgusu($request)->where('id', '<', $teklifler->last()->id)->exists(),
            'arama' => $request->string('q')->toString(),
            'secili' => null,
            'istatistik' => $this->istatistik($knowledgeBase),
        ]);
    }

    public function store(TalepRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $conversation = $request->user()->conversations()->create([
            'ilk_talep' => $validated['talep'],
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['talep'],
        ]);

        app(ActivityRecorder::class)->kaydet('teklif_olustur', 'Teklif üretimi başlatıldı', $conversation);

        GenerateTeklifJob::dispatch($conversation, $validated['talep']);

        return redirect()->route('teklif.show', $conversation);
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
        $conversation->okunduIsaretle();

        $teklifler = $this->tekliflerim($request);

        return view('teklif.index', [
            'tekliflerim' => $teklifler,
            'devamiVar' => $teklifler->isNotEmpty()
                && $this->teklifSorgusu($request)->where('id', '<', $teklifler->last()->id)->exists(),
            'arama' => $request->string('q')->toString(),
            'secili' => $conversation->load('messages'),
            'istatistik' => $this->istatistik($knowledgeBase),
        ]);
    }

    /**
     * Sohbete devam mesajı: yeni mesaj kutusundan gönderilir, aynı job +
     * durum/polling mekanizmasını kullanır (bkz. GenerateTeklifJob).
     */
    public function reply(MesajRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('reply', $conversation); // ConversationPolicy@reply

        if ($conversation->durum === 'bekliyor') {
            return redirect()->route('teklif.show', $conversation)
                ->with('error', 'Önceki mesaj hâlâ hazırlanıyor, lütfen bekleyin.');
        }

        $validated = $request->validated();

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['mesaj'],
        ]);

        $conversation->update(['durum' => 'bekliyor', 'hata_mesaji' => null]);

        GenerateTeklifJob::dispatch($conversation, $validated['mesaj']);

        return redirect()->route('teklif.show', $conversation);
    }

    /**
     * Teklif işinin durumu. Bekleme sayfasındaki JS bunu 1.5 saniyede bir polling ile sorgular.
     */
    public function durum(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return response()->json([
            'durum' => $conversation->durum,
            'hata_mesaji' => $conversation->hata_mesaji,
        ]);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->messages()->delete();
        $conversation->delete();

        return redirect()->route('teklif.create')->with('status', 'Teklif silindi.');
    }

    /**
     * Sol sütundaki toplu seçim şeridi (data-bulk-* sözleşmesi, tasarim.js).
     */
    public function bulkDestroy(TopluSilRequest $request): RedirectResponse
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

        return redirect()->route('teklif.create')
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
    protected function tekliflerim(Request $request, ?int $oncesi = null): Collection
    {
        return $this->teklifSorgusu($request)
            ->when($oncesi !== null, fn ($q) => $q->where('id', '<', $oncesi))
            ->orderByDesc('id')
            ->limit(self::SAYFA_BOYU)
            ->get();
    }

    /** @return Builder<Conversation> */
    protected function teklifSorgusu(Request $request)
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
    public function daha(Request $request): View
    {
        $this->authorize('perm_teklif', User::class);

        $oncesi = $request->integer('oncesi') ?: null;
        $teklifler = $this->tekliflerim($request, $oncesi);

        return view('teklif.partials.satirlar', [
            'tekliflerim' => $teklifler,
            'secili' => null,
            'devamiVar' => $teklifler->isNotEmpty()
                && $this->teklifSorgusu($request)->where('id', '<', $teklifler->last()->id)->exists(),
        ]);
    }

    protected function istatistik(KnowledgeBaseService $knowledgeBase): array
    {
        try {
            $bilgiSayisi = count($knowledgeBase->listEntries());
        } catch (Throwable) {
            $bilgiSayisi = 0;
        }

        return [
            'fiyat_sayisi' => PriceItem::aktif()->count(),
            'bilgi_sayisi' => $bilgiSayisi,
            'kullanici_sayisi' => User::count(),
        ];
    }
}
