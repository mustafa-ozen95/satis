<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Hangi formun gönderildiği ("_form" gizli alanı).
 *
 * Doğrulama hatasıyla dönen sayfada yalnızca gönderilen formun penceresi
 * açık gelir ve yalnızca o form eski girdiyle dolar; diğerleri kayıtlı
 * değerini korur. Karşılaştırma burada yapılır, şablon hazır değeri okur.
 */
final class FormStatus
{
    public static function matches(string $ad): bool
    {
        return old('_form') === $ad;
    }

    /** Sayfa başına tek "ekle" formu. */
    public static function isAdd(): bool
    {
        return self::matches('ekle');
    }

    /**
     * Kayıt başına "düzenle" formları: kimlik => açık mı.
     *
     * @param  iterable<int|string>  $kimlikler
     * @return array<int|string, bool>
     */
    public static function editMap(iterable $kimlikler): array
    {
        $harita = [];

        foreach ($kimlikler as $kimlik) {
            $harita[$kimlik] = self::matches("duzenle-{$kimlik}");
        }

        return $harita;
    }
}
