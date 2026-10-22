<?php

return [

    /*
    |--------------------------------------------------------------------------
    | İlk Admin Hesabı
    |--------------------------------------------------------------------------
    |
    | Sistemde hiç kullanıcı yokken bir kere otomatik oluşturulan admin hesabı
    | (PANEL_EMAIL / PANEL_PASSWORD). Sonraki kullanıcılar panelden, Kullanıcılar sayfasından eklenir.
    |
    */

    /*
     * Giriş anahtarı E-POSTA (kardeş panellerle aynı); ad yalnızca ekranda
     * görünür. PANEL_USERNAME yalnız geriye uyum için okunur.
     */
    'name' => env('PANEL_NAME', 'Sistem Yöneticisi'),
    'email' => env('PANEL_EMAIL', env('PANEL_USERNAME')),

    /*
     * Sabit bir varsayılan parola YOK: imajı kuran herkeste aynı giriş
     * olurdu. Konteynerde başlatıcı boş parolayı rastgele üretir; başka bir
     * yoldan (elle db:seed) parola verilmezse ilk hesap açılmaz.
     */
    'password' => env('PANEL_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | En kısa parola uzunluğu
    |--------------------------------------------------------------------------
    |
    | Sekiz karakter — kardeş panellerle aynı. Dört karakter parolayı
    | denemeyle bulmayı gerçekçi kılar.
    |
    | Değer TEK YERDE: yönetici ekranı ve profil ekranı aynı sayıyı okur. İki
    | yere ayrı yazılsaydı biri değişince diğeri sessizce eski kalırdı.
    |
    */
    'password_length' => (int) env('PANEL_PASSWORD_LENGTH', 8),

];
