<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiyatAlanRequest;
use App\Http\Requests\StorePriceItemRequest;
use App\Models\PriceItem;
use App\Services\ActivityRecorder;
use App\Support\FormDurumu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Fiyat kalemleri: liste, ekleme, düzenleme, silme.
 */
class FiyatlarController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PriceItem::class);

        $fiyatlar = PriceItem::aktif()->orderBy('category')->orderBy('name')->get();

        return view('fiyatlar.index', [
            'fiyatlar' => $fiyatlar,
            'duzenleAcik' => FormDurumu::duzenleHaritasi($fiyatlar->pluck('id')),
            'buFormEkle' => FormDurumu::ekle(),

            /*
             * Kategori SEÇİLİR. Liste var olan kalemlerden geliyor; serbest
             * metin her yazımda yeni bir kategori doğuruyordu.
             */
            'kategoriler' => PriceItem::query()
                ->select('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    public function store(StorePriceItemRequest $request): RedirectResponse
    {
        $this->authorize('manage', PriceItem::class);

        $kalem = PriceItem::create([
            ...$request->validated(),
            'kdv_dahil' => $request->boolean('kdv_dahil'),
        ]);

        app(ActivityRecorder::class)->kaydet('fiyat_ekle', "Fiyat kalemi eklendi: {$kalem->category} · {$kalem->name}", $kalem);

        return redirect()->route('fiyatlar.index')->with('status', 'Fiyat kalemi eklendi.');
    }

    public function update(StorePriceItemRequest $request, PriceItem $fiyat): RedirectResponse
    {
        $this->authorize('manage', PriceItem::class);

        $eskiFiyat = $fiyat->price;

        $fiyat->update([
            ...$request->validated(),
            'kdv_dahil' => $request->boolean('kdv_dahil'),
        ]);

        /* Eski ve yeni değer birlikte yazılır: "ne değişti" sonradan sorulacak. */
        app(ActivityRecorder::class)->kaydet(
            'fiyat_guncelle',
            "Fiyat kalemi güncellendi: {$fiyat->category} · {$fiyat->name}"
                .($eskiFiyat !== $fiyat->price ? " · {$eskiFiyat} → {$fiyat->price} {$fiyat->currency}" : ''),
            $fiyat,
        );

        return redirect()->route('fiyatlar.index')->with('status', 'Fiyat kalemi güncellendi.');
    }

    /**
     * TEK ALANI YERİNDE GÜNCELLER (tabloda çift tıklama).
     *
     * Ayrı bir uç, çünkü tam form bütün alanları zorunlu kılıyor: yalnızca
     * fiyatı değiştirmek için kategoriyi, adı ve birimi de göndermek
     * gerekirdi ve o değerler ekrandan geldiği için eski hâllerini geri
     * yazma riski taşırdı.
     *
     * Yanıt hem HAM hem GÖSTERİM değerini döndürür: hücre kaydedildikten
     * sonra ne yazacağını sunucudan öğrenir, kendi biçimlendirmesini
     * uydurmaz (fiyat "1500" değil "1500,00 TL" görünür).
     */
    public function alanGuncelle(FiyatAlanRequest $request, PriceItem $fiyat): JsonResponse
    {
        $alan = (string) $request->validated('alan');
        $deger = $request->validated('deger');

        $onceki = $fiyat->{$alan};

        $fiyat->update([$alan => $deger]);

        app(ActivityRecorder::class)->kaydet(
            'fiyat_guncelle',
            "Fiyat kalemi yerinde düzenlendi: {$fiyat->category} · {$fiyat->name}"
                ." · {$alan}: {$onceki} → {$fiyat->{$alan}}",
            $fiyat,
        );

        return response()->json([
            'ham' => (string) $fiyat->{$alan},
            'gosterim' => $this->gosterim($fiyat, $alan),
            'mesaj' => 'Kaydedildi.',
        ]);
    }

    /** Hücrede görünecek metin; biçim tek yerden gelir. */
    private function gosterim(PriceItem $fiyat, string $alan): string
    {
        return $alan === 'price'
            ? rtrim(rtrim(number_format((float) $fiyat->price, 2, ',', '.'), '0'), ',').' '.$fiyat->currency
            : (string) $fiyat->{$alan};
    }

    public function destroy(PriceItem $fiyat): RedirectResponse
    {
        $this->authorize('manage', PriceItem::class);

        /*
          * Kayıt SİLMEDEN ÖNCE yazılır: silinen satırın adı ve fiyatı
          * sonradan okunamaz, denetim kaydında durmalı.
          */
        app(ActivityRecorder::class)->kaydet(
            'fiyat_sil',
            "Fiyat kalemi silindi: {$fiyat->category} · {$fiyat->name} · {$fiyat->price} {$fiyat->currency}",
        );

        $fiyat->delete();

        return redirect()->route('fiyatlar.index')->with('status', 'Fiyat kalemi silindi.');
    }
}
