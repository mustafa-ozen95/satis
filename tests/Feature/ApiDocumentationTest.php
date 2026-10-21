<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Api\ApiDocumentation;
use App\Services\Authorization\PermissionRegistry;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Belge ile gerçek ARASINDA fark kalmasın.
 *
 * Belge rotalardan üretiliyor; buradaki sınamalar üç şeyi bağlıyor:
 * uç → belge, belge → yetki sözlüğü, yetki sözlüğü → Gate. Biri eklenip
 * öbürü unutulduğunda kimse fark etmez; test eder.
 */
class ApiDocumentationTest extends TestCase
{
    public function test_her_ucun_belgesi_var(): void
    {
        $eksik = collect(app(ApiDocumentation::class)->endpoints())
            ->reject(fn (array $uc): bool => $uc['documented'])
            ->pluck('name')
            ->all();

        $this->assertSame([], $eksik, 'config/api.php içinde belgesi olmayan uç: '.implode(', ', $eksik));
    }

    public function test_belgede_kalmis_olu_girdi_yok(): void
    {
        $oksuz = app(ApiDocumentation::class)->orphans();

        $this->assertSame([], $oksuz, 'Rotası olmayan belge girdisi: '.implode(', ', $oksuz));
    }

    public function test_her_ucun_yetkisi_sozlukte_ve_gate_olarak_tanimli(): void
    {
        $sozluk = app(PermissionRegistry::class);

        foreach (app(ApiDocumentation::class)->endpoints() as $uc) {
            $yetki = $uc['ability'];

            $this->assertNotSame('', $yetki, "Yetkisiz uç: {$uc['name']}");
            $this->assertStringContainsString(':', $yetki, "Biçimsiz yetki: {$yetki}");

            [$kaynak, $aksiyon] = explode(':', $yetki, 2);

            $this->assertTrue(
                $sozluk->supports($kaynak, $aksiyon),
                "config/permissions.php içinde tanımsız yetki: {$yetki}",
            );

            $this->assertTrue(
                Gate::has($yetki),
                "Gate::define edilmemiş yetki: {$yetki}",
            );
        }
    }

    public function test_sozlukteki_her_yetkinin_bir_gate_karsiligi_var(): void
    {
        $sozluk = app(PermissionRegistry::class);

        foreach ($sozluk->resourceKeys() as $kaynak) {
            foreach ($sozluk->actionsFor($kaynak) as $aksiyon) {
                $this->assertTrue(
                    Gate::has("{$kaynak}:{$aksiyon}"),
                    "Sözlükte duran ama sorulmayan yetki: {$kaynak}:{$aksiyon}",
                );
            }
        }
    }
}
