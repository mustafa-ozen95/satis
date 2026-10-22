<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Identity\AccountOpener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MERKEZDEN AÇILAN HESAP YALNIZCA TEKLİF İSTEYEBİLİR.
 *
 * Panelin işi teklif yazdırmaktır; bilgi bankası ve fiyat listesi onu
 * BESLEYEN kaynaklardır. Kaynağı görmek, tek tek maliyetleri ve iç notları
 * görmek demek — herkesin işi değil. Yönetici dışındakiler varsayılan
 * olarak yalnızca teklif ister.
 *
 * Yönetici gerekirse Kullanıcılar ekranından genişletir.
 */
class NewAccountPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_acilan_hesap_yalnizca_teklif_yetkisi_alir(): void
    {
        $kullanici = app(AccountOpener::class)->open([
            'sub' => 'merkez-01',
            'email' => 'yeni@ornek.test',
            'name' => 'Yeni Kişi',
            'rol' => 'teknik',
            'email_verified' => true,
        ]);

        $this->assertTrue((bool) $kullanici->perm_teklif, 'Teklif isteyebilmeli.');
        $this->assertFalse((bool) $kullanici->perm_bilgi, 'Bilgi bankası kapalı doğmalı.');
        $this->assertFalse((bool) $kullanici->perm_fiyat, 'Fiyat listesi kapalı doğmalı.');
    }

    /** Yönetici olmayan kullanıcı kaynak ekranlarına giremez. */
    public function test_yetkisiz_kullanici_kaynak_ekranlarini_acamaz(): void
    {
        $kullanici = User::factory()->create([
            'perm_teklif' => true,
            'perm_bilgi' => false,
            'perm_fiyat' => false,
        ]);

        $this->actingAs($kullanici)->get(route('knowledge-base.index'))->assertRedirect();
        $this->actingAs($kullanici)->get(route('prices.index'))->assertRedirect();

        /* Teklif ekranı açık kalır: panelin asıl işi bu. */
        $this->actingAs($kullanici)->get(route('quote.create'))->assertOk();
    }

    /** Yönetici her şeyi görür; yetki bayraklarına bakılmaz. */
    public function test_yonetici_kaynaklari_gorur(): void
    {
        $yonetici = User::factory()->admin()->create([
            'perm_bilgi' => false,
            'perm_fiyat' => false,
        ]);

        $this->actingAs($yonetici)->get(route('prices.index'))->assertOk();
    }
}
