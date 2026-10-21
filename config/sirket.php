<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Kurulumu yapan şirket
|--------------------------------------------------------------------------
|
| Depoda hiçbir şirketin adı, sitesi ya da iş kuralı durmaz; hepsi kurulumun
| kendisinden gelir. Ad ve site ortamdan (.env ya da platformun değişken
| ekranı), uzun metinler storage/app/sirket/ altındaki dosyalardan okunur.
| O dizin storage hacmindedir: depoya (.gitignore) ve imaja (.dockerignore)
| girmez, yeniden dağıtımda yerinde kalır.
|
| Hiçbiri verilmezse panel genel bir istemle çalışır ve site taraması atlanır.
|
*/

return [

    /* Teklif isteminde asistanın adına çalıştığı şirket. Boşsa ad geçmez. */
    'ad' => env('SIRKET_ADI', ''),

    /*
     * Kurumsal sitenin kök adresi (ör. https://www.ornek.test).
     *
     * İki iş görür: site sayfaları dosyasındaki göreli yollar buna eklenir ve
     * siteden gelen bilgi kayıtlarının "kaynak" etiketi bundan türetilir
     * (alan adı, baştaki "www." atılarak). Etiket veritabanına yazılıyor:
     * adres değişirse eski kayıtlar artık "site kaydı" sayılmaz.
     */
    'site' => env('SIRKET_SITESI', ''),

    /*
     * Şirkete özgü TEKLİF İSTEMİ (düz metin, isteğe bağlı).
     *
     * Varsa koddaki genel istemin YERİNE geçer; {fiyat_baglami},
     * {bilgi_baglami}, {kosullar} ve {talep} yer tutucularının dördünü de
     * taşımak zorunda, birisi eksikse dosya kullanılmaz ve günlüğe uyarı düşer.
     * Ortam değişkeni değil dosya: istem çok satırlı ve uzun, .env'de ve
     * platformların değişken ekranında tırnak/satır sonu sorunu çıkarırdı.
     */
    'istem_dosyasi' => storage_path('app/sirket/teklif-istemi.txt'),

    /*
     * bilgi:siteden turunun okuyacağı sayfalar (JSON, isteğe bağlı):
     *
     *     {"/hizmetler": {"baslik": "Hizmetler", "kategori": "Genel"}, ...}
     *
     * Anahtar göreli yol (SIRKET_SITESI'ne eklenir) ya da tam adres olabilir.
     * Dosya yoksa tur hiçbir şeye dokunmadan atlanır.
     */
    'site_sayfalari_dosyasi' => storage_path('app/sirket/site-sayfalari.json'),

];
