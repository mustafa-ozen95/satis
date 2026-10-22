<?php

namespace App\Http\Controllers;

use App\Http\Requests\KnowledgeRequest;
use App\Models\KnowledgeRecord;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\KnowledgeBaseService;
use App\Support\FormStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Bilgi bankası: liste, ekleme, düzenleme, silme. Veri Chroma'da tutulduğu için
 * (Eloquent modeli yok) yetki kontrolü doğrudan Gate ile yapılır.
 */
class KnowledgeBaseController extends Controller
{
    public function __construct(protected KnowledgeBaseService $knowledgeBase) {}

    public function index(): View
    {
        $this->authorize('perm_bilgi', User::class);

        $kayitlar = $this->knowledgeBase->listEntries();

        return view('knowledge-base.index', [
            'kayitlar' => $kayitlar,
            'duzenleAcik' => FormStatus::editMap(collect($kayitlar)->pluck('id')),
            'buFormEkle' => FormStatus::isAdd(),

            /*
             * Kategori SEÇİLİR. Liste var olan kayıtlardan geliyor; ayrı bir
             * kategori tablosu yok ve olmamalı — kategori bir etiket, kendi
             * başına yönetilecek bir varlık değil.
             */
            'kategoriler' => KnowledgeRecord::query()
                ->select('kategori')
                ->distinct()
                ->orderBy('kategori')
                ->pluck('kategori')
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    public function store(KnowledgeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->knowledgeBase->addEntry(
            text: $validated['bilgi'],
            title: $validated['baslik'],
            category: $validated['kategori'],
            source: 'panel-manuel',
        );

        app(ActivityRecorder::class)->log('bilgi_ekle', 'Bilgi bankasına kayıt eklendi');

        return redirect()->route('knowledge-base.index')->with('status', 'Bilgi eklendi.');
    }

    public function update(KnowledgeRequest $request, int $entry): RedirectResponse
    {
        $validated = $request->validated();

        $this->knowledgeBase->updateEntry(
            entryId: $entry,
            text: $validated['bilgi'],
            title: $validated['baslik'],
            category: $validated['kategori'],
        );

        app(ActivityRecorder::class)->log(
            'bilgi_guncelle',
            "Bilgi bankası kaydı güncellendi: {$validated['kategori']} · {$validated['baslik']}",
        );

        return redirect()->route('knowledge-base.index')->with('status', 'Bilgi güncellendi.');
    }

    public function destroy(int $entry): RedirectResponse
    {
        $this->authorize('perm_bilgi', User::class);

        $this->knowledgeBase->deleteEntry($entry);

        /*
         * Kaydın kendisi Chroma'da ve silindi; burada kalan tek iz bu satır.
         * Kimliği yazılır ki hangi kaydın gittiği belli olsun.
         */
        app(ActivityRecorder::class)->log('bilgi_sil', "Bilgi bankası kaydı silindi: {$entry}");

        return redirect()->route('knowledge-base.index')->with('status', 'Bilgi silindi.');
    }
}
