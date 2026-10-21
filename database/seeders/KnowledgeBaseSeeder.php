<?php

namespace Database\Seeders;

use App\Services\KnowledgeBaseService;
use Illuminate\Database\Seeder;

/**
 * Fiyat kalemi olmayan, kural/istisna/açıklama niteliğindeki bilgiler.
 * Bunlar Fiyat Motoru'na değil Bilgi Bankası'na gider; Teklif AI onları
 * bağlam olarak arar, fiyat üretmek için değil.
 *
 * Kuruma özgü kurallar depoda DURMAZ: veritabanındadır ve panelden (Bilgi
 * Bankası ekranı) yönetilir. Buradaki kayıtlar yalnız ÖRNEK.
 *
 * KAYIT VERİTABANINA YAZILIR, indeks ondan türetilir: Chroma ya da Gemini o
 * an erişilemezse kayıtlar yine durur ve `bilgi:indeksle` indeksi sonradan
 * kurar.
 */
class KnowledgeBaseSeeder extends Seeder
{
    public function run(KnowledgeBaseService $knowledgeBase): void
    {
        foreach ($this->notes() as $note) {
            $knowledgeBase->addEntry(
                text: $note['text'],
                title: $note['title'],
                category: $note['category'],
                source: 'ilk-kurulum',
            );
        }
    }

    protected function notes(): array
    {
        return [
            [
                'title' => 'Örnek kural: teklif geçerlilik süresi',
                'category' => 'Genel',
                'text' => 'Örnek kayıt: teklifler hazırlandığı tarihten itibaren 15 gün geçerlidir. '
                    .'Kurumunuzun kurallarıyla değiştirin.',
            ],
            [
                'title' => 'Örnek kural: kurulum ücreti',
                'category' => 'Genel',
                'text' => 'Örnek kayıt: yıllık ödemede kurulum ücreti alınmaz. Kurumunuzun kurallarıyla değiştirin.',
            ],
        ];
    }
}
