<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PriceItem;
use Illuminate\Support\Collection;

/**
 * ÜRÜN NESLİ — kalemin adındaki G7 / Gen8 / Gen9.
 *
 * "Yeni nesil sunucu öner" diyen bir talebe, listede daha yeni bir nesil
 * dururken eski nesil önerilebilir: model nesli ada bakıp çıkarmak zorunda
 * kalınca yanlış çıkarabilir.
 *
 * Bir dil modelinden çıkarmasını beklemek yerine BİLGİYİ VERİYORUZ: fiyat
 * listesindeki her kalemin yanında nesli ve o kategorideki en yüksek neslin
 * hangisi olduğu yazılı gider. Kural istemde duruyor, veri burada üretiliyor;
 * ikisi birden olmadan biri işe yaramıyor.
 *
 * NESLİ OLMAYAN KALEM YOK SAYILIR: IP, firewall, lisans gibi kalemlerde nesil
 * diye bir şey yok ve uydurma bir "Nesil:" satırı modelin kafasını karıştırır.
 */
class ProductGeneration
{
    /**
     * Addaki nesil numarası.
     *
     * "G" harfi sözcük başında olmalı ve ardından doğrudan rakam (G7) ya da
     * "en" + rakam (Gen9) gelmeli. Bu yüzden "8GB", "1Gbps", "250GB" gibi
     * yazımlar eşleşmez: oralarda G'den sonra harf var.
     */
    public static function nesil(?string $ad): ?int
    {
        if (blank($ad)) {
            return null;
        }

        if (preg_match('/(?<![\p{L}\p{N}])g(?:en)?[\s.-]*(\d{1,2})(?![\p{L}\p{N}])/iu', (string) $ad, $eslesme) !== 1) {
            return null;
        }

        return (int) $eslesme[1];
    }

    /**
     * Kategori başına en yüksek nesil.
     *
     * Karşılaştırma KATEGORİ İÇİNDE yapılır: "Dedicated Sunucu"daki Gen9 ile
     * başka bir kategorideki bir numarayı karşılaştırmanın anlamı olmaz.
     *
     * @return array<string, int>
     */
    public static function enYuksekler(Collection $kalemler): array
    {
        $enYuksek = [];

        foreach ($kalemler as $kalem) {
            $nesil = self::nesil($kalem->name);

            if ($nesil === null) {
                continue;
            }

            $kategori = (string) $kalem->category;

            if (! isset($enYuksek[$kategori]) || $nesil > $enYuksek[$kategori]) {
                $enYuksek[$kategori] = $nesil;
            }
        }

        return $enYuksek;
    }

    /**
     * Fiyat listesinde kalemin altına yazılacak nesil satırı.
     *
     * Tek başına "Nesil: Gen8" yetmiyor: modelin asıl bilmesi gereken, bunun
     * kategorideki EN YENİ nesil olup olmadığı.
     *
     * @param  array<string, int>  $enYuksekler
     */
    public static function satir(PriceItem $kalem, array $enYuksekler): ?string
    {
        $nesil = self::nesil($kalem->name);

        if ($nesil === null) {
            return null;
        }

        $tepe = $enYuksekler[(string) $kalem->category] ?? $nesil;

        return $nesil >= $tepe
            ? "  Nesil: Gen{$nesil} — bu kategorideki EN YENİ nesil"
            : "  Nesil: Gen{$nesil} — daha yenisi var (Gen{$tepe})";
    }
}
