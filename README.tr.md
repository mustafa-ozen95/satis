# SYS — Satış Yönetim Sistemi

[English](README.md) · **Türkçe**

Müşteri talebinden **teklif metni** üreten panel: talebi alır, fiyat listesi ve
bilgi bankasıyla birlikte bir dil modeline verir, çıkan metni satış ekibine
müşteri maili + iç açıklama olarak sunar.

Kimlik Yönetim Merkezi (merkez.ornek.test) ile giriş yapar ve kardeş panellerle
(KYM, EYS, NYS) aynı kalıpta kurulur: tek imaj, tek komut.


## Üç iş

1. **Teklif** — talep yazılır, iş kuyruğa girer, metin hazır olunca sohbet
   ekranında görünür. Konuşmaya devam edilebilir; geçmiş modele birlikte
   gider.
2. **Bilgi bankası** — teklif yazarken kullanılan kurumsal bilgi. Vektör
   deposunda (Chroma) durur, benzerlik araması oradan gelir.
3. **Fiyatlar** — kalem, birim, para birimi ve KDV bilgisi. Teklif üretiminde
   modele bu liste verilir.


## Yetki

Panel içindeki yetkiler **kişiye özeldir** ve yöneticinin Kullanıcılar
ekranından verdiği şeydir: teklif, bilgi bankası, fiyatlar. Kullanıcı
eksik yetkisini panelden talep edebilir; yönetici onaylar.

Merkezdeki rol yalnızca **kapıyı** belirler ve kapının kimin için açılacağına
**merkez** karar verir. Panelde yerel bir izin
listesi yoktur. Rolden yetki türetilmez — merkezde rol dağıtabilen biri,
buradaki hiçbir yöneticinin haberi olmadan kendini burada yetkilendirememeli.


## API

Dışarıdaki sistemler `api/v1` altındaki uçlardan okur ve teklif ister. Kimlik
**jetondur** (`Authorization: Bearer ...`), oturum çerezi kabul edilmez.

Jeton sahibi adına konuşur ve **onu aşamaz**: yetkisi, jetonun kendi listesi
ile sahibinin panel yetkilerinin kesişimidir. Jetonlar ve uç listesi
Ayarlar → **API jetonları** ekranında; belge sayfası rotalardan üretilir.


## Denetim kaydı

Her anlamlı işlem yazılır ve satırlar HMAC-SHA256 ile zincirlenir: bir satırı
sonradan değiştiren ya da silen zinciri kırar.

    php artisan denetim:dogrula


## Kurulum

Kurulum deponun kökündeki `docker-compose.yml` ile (Compose standardı: MariaDB,
redis ve Chroma ayrı servis, panel yalnız HTTP; her platformda aynı dosya).
Ayrıntı ve değişkenler: `docker/README.md`, `.env.example`.

    cp .env.example .env && docker compose up -d --build   # kur / güncelle
    docker compose logs -f panel                           # kayıtlar
    docker compose exec panel supervisorctl status         # servisler

Depoda hiçbir şirketin adı, sitesi ya da iş kuralı durmaz: şirket adı ve site
ortamdan gelir (`SIRKET_ADI`, `SIRKET_SITESI`), şirkete özgü teklif istemi ve
siteden okunacak sayfalar `storage/app/sirket/` altındaki isteğe bağlı
dosyalardır (bkz. `docker/README.md`). Bunlar yoksa panel genel bir istemle
çalışır ve site taramasını atlar.


## Komutlar

| | |
|---|---|
| `php artisan kimlik:esitle` | merkezdeki erişim değişikliklerini işler (zamanlayıcı: her gece 04:10) |
| `php artisan denetim:dogrula` | denetim zincirini doğrular (zamanlayıcı: 03:30) |
| `php artisan queue:work` | teklif üretimi (konteynerde supervisord koşturuyor) |


## Testler

    vendor/bin/phpunit

Sunucuda PHP yok; sınamalar imajın içinden koşar. Nasıl olduğu `CLAUDE.md` →
"Bu Panele Özel" başlığında.


## Kurallar

`CLAUDE.md`. Kısaca: controller ince, yetki Policy'de, validation Form
Request'te, Blade'de PHP yok, güvenlik her şeyden önce gelir ve yorum kodun
**neden** öyle olduğunu anlatır.
