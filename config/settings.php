<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Panelden değiştirilebilen ayarlar
|--------------------------------------------------------------------------
|
| Anahtar, ÜZERİNE BİNDİRİLEN config yoludur ("session.lifetime"); değer
| veritabanında durur ve satırı olmayan ayar VARSAYILANINDA kalır. Böylece
| config dosyaları her zaman geçerli bir taban olur ve veritabanı yalnızca
| farkları taşır.
|
| BURAYA HER AYAR KONMAZ. Sırlar (API anahtarları, istemci sırrı, veritabanı
| parolası) kurulumun ortamında (.env ya da platformun gizli değişkenleri) kalır: panelden okunabilir hâle getirmek, yönetici
| hesabını ele geçiren birine onları da vermek olurdu. Buraya yalnızca
| DAVRANIŞ ayarları girer.
|
| Kardeş panellerde aynı yapı var; gruplar panele göre
| değişir.
|
*/

/*
 * Hesabın erişebildiği Gemini modelleri (sağlayıcının /v1beta/models listesinden).
 *
 * Liste ELLE tutuluyor: her ayar ekranı açılışında sağlayıcıya sormak,
 * ekranı dışarıdaki bir servisin ayakta olmasına bağlardı. Yeni bir model
 * çıktığında buraya bir satır eklenir.
 */
$geminiModelleri = [
    'gemini-3.6-flash' => 'Gemini 3.6 Flash — önerilen',
    'gemini-3.7-flash' => 'Gemini 3.7 Flash',
    'gemini-3.8-flash' => 'Gemini 3.8 Flash — en yeni, yoğun saatlerde dolabiliyor',
    'gemini-3.5-flash' => 'Gemini 3.5 Flash — bir önceki',
    'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash Lite — en ucuz, en yüzeysel',
];

