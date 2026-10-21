<?php

declare(strict_types=1);

namespace App\Support;

/**
 * TEKLİFİN SABİT KOŞULLARI — süreler ve KDV oranı.
 *
 * Bu bilgiler her teklifte aynıdır, dolayısıyla her teklifte modelden yeniden
 * yazmasını istemek yanlış yerden istemekti:
 *
 *  - Süre yazılmayabiliyor: fiyat "TL / ay" olarak geçiyor ama hizmetin
 *    süresi, teslim zamanı ve teklifin geçerliliği teklifte olmuyor.
 *  - KDV oranı uydurulabiliyor: model ayardaki oran yerine kendi bildiği bir
 *    oranı varsayıyor.
 *
 * İki yerde birden kullanılır:
 *  - İSTEMDE, model hesabı doğru yapsın ve başka bir süre uydurmasın diye.
 *  - MÜŞTERİ MAİLİNİN SONUNDA, metin ne yazarsa yazsın süreler orada dursun
 *    diye. Modelin yazmasına bırakılsaydı yine unutulabilirdi.
 *
 * Değerler config/teklif.php'de; panelden değiştirilebiliyor.
 */
class TeklifKosullari
{
    /** Mailin sonundaki bloğun başlığı; iki kez eklenmemesi de buradan anlaşılır. */
    public const BASLIK = 'SÜRELER VE GEÇERLİLİK';

    public static function kdvOrani(): int
    {
        return (int) config('teklif.kdv_orani', 20);
    }

    /** İsteme giren blok: modelin uyacağı koşullar. */
    public static function istemMetni(): string
    {
        return "GEÇERLİ KOŞULLAR (sabittir, uydurulmaz):\n"
            .'- KDV oranı: %'.self::kdvOrani()."\n"
            .'- Hizmet süresi / faturalama: '.self::hizmetSuresi()."\n"
            .'- Kurulum / teslim süresi: '.self::kurulumSuresi()."\n"
            .'- Teklif geçerlilik süresi: '.self::gecerlilikGun()." gün\n"
            .'(Bu satırlar teklifin sonuna SİSTEM tarafından eklenir; mailde tekrar yazma.)';
    }

    /** Müşteri mailinin sonuna eklenen blok. */
    public static function mailMetni(): string
    {
        return self::BASLIK."\n"
            .'- Hizmet süresi: '.self::hizmetSuresi()."\n"
            .'- Kurulum / teslim süresi: '.self::kurulumSuresi()."\n"
            .'- Teklif geçerlilik süresi: '.self::gecerlilikGun().' gün';
    }

    /**
     * Bloğu müşteri mailine ekler.
     *
     * İKİ KOŞULLA:
     *
     *  - Yanıtta FİYAT GEÇİYORSA. Panel teklif dışında bilgi sorularına da
     *    cevap veriyor ("iletişim bilgileriniz nedir"); adres bilgisinin
     *    altına hizmet süresi eklemek gürültüden başka bir şey olmaz.
     *  - Blok HENÜZ YOKSA. Model kuralı dinleyip yine de yazdıysa ikinci bir
     *    kopya çıkmasın.
     */
    public static function mailEkle(string $mail): string
    {
        if (! self::fiyatGeciyor($mail) || str_contains($mail, self::BASLIK)) {
            return $mail;
        }

        return rtrim($mail)."\n\n".self::mailMetni();
    }

    /**
     * Metinde fiyat var mı?
     *
     * Rakamın hemen ardından para birimi aranır ("1.250,00 TL"): yalnızca
     * rakam aramak telefon numarasını, yalnızca "TL" aramak bir cümleyi
     * fiyat sayardı.
     */
    private static function fiyatGeciyor(string $mail): bool
    {
        return preg_match('/\d[\d.,]*\s*(TL|TRY|USD|EUR|₺|\$|€)(?![\p{L}])/iu', $mail) === 1;
    }

    private static function hizmetSuresi(): string
    {
        return trim((string) config('teklif.hizmet_suresi'));
    }

    private static function kurulumSuresi(): string
    {
        return trim((string) config('teklif.kurulum_suresi'));
    }

    private static function gecerlilikGun(): int
    {
        return (int) config('teklif.gecerlilik_gun', 30);
    }
}
