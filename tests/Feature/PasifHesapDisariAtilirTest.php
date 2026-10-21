<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Erişimi kaldırılan hesabın AÇIK OTURUMU da biter.
 *
 * `aktif` yalnızca girişte sorulsaydı, o sırada oturumu açık olan biri
 * sekmesini kapatmadığı sürece panelde gezinmeye devam ederdi.
 */
class PasifHesapDisariAtilirTest extends TestCase
{
    use RefreshDatabase;

    public function test_pasife_alinan_kullanici_bir_sonraki_istekte_atilir(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->get(route('teklif.create'))->assertOk();

        $kullanici->forceFill(['aktif' => false])->save();

        $this->get(route('teklif.create'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_etkin_kullanici_etkilenmez(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->get(route('teklif.create'))->assertOk();
        $this->get(route('teklif.create'))->assertOk();

        $this->assertAuthenticatedAs($kullanici);
    }
}