return [

    'groups' => [

        'panel' => [
            'label' => 'Panel',
            'description' => 'Saat dilimi ve genel davranış.',

            'settings' => [
                'app.timezone' => [
                    'label' => 'Saat dilimi',
                    'hint' => 'Kayıt ve denetim zamanları bu dilimde gösterilir.',
                    'type' => 'select',
                    'options' => [
                        'Europe/Istanbul' => 'Europe/Istanbul',
                        'UTC' => 'UTC',
                    ],
                    'rules' => ['required', 'timezone'],
                ],
            ],
        ],

        'guvenlik' => [
            'label' => 'Güvenlik',
            'description' => 'Oturum ve parola kuralları.',

            'settings' => [
                'session.lifetime' => [
                    'label' => 'Oturum süresi',
                    'hint' => 'HAREKETSİZLİK süresidir: sayaç her istekte sıfırlanır. '
                        .'Bu panelde hatırlama çerezi verilmiyor, yani süre gerçekten süre.',
                    'unit' => 'dakika',
                    'type' => 'int',
                    'rules' => ['integer', 'min:5', 'max:1440'],
                ],

                'panel.parola_uzunluk' => [
                    'label' => 'En kısa parola uzunluğu',
                    'hint' => 'Yeni parolalarda geçerli; var olan parolalar etkilenmez.',
                    'unit' => 'karakter',
                    'type' => 'int',
                    'rules' => ['integer', 'min:6', 'max:64'],
                ],
            ],
        ],

        'kimlik' => [
            'label' => 'Kimlik Yönetim Merkezi',
            'description' => 'Merkezle giriş. HANGİ ROLÜN GİREBİLECEĞİ ve hesabın '
                .'kendiliğinden açılıp açılmayacağı BURADA DEĞİL, merkezdeki '
                .'İstemciler ekranındadır — erişim kararı doğası gereği merkezin '
                .'işidir. İstemci kimliği ve sırrı kurulumun ortam değişkenlerindedir.',

            'settings' => [
                'kimlik.acik' => [
                    'label' => 'Merkezle giriş',
                    'hint' => 'Kapalıyken giriş ekranında düğme çizilmez ve uçlar 404 döner. '
                        .'Panelin kendi parola girişi her hâlükârda açık kalır.',
                    'type' => 'bool',
                    'rules' => ['boolean'],
                ],

                'kimlik.yonetici_devri' => [
                    'label' => 'Merkezdeki yönetici burada da yönetici olsun',
                    'hint' => 'KAPALI TUTUN. Açıkken merkezde rol dağıtabilen biri, buradaki '
                        .'hiçbir yöneticinin haberi olmadan kendini burada da yönetici yapabilir.',
                    'type' => 'bool',
                    'rules' => ['boolean'],
                ],

                'kimlik.dogrulama_dakika' => [
                    'label' => 'Merkeze sorma aralığı',
                    'hint' => '"Erişimi kalkan biri en fazla ne kadar daha içeride kalabilir" '
                        .'sorusunun yanıtı. Her istekte sorulmaz; yanıt kullanıcı başına '
                        .'bu süre kadar önbelleklenir.',
                    'unit' => 'dakika',
                    'type' => 'int',
                    'rules' => ['integer', 'min:1', 'max:60'],
                ],
            ],
        ],

        'teklif' => [
            'label' => 'Teklif motoru',
            'description' => 'Metni üreten modeller. Anahtarlar kurulumun ortam değişkenlerindedir.',

            'settings' => [
                /*
                 * MODELLER LİSTEDEN SEÇİLİR.
                 *
                 * Serbest metin kutusuydu: bir harf hatası ya da anahtarın
                 * erişemediği bir ad, teklif üretimini ancak ilk istekte —
                 * kullanıcının karşısında — patlatıyordu. Liste, hesabın
                 * gerçekten erişebildiği modellerden (/v1beta/models).
                 *
                 * Yeni bir model çıktığında buraya bir satır eklenir;
                 * geçiş tek tık olur.
                 */
                'services.gemini.model' => [
                    'label' => 'Gemini modeli',
                    'hint' => 'Teklifi yazan model. Önerilen 3.6; '
                        .'3.7 ve 3.8 daha yeni ama yoğun saatlerde "high demand" dönebiliyor.',
                    'type' => 'select',
                    'options' => $geminiModelleri,
                    'rules' => ['required', 'string', 'max:100'],
                ],

                'services.gemini.yedek_model' => [
                    'label' => 'Gemini yedek modeli',
                    'hint' => 'Birincisi "yoğunluk" dediğinde aynı sağlayıcıda denenir; '
                        .'sağlayıcı değiştirmeden önce buraya bakılır.',
                    'type' => 'select',
                    'options' => $geminiModelleri,
                    'rules' => ['nullable', 'string', 'max:100'],
                ],

                'services.groq.model' => [
                    'label' => 'Groq modeli (yedek sağlayıcı)',
                    'hint' => 'Gemini modellerinin ikisi de yanıt vermezse devreye girer.',
                    'type' => 'string',
                    'mono' => true,
                    'rules' => ['required', 'string', 'max:100'],
                ],

                'services.llm.timeout' => [
                    'label' => 'Yanıt bekleme süresi (saniye)',
                    'hint' => 'Uzun teklif metinleri 30 saniyeyi aşabiliyor; kısa tutmak '
                        .'çalışan bir isteği "başarısız" saydırır.',
                    'type' => 'int',
                    'rules' => ['required', 'integer', 'between:15,600'],
                ],

                'services.llm.retry' => [
                    'label' => 'Yeniden deneme sayısı',
                    'hint' => 'Kota (429) ve sağlayıcı arızası (5xx) geçicidir; '
                        .'kalıcı hatalar zaten tekrarlanmaz.',
                    'type' => 'int',
                    'rules' => ['required', 'integer', 'between:1,6'],
                ],

                'services.llm.temperature' => [
                    'label' => 'Sıcaklık',
                    'hint' => 'Düşük değer fiyat listesine sadakat demektir. 0 ile 1 arası.',
                    'type' => 'string',
                    'rules' => ['required', 'numeric', 'between:0,1'],
                ],
            ],
        ],

        /*
         * TEKLİFİN TİCARİ KOŞULLARI.
         *
         * Her teklifte aynı olan şeyler modele bırakılmaz: model süreyi
         * atlayabiliyor ya da KDV oranını kendi varsayabiliyor. Buradaki
         * değerler isteme her zaman girer ve müşteri mailinin sonuna sistem
         * tarafından eklenir.
         */
        'teklif_kosullari' => [
            'label' => 'Teklif koşulları',
            'description' => 'Her teklifte geçerli olan süreler ve KDV oranı. '
                .'Model bunları uydurmaz; burada ne yazıyorsa teklifte o yazar.',

            'settings' => [
                'teklif.kdv_orani' => [
                    'label' => 'KDV oranı',
                    'hint' => 'Fiyatı "+KDV" olan kalemlerde hesap bu oranla yapılır. '
                        .'Bilgi bankasındaki ödeme koşullarıyla aynı olmalı.',
                    'unit' => '%',
                    'type' => 'int',
                    'rules' => ['required', 'integer', 'between:0,100'],
                ],

                'teklif.hizmet_suresi' => [
                    'label' => 'Hizmet süresi / faturalama',
                    'hint' => 'Teklifte "Hizmet süresi" satırı olarak yazılır. '
                        .'Taahhütlü çalışılıyorsa burada yazmalı.',
                    'type' => 'string',
                    'rules' => ['required', 'string', 'max:300'],
                ],

                'teklif.kurulum_suresi' => [
                    'label' => 'Kurulum / teslim süresi',
                    'hint' => 'Teklifte "Kurulum / teslim süresi" satırı olarak yazılır.',
                    'type' => 'string',
                    'rules' => ['required', 'string', 'max:300'],
                ],

                'teklif.gecerlilik_gun' => [
                    'label' => 'Teklif geçerlilik süresi',
                    'hint' => 'Teklifin kaç gün geçerli olduğu.',
                    'unit' => 'gün',
                    'type' => 'int',
                    'rules' => ['required', 'integer', 'between:1,365'],
                ],
            ],
        ],
    ],
];
