<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Zamanlayıcıda duran işler.
 *
 * Bir turu eklemeyi unutmak sessizdir: her şey çalışıyor görünür, yalnızca
 * veri yavaşça eskir. Liste burada bağlanıyor.
 */
class SchedulerTest extends TestCase
{
    public function test_gerekli_turlar_zamanlayicida(): void
    {
        $komutlar = collect(app(Schedule::class)->events())
            ->map(fn ($olay): string => (string) $olay->command)
            ->implode(' ');

        foreach (['identity:sync', 'audit:verify', 'update:check', 'knowledge:index', 'knowledge:site'] as $komut) {
            $this->assertStringContainsString($komut, $komutlar, "{$komut} zamanlayıcıda yok.");
        }
    }

    /** Site turu HAFTALIK: günlük koşmak siteyi gereksiz yere indirmek olurdu. */
    public function test_site_turu_haftalik_kosar(): void
    {
        $olay = collect(app(Schedule::class)->events())
            ->first(fn ($olay): bool => str_contains((string) $olay->command, 'knowledge:site'));

        $this->assertNotNull($olay);
        $this->assertSame('30 5 * * 1', $olay->expression);
    }
}
