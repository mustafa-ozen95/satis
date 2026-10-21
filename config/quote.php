<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Teklifin ticari koşulları
|--------------------------------------------------------------------------
|
| Her teklifte AYNI olan şeyler: KDV oranı, hizmet süresi, kurulum süresi ve
| teklifin geçerlilik süresi. Bunlar modele bırakılmaz.
|
| Modele bırakıldıklarında iki şekilde yanılıyor:
|
|  - SÜRE HİÇ YAZILMIYOR. Fiyat "TL / ay" olarak geçiyor ama hizmetin ne
|    kadar süreyle verildiği, ne zaman teslim edileceği teklifte olmuyor.
|  - KDV ORANI UYDURULUYOR. Model kendi bildiği bir oranı varsayıyor. Oran
|    bilgi bankasında dursa bile oraya ANLAM ARAMASIYLA ulaşılıyor: "16gb
|    ram sunucu öner" sorgusu ödeme koşullarını getirmiyor, dolayısıyla
|    model oranı hiç görmüyor.
|
| Her teklifte geçerli olan bir koşul, arama sonucuna bağlı olamaz: buradaki
| değerler İSTEME HER ZAMAN girer ve müşteri mailinin sonuna sistem tarafından
| eklenir (bkz. App\Support\QuoteTerms).
|
| Panelden değiştirilebilir (Ayarlar → Teklif koşulları); buradaki değerler
| yalnızca varsayılandır.
|
*/

return [

    /*
     * KDV oranı (yüzde).
     *
     * Bilgi bankasındaki "Genel Ödeme ve KDV Koşulları" kaydıyla aynı olmalı.
     */
    'kdv_orani' => 20,

    /*
     * Hizmet süresi ve faturalama dönemi.
     *
     * Fiyat listesindeki birim "ay"; teklifte de bu yazar. Taahhütlü bir
     * çalışma için satış ekibi ayrıca fiyatlandırır — model kendiliğinden
     * "12 ay taahhüt" gibi bir süre uyduramasın diye cümle burada duruyor.
     */
    'hizmet_suresi' => 'Aylık dönem hâlindedir ve dönem sonunda yenilenir. '
        .'Farklı bir süre veya taahhüt talep edilirse satış ekibi ayrıca fiyatlandırır.',

    /* Kurulum/teslim süresi (bilgi bankasındaki kayıtla aynı cümle). */
    'kurulum_suresi' => 'Stoktaki modellerde kurulum hızlıdır; '
        .'özel yapılandırmalarda teslim süresi satış ekibince teyit edilir.',

    /* Teklifin kaç gün geçerli olduğu. */
    'gecerlilik_gun' => 30,

];
