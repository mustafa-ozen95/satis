<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriceFieldRequest;
use App\Http\Requests\StorePriceItemRequest;
use App\Models\PriceItem;
use App\Services\ActivityRecorder;
use App\Support\FormStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Fiyat kalemleri: liste, ekleme, düzenleme, silme.
 */
class PricesController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PriceItem::class);

        $fiyatlar = PriceItem::active()->orderBy('category')->orderBy('name')->get();

        return view('prices.index', [
            'fiyatlar' => $fiyatlar,
            'duzenleAcik' => FormStatus::editMap($fiyatlar->pluck('id')),
            'buFormEkle' => FormStatus::isAdd(),

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

        app(ActivityRecorder::class)->log('fiyat_ekle', "Fiyat kalemi eklendi: {$kalem->category} · {$kalem->name}", $kalem);

        return redirect()->route('prices.index')->with('status', 'Fiyat kalemi eklendi.');
    }

    public function update(StorePriceItemRequest $request, PriceItem $price): RedirectResponse
    {
        $this->authorize('manage', PriceItem::class);

        $eskiFiyat = $price->price;

        $price->update([
            ...$request->validated(),
            'kdv_dahil' => $request->boolean('kdv_dahil'),
        ]);

        /* Eski ve yeni değer birlikte yazılır: "ne değişti" sonradan sorulacak. */
        app(ActivityRecorder::class)->log(
            'fiyat_guncelle',
            "Fiyat kalemi güncellendi: {$price->category} · {$price->name}"
                .($eskiFiyat !== $price->price ? " · {$eskiFiyat} → {$price->price} {$price->currency}" : ''),
            $price,
        );

        return redirect()->route('prices.index')->with('status', 'Fiyat kalemi güncellendi.');
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
    public function updateField(PriceFieldRequest $request, PriceItem $price): JsonResponse
    {
        $alan = (string) $request->validated('field');
        $deger = $request->validated('value');

        $onceki = $price->{$alan};

        $price->update([$alan => $deger]);

        app(ActivityRecorder::class)->log(
            'fiyat_guncelle',
            "Fiyat kalemi yerinde düzenlendi: {$price->category} · {$price->name}"
                ." · {$alan}: {$onceki} → {$price->{$alan}}",
            $price,
        );

        return response()->json([
            'raw' => (string) $price->{$alan},
            'display' => $this->display($price, $alan),
            'message' => 'Kaydedildi.',
        ]);
    }

    /** Hücrede görünecek metin; biçim tek yerden gelir. */
    private function display(PriceItem $price, string $alan): string
    {
        return $alan === 'price'
            ? rtrim(rtrim(number_format((float) $price->price, 2, ',', '.'), '0'), ',').' '.$price->currency
            : (string) $price->{$alan};
    }

    public function destroy(PriceItem $price): RedirectResponse
    {
        $this->authorize('manage', PriceItem::class);

        /*
          * Kayıt SİLMEDEN ÖNCE yazılır: silinen satırın adı ve fiyatı
          * sonradan okunamaz, denetim kaydında durmalı.
          */
        app(ActivityRecorder::class)->log(
            'fiyat_sil',
            "Fiyat kalemi silindi: {$price->category} · {$price->name} · {$price->price} {$price->currency}",
        );

        $price->delete();

        return redirect()->route('prices.index')->with('status', 'Fiyat kalemi silindi.');
    }
}
