<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TEKLİF BAŞLIĞI SUNUCUDA KIRPILMAZ.
 *
 * Başlığı hem PHP hem CSS kırpıyordu: `Str::limit(…, 60)` sütun ne kadar
 * geniş olursa olsun metni 60 karakterde bitiriyor, satırın sağında boşluk
 * bırakıyordu.
 *
 * Kırpma tek yerde kalmalı: çizim sırasında, satıra ne sığıyorsa. Sunucu
 * metnin tamamını yollar; sığmayanı tarayıcı üç noktaya indirir.
 */
class QuoteHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_uzun_baslik_listeye_tam_gider(): void
    {
        $kullanici = User::factory()->admin()->create();

        $talep = 'Tema: Fiyat Teklifi Talebi: Veri Merkezi Kolokasyon, Bant Genişliği ve Yönetilen Hizmetler';

        $this->assertGreaterThan(60, mb_strlen($talep), 'Sınama metni eski sınırdan uzun olmalı.');

        $kullanici->conversations()->create(['ilk_talep' => $talep, 'durum' => 'hazir']);

        $this->actingAs($kullanici)
            ->get(route('quote.create'))
            ->assertOk()
            ->assertSee($talep, false);
    }
}
