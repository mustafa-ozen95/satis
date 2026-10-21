<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\PriceItem;
use App\Models\User;
use App\Services\KnowledgeBaseService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

/**
 * Yönetici için özet sayfası: teklif, kullanıcı ve bilgi bankası sayıları.
 */
class IstatistikController extends Controller
{
    public function index(KnowledgeBaseService $knowledgeBase): View
    {
        $this->authorize('admin');

        try {
            $bilgiSayisi = count($knowledgeBase->listEntries());
        } catch (Throwable) {
            $bilgiSayisi = null;
        }

        return view('admin.istatistikler.index', [
            'istatistik' => [
                'kullanici_sayisi' => User::count(),
                'aktif_kullanici_sayisi' => User::where('aktif', true)->count(),
                'fiyat_sayisi' => PriceItem::aktif()->count(),
                'bilgi_sayisi' => $bilgiSayisi,
                'konusma_sayisi' => Conversation::count(),
            ],
            'kullaniciAktivite' => $this->kullaniciAktivite(),
        ]);
    }

    /**
     * "X kişisi 2 teklif yazdı, 3 fiyat kalemi ekledi" özeti - yalnızca en az
     * bir aktivitesi olan kullanıcılar, toplam aktiviteye göre azalan sırada.
     */
    protected function kullaniciAktivite(): Collection
    {
        return User::query()
            ->withCount([
                'activityLogs as teklif_sayisi' => fn ($q) => $q->where('tur', 'teklif_olustur'),
                'activityLogs as fiyat_sayisi' => fn ($q) => $q->where('tur', 'fiyat_ekle'),
                'activityLogs as bilgi_sayisi' => fn ($q) => $q->where('tur', 'bilgi_ekle'),
            ])
            ->get()
            ->filter(fn ($kullanici) => $kullanici->teklif_sayisi + $kullanici->fiyat_sayisi + $kullanici->bilgi_sayisi > 0)
            ->sortByDesc(fn ($kullanici) => $kullanici->teklif_sayisi + $kullanici->fiyat_sayisi + $kullanici->bilgi_sayisi)
            ->values();
    }
}
