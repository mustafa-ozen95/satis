<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * GİZLİ DOSYALAR İMAJA DA DEPOYA DA GİRMEZ.
 *
 * Dockerfile "COPY . ." yapıyor: derleme bağlamında duran her dosya imaja
 * girer. Korunan üç şey: satis.conf'taki API anahtarları, sertifika
 * dizinindeki TLS özel anahtarı ve storage/app altındaki çalışma verisi
 * (şirkete özgü teklif istemi ve site sayfaları dahil).
 */
class GizliDosyalarTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function kurallar(): array
    {
        return [
            'imaj · kurulum yapılandırması' => ['.dockerignore', '/satis.conf'],
            'imaj · ortam dosyası' => ['.dockerignore', '.env'],
            'imaj · sertifika dizini' => ['.dockerignore', '/sertifika'],
            'imaj · çalışma verisi' => ['.dockerignore', 'storage/app/*'],
            'depo · kurulum yapılandırması' => ['.gitignore', '/satis.conf'],
            'depo · sertifika dizini' => ['.gitignore', '/sertifika/'],
        ];
    }

    #[DataProvider('kurallar')]
    public function test_gizli_dosya_disarida_kalir(string $dosya, string $kural): void
    {
        $satirlar = array_map('trim', file(base_path($dosya), FILE_IGNORE_NEW_LINES) ?: []);

        $this->assertContains($kural, $satirlar, "{$dosya} içinde '{$kural}' satırı olmalı.");
    }
}
