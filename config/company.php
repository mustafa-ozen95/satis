<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Kurulumu yapan şirket
|--------------------------------------------------------------------------
|
| Depoda hiçbir şirketin adı, sitesi ya da iş kuralı durmaz; hepsi kurulumun
| kendisinden gelir. Ad ve site ortamdan (.env ya da platformun değişken
| ekranı), uzun metinler storage/app/company/ altındaki dosyalardan okunur.
| O dizin storage hacmindedir: depoya (.gitignore) ve imaja (.dockerignore)
| girmez, yeniden dağıtımda yerinde kalır.
|
| Hiçbiri verilmezse panel genel bir istemle çalışır ve site taraması atlanır.
|
*/

return [

    /* Teklif isteminde asistanın adına çalıştığı şirket. Boşsa ad geçmez. */
    'name' => env('COMPANY_NAME', ''),

    /*
     * Kurumsal sitenin kök adresi (ör. https://www.ornek.test).
     *
     * İki iş görür: site sayfaları dosyasındaki göreli yollar buna eklenir ve
     * siteden gelen bilgi kayıtlarının "kaynak" etiketi bundan türetilir
     * (alan adı, baştaki "www." atılarak). Etiket veritabanına yazılıyor:
     * adres değişirse eski kayıtlar artık "site kaydı" sayılmaz.
     */
    'website' => env('COMPANY_WEBSITE', ''),

    /*
     * Şirkete özgü TEKLİF İSTEMİ (düz metin, isteğe bağlı).
     *
     * Varsa koddaki genel istemin YERİNE geçer; {price_context},
     * {knowledge_context}, {terms} ve {request} yer tutucularının dördünü de
     * taşımak zorunda, birisi eksikse dosya kullanılmaz ve günlüğe uyarı düşer.
     * Ortam değişkeni değil dosya: istem çok satırlı ve uzun, .env'de ve
     * platformların değişken ekranında tırnak/satır sonu sorunu çıkarırdı.
     */
    'prompt_file' => storage_path('app/company/quote-prompt.txt'),

    /*
     * knowledge:site turunun okuyacağı sayfalar (JSON, isteğe bağlı):
     *
     *     {"/hizmetler": {"baslik": "Hizmetler", "kategori": "Genel"}, ...}
     *
     * Anahtar göreli yol (COMPANY_WEBSITE'ne eklenir) ya da tam adres olabilir.
     * Dosya yoksa tur hiçbir şeye dokunmadan atlanır.
     */
    'site_pages_file' => storage_path('app/company/site-pages.json'),

];
