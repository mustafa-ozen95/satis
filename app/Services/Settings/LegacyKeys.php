<?php

declare(strict_types=1);

namespace App\Services\Settings;

/**
 * Veritabanında kayıtlı eski ayar anahtarlarının ÇEVİRİSİ.
 *
 * Ayarın anahtarı config yolunun kendisidir; config dosyaları İngilizce
 * adlandırılınca anahtarların ilk parçası da değişti. `settings` tablosundaki
 * satırlar ise bu adlarla yazılmıştı ve yeniden adlandırılmaz (canlı veri):
 * çeviri YALNIZCA veritabanı sınırında yapılır. Okurken eski ad yeniye,
 * yazarken yeni ad eskiye döner; uygulamanın geri kalanı yalnızca yeni adı bilir.
 * Önbellekte kalmış eski adlı bir dizi de aynı çeviriden geçer.
 */
final class LegacyKeys
{
    /** Eski ilk parça → yeni ilk parça. */
    private const PREFIXES = [
        'kimlik' => 'identity',
        'teklif' => 'quote',
        'basit_mod' => 'simple_mode',
    ];

    /** Veritabanındaki tam ad → uygulamadaki tam ad (alt anahtarı da çevrilenler). */
    private const KEYS = [
        'kimlik.acik' => 'identity.enabled',
        'kimlik.yonetici_devri' => 'identity.admin_takeover',
        'kimlik.dogrulama_dakika' => 'identity.verify_minutes',
        'teklif.kdv_orani' => 'quote.vat_rate',
        'teklif.hizmet_suresi' => 'quote.service_time',
        'teklif.kurulum_suresi' => 'quote.setup_time',
        'teklif.gecerlilik_gun' => 'quote.validity_days',
        'panel.parola_uzunluk' => 'panel.password_length',
        'services.gemini.yedek_model' => 'services.gemini.fallback_model',
    ];

    /** Veritabanındaki ad → uygulamadaki ad. */
    public static function toCurrent(string $key): string
    {
        return self::KEYS[$key] ?? self::swap($key, self::PREFIXES);
    }

    /** Uygulamadaki ad → veritabanındaki ad. */
    public static function toStored(string $key): string
    {
        return array_flip(self::KEYS)[$key] ?? self::swap($key, array_flip(self::PREFIXES));
    }

    /**
     * Anahtarları çevrilmiş yeni bir dizi döner (değerlere dokunmaz).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function currentKeys(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $out[self::toCurrent((string) $key)] = $value;
        }

        return $out;
    }

    /** @param  array<string, string>  $map */
    private static function swap(string $key, array $map): string
    {
        $dot = strpos($key, '.');

        if ($dot === false) {
            return $key;
        }

        $head = substr($key, 0, $dot);

        return isset($map[$head]) ? $map[$head].substr($key, $dot) : $key;
    }
}
