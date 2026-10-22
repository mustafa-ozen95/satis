<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

/**
 * KURULUMU YAPAN ŞİRKET — ad, site, teklif istemi ve site sayfaları.
 *
 * Değerler config/company.php'den ve storage/app/company/ altındaki dosyalardan
 * gelir; depoda hiçbir şirkete özgü metin durmaz. Dosyalar her çağrıda
 * okunur: yerine konan bir dosya için yeniden başlatma gerekmez.
 */
final class Company
{
    /** Şirkete özgü istem dosyasının taşıması gereken yer tutucular. */
    public const PROMPT_PLACEHOLDERS = ['{price_context}', '{knowledge_context}', '{terms}', '{request}'];

    /** Site verilmemişse siteden gelen kayıtların kaynak etiketi. */
    private const VARSAYILAN_KAYNAK = 'kurumsal-site';

    public static function name(): string
    {
        return trim((string) config('company.name'));
    }

    /** Sitenin kök adresi, sondaki "/" olmadan; verilmemişse boş. */
    public static function website(): string
    {
        return rtrim(trim((string) config('company.website')), '/');
    }

    /**
     * Siteden gelen bilgi kayıtlarının kaynak etiketi: sitenin alan adı.
     *
     * Etiket veritabanına yazılır ve tur kendi kayıtlarını ondan tanır
     * (elle yazılmış kayıt ezilmez, sitede kalmayan bölüm silinir). Sütun
     * 60 karakter.
     */
    public static function siteSource(): string
    {
        $alan = (string) parse_url(self::website(), PHP_URL_HOST);
        $alan = (string) preg_replace('/^www\./i', '', mb_strtolower($alan));

        return $alan !== '' ? mb_substr($alan, 0, 60) : self::VARSAYILAN_KAYNAK;
    }

    /**
     * Şirkete özgü teklif istemi; dosya yoksa ya da kullanılamazsa null.
     *
     * Yer tutucusu eksik bir dosya sessizce kullanılsaydı model fiyat listesini
     * ya da talebi hiç görmeden teklif yazardı; o yüzden genel isteme düşülür
     * ve günlüğe uyarı yazılır.
     */
    public static function promptTemplate(): ?string
    {
        $metin = self::file((string) config('company.prompt_file'));

        if ($metin === null || trim($metin) === '') {
            return null;
        }

        $eksik = array_values(array_filter(
            self::PROMPT_PLACEHOLDERS,
            static fn (string $yer): bool => ! str_contains($metin, $yer),
        ));

        if ($eksik !== []) {
            Log::warning('Şirkete özgü teklif istemi kullanılmadı; eksik yer tutucu: '.implode(', ', $eksik));

            return null;
        }

        return rtrim($metin);
    }

    /**
     * knowledge:site turunun sayfaları: tam adres → başlık ve kategori.
     *
     * Dosya yoksa boş liste. Dosya VAR ama okunamıyorsa istisna: yanlış
     * yazılmış bir liste "sayfa yok" sayılsaydı tur sessizce atlanırdı.
     *
     * @return array<string, array{baslik: string, kategori: string}>
     */
    public static function sitePages(): array
    {
        $metin = self::file((string) config('company.site_pages_file'));

        if ($metin === null) {
            return [];
        }

        try {
            $liste = json_decode($metin, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Site sayfaları dosyası geçerli JSON değil: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($liste)) {
            throw new RuntimeException('Site sayfaları dosyası bir nesne olmalı: {"/yol": {"baslik": "...", "kategori": "..."}}.');
        }

        $sayfalar = [];

        foreach ($liste as $yol => $sayfa) {
            $baslik = is_array($sayfa) ? trim((string) ($sayfa['baslik'] ?? '')) : '';
            $kategori = is_array($sayfa) ? trim((string) ($sayfa['kategori'] ?? '')) : '';

            if ($baslik === '' || $kategori === '') {
                throw new RuntimeException("Site sayfaları dosyasında \"{$yol}\" için baslik ve kategori gerekli.");
            }

            $sayfalar[self::url((string) $yol)] = ['baslik' => $baslik, 'kategori' => $kategori];
        }

        return $sayfalar;
    }

    /** Göreli yolu sitenin köküne ekler; tam adresi olduğu gibi bırakır. */
    private static function url(string $yol): string
    {
        if (preg_match('#^https?://#i', $yol) === 1) {
            return $yol;
        }

        if (self::website() === '') {
            throw new RuntimeException("Site sayfaları dosyasında göreli yol var (\"{$yol}\") ama COMPANY_WEBSITE verilmemiş.");
        }

        return self::website().'/'.ltrim($yol, '/');
    }

    private static function file(string $yol): ?string
    {
        if ($yol === '' || ! is_file($yol)) {
            return null;
        }

        /* Var ama okunamıyorsa (sahibi/izni yanlış) sessiz kalınmaz. */
        if (! is_readable($yol)) {
            Log::warning("Şirket dosyası okunamıyor (sahibini ve iznini denetleyin): {$yol}");

            return null;
        }

        $metin = file_get_contents($yol);

        return $metin === false ? null : $metin;
    }
}
