<?php

namespace App\Http\Controllers;

use App\Http\Requests\BilgiRequest;
use App\Models\BilgiKaydi;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\KnowledgeBaseService;
use App\Support\FormDurumu;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Bilgi bankası: liste, ekleme, düzenleme, silme. Veri Chroma'da tutulduğu için
 * (Eloquent modeli yok) yetki kontrolü doğrudan Gate ile yapılır.
 */
class BilgiBankasiController extends Controller
{
    public function __construct(protected KnowledgeBaseService $knowledgeBase) {}

    public function index(): View
    {
        $this->authorize('perm_bilgi', User::class);

        $kayitlar = $this->knowledgeBase->listEntries();

        return view('bilgi-bankasi.index', [
            'kayitlar' => $kayitlar,
            'duzenleAcik' => FormDurumu::duzenleHaritasi(collect($kayitlar)->pluck('id')),
            'buFormEkle' => FormDurumu::ekle(),

            /*
             * Kategori SEÇİLİR. Liste var olan kayıtlardan geliyor; ayrı bir
             * kategori tablosu yok ve olmamalı — kategori bir etiket, kendi
             * başına yönetilecek bir varlık değil.
             */
            'kategoriler' => BilgiKaydi::query()
                ->select('kategori')
                ->distinct()
                ->orderBy('kategori')
                ->pluck('kategori')
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    public function store(BilgiRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->knowledgeBase->addEntry(
            text: $validated['bilgi'],
            title: $validated['baslik'],
            category: $validated['kategori'],
            source: 'panel-manuel',
        );

        app(ActivityRecorder::class)->kaydet('bilgi_ekle', 'Bilgi bankasına kayıt eklendi');

        return redirect()->route('bilgi-bankasi.index')->with('status', 'Bilgi eklendi.');
    }

    public function update(BilgiRequest $request, int $entry): RedirectResponse
    {
        $validated = $request->validated();

        $this->knowledgeBase->updateEntry(
            entryId: $entry,
            text: $validated['bilgi'],
            title: $validated['baslik'],
            category: $validated['kategori'],
        );

        app(ActivityRecorder::class)->kaydet(
            'bilgi_guncelle',
            "Bilgi bankası kaydı güncellendi: {$validated['kategori']} · {$validated['baslik']}",
        );

        return redirect()->route('bilgi-bankasi.index')->with('status', 'Bilgi güncellendi.');
    }

    public function destroy(int $entry): RedirectResponse
    {
        $this->authorize('perm_bilgi', User::class);

        $this->knowledgeBase->deleteEntry($entry);

        /*
         * Kaydın kendisi Chroma'da ve silindi; burada kalan tek iz bu satır.
         * Kimliği yazılır ki hangi kaydın gittiği belli olsun.
         */
        app(ActivityRecorder::class)->kaydet('bilgi_sil', "Bilgi bankası kaydı silindi: {$entry}");

        return redirect()->route('bilgi-bankasi.index')->with('status', 'Bilgi silindi.');
    }
}
