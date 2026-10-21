<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateTeklifJob;
use App\Models\Conversation;
use Tests\TestCase;

/**
 * Kuyruk zaman aşımı ile `retry_after` ARASINDAKİ İLİŞKİ.
 *
 * `retry_after` işin zaman aşımından kısaysa, iş hâlâ çalışırken kuyruk onu
 * terk edilmiş sayıp ikinci bir işçiye verir: teklif iki kez üretilir, model
 * iki kez ücretlendirilir, sohbete iki yanıt düşer. Sessiz ve pahalı bir
 * hata olduğu için sınamayla bağlanıyor.
 */
class KuyrukAyariTest extends TestCase
{
    public function test_retry_after_is_zaman_asimindan_uzun(): void
    {
        $is = new GenerateTeklifJob(new Conversation, 'talep');

        foreach (['database', 'redis', 'beanstalkd'] as $baglanti) {
            $retryAfter = (int) config("queue.connections.{$baglanti}.retry_after");

            $this->assertGreaterThan(
                $is->timeout,
                $retryAfter,
                "{$baglanti}: retry_after ({$retryAfter}) işin zaman aşımından ({$is->timeout}) uzun olmalı.",
            );
        }
    }

    /** İşçinin sınırı da retry_after'ı aşmamalı. */
    public function test_isci_zaman_asimi_retry_after_icinde_kalir(): void
    {
        $conf = file_get_contents(base_path('docker/rootfs/etc/supervisor/supervisord.conf'));

        $this->assertIsString($conf);
        $this->assertSame(1, preg_match('/queue:work.*--timeout=(\d+)/', $conf, $m), 'İşçi komutu bulunamadı.');

        $this->assertLessThanOrEqual(
            (int) config('queue.connections.redis.retry_after'),
            (int) $m[1],
            'İşçi zaman aşımı retry_after içinde kalmalı.',
        );
    }
}
