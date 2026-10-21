<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

/**
 * KURULUMU YAPAN ŞİRKET — ad, site, teklif istemi ve site sayfaları.
 *
 * Değerler config/sirket.php'den ve storage/app/sirket/ altındaki dosyalardan
 * gelir; depoda hiçbir şirkete özgü metin durmaz. Dosyalar her çağrıda
 * okunur: yerine konan bir dosya için yeniden başlatma gerekmez.
 */
final class Sirket
{
    /** Şirkete özgü istem dosyasının taşıması gereken yer tutucular. */
    public const ISTEM_YER_TUTUCULARI = ['{fiyat_baglami}', '{bilgi_baglami}', '{kosullar}', '{talep}'];

    /** Site verilmemişse siteden gelen kayıtların kaynak etiketi. */
    private const VARSAYILAN_KAYNAK = 'kurumsal-site';

    public static function ad(): string
    {
        return trim((string) config('sirket.ad'));
    }

    /** Sitenin kök adresi, sondaki "/" olmadan; verilmemişse boş. */
    public static function site(): string
    {
        return rtrim(trim((string) config('sirket.site')), '/');
    }

    /**
     * Siteden gelen bilgi kayıtlarının kaynak etiketi: sitenin alan adı.
     *
     * Etiket veritabanına yazılır ve tur kendi kayıtlarını ondan tanır
     * (elle yazılmış kayıt ezilmez, sitede kalmayan bölüm silinir). Sütun
     * 60 karakter.
     */
    public static function siteKaynagi(): string
    {
        $alan = (string) parse_url(self::site(), PHP_URL_HOST);
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
    public static function istemSablonu(): ?string
    {
        $metin = self::dosya((string) config('sirket.istem_dosyasi'));

        if ($metin === null || trim($metin) === '') {
            return null;
        }

        $eksik = array_values(array_filter(
            self::ISTEM_YER_TUTUCULARI,
            static fn (string $yer): bool => ! str_contains($metin, $yer),
        ));

        if ($eksik !== []) {
            Log::warning('Şirkete özgü teklif istemi kullanılmadı; eksik yer tutucu: '.implode(', ', $eksik));

            return null;
        }

        return rtrim($metin);
    }

    /**
     * bilgi:siteden turunun sayfaları: tam adres → başlık ve kategori.
     *
     * Dosya yoksa boş liste. Dosya VAR ama okunamıyorsa istisna: yanlış
     * yazılmış bir liste "sayfa yok" sayılsaydı tur sessizce atlanırdı.
     *
     * @return array<string, array{baslik: string, kategori: string}>
     */
    public static function siteSayfalari(): array
    {
        $metin = self::dosya((string) config('sirket.site_sayfalari_dosyasi'));

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

            $sayfalar[self::adres((string) $yol)] = ['baslik' => $baslik, 'kategori' => $kategori];
        }

        return $sayfalar;
    }

    /** Göreli yolu sitenin köküne ekler; tam adresi olduğu gibi bırakır. */
    private static function adres(string $yol): string
    {
        if (preg_match('#^https?://#i', $yol) === 1) {
            return $yol;
        }

        if (self::site() === '') {
            throw new RuntimeException("Site sayfaları dosyasında göreli yol var (\"{$yol}\") ama SIRKET_SITESI verilmemiş.");
        }

        return self::site().'/'.ltrim($yol, '/');
    }

    private static function dosya(string $yol): ?string
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
