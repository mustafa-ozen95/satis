<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Yetki sözlüğü
|--------------------------------------------------------------------------
|
| Panelin tanıdığı kaynaklar ve her kaynakta yapılabilecek işler. Jeton
| ekranındaki kutucuklar, API belgesi ve yetki etiketleri HEP buradan
| çiziliyor; ikinci bir liste yoktur — olsaydı biri değişince öbürü sessizce
| eski kalırdı.
|
| Buradaki her "kaynak:aksiyon" çiftinin karşılığında bir Gate olmak zorunda
| (bkz. AppServiceProvider ve YetkiSozluguTest): sözlükte duran ama kimsenin
| sormadığı bir yetki, işe yaramadığı hâlde veriliyormuş gibi görünür.
|
| Kardeş panellerde aynı dosya aynı işi yapıyor.
|
*/

return [

    'actions' => [
        'view' => 'Görüntüle',
        'create' => 'Ekle',
    ],

    'resources' => [
        'fiyat' => [
            'label' => 'Fiyat kalemleri',
            'actions' => ['view'],
        ],

        'bilgi' => [
            'label' => 'Bilgi bankası',
            'actions' => ['view'],
        ],

        /*
         * Teklif YAZDIRMAK okumaktan ayrı bir yetkidir: teklif istemek panelin
         * dil modelini çalıştırır, okumak yalnızca sonucu alır.
         */
        'teklif' => [
            'label' => 'Teklifler',
            'actions' => ['view', 'create'],
        ],
    ],
];
