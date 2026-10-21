# satis — konteyner kurulumu

Kurulum deponun kökündeki `docker-compose.yml` ile yapılır. Dosya Compose
standardındadır (compose-spec.io): aynı dosya `docker compose` ile de, NineDeploy,
Coolify, Dokploy, Portainer gibi bir panelin "Git deposundan compose" kurulumuyla da
çalışır. Ayarların hepsi ortamdan gelir; liste ve açıklamalar `.env.example`'da.

    cp .env.example .env          # en az APP_URL, DB_PASSWORD ve bir LLM anahtarı; chmod 600 .env
    docker compose up -d --build

Bir platformda: depoyu "compose" türünde ekleyin (dosya `docker-compose.yml`), ana
servis `panel`, port 80, sağlık yolu `/up`; `.env.example`'daki adları platformun
değişken ekranına girin, LLM anahtarlarını "gizli" işaretleyin.

**HTTP portu dışarı açılmaz.** Alan adını ve TLS'i önündeki vekil verir (platformun
kendisi ya da Caddy/nginx); panel isteğin HTTPS olduğunu `X-Forwarded-Proto`'dan bilir.
Vekilsiz deneme: `cp docker-compose.override.example.yml docker-compose.override.yml`
(düz HTTP, port 8080).


## Servisler

| Servis | Ne | Not |
|---|---|---|
| `panel` | nginx + php-fpm + kuyruk işçisi + zamanlayıcı, supervisord altında | yalnız 80 |
| `db` | MariaDB 11.4 | `VT_KOPYA=0` ile kalkmaz (dışarıdaki veritabanı) |
| `redis` | önbellek, oturum, kuyruk | kalıcılık kapalı: yeniden başlatmada oturumlar düşer, veri düşmez |
| `chroma` | bilgi bankasının vektör deposu (`chromadb/chroma` 1.4.4, özetle sabit) | port açmaz; panel `http://chroma:8000` ile ulaşır |

Chroma Python servisi olduğu için imaja katılmıyor. **Dışarıya port açmaz**: kimlik
doğrulaması olmayan bir servis açık portta bilgi bankasını okumanın ve silmenin yolu
olurdu. Panel ile aynı compose ağında olmak zorunda; `chroma` adı panelin içinden
çözülmezse teklifler bilgi bankası olmadan, hiçbir hata vermeden yazılır. Her
değişiklikten sonra bakın:

    docker compose exec panel curl -s -o /dev/null -w '%{http_code}\n' http://chroma:8000/api/v2/heartbeat

200 değilse bilgi bankası ölüdür.

`AG_YALITIMI=true` konteynerleri kimlik merkezinden, LLM servislerinden (Gemini, Groq) ve
dışarıdan yalıtır (dış dünyadan ayrık bir deneme kurulumu için); teklif üretimi o zaman
çalışmaz.


## Hacimler

Adlar `ONEK` ile başlar (varsayılan `satis`); aynı sunucuda ikinci kurulum onu değiştirir.

| Hacim | İçerik |
|---|---|
| `satis_storage` | `storage/app`: APP_KEY (verilmediyse), ilk parola dosyası, Ayarlar → Veritabanı'nın bağlantı dosyası (`veritabani/`), şirkete özgü istem ve site sayfaları (`sirket/`) |
| `satis_veritabani` | db servisinin veri dizini — kullanıcılar, konuşmalar, fiyat kalemleri, denetim kaydı |
| `satis_logs` | `storage/logs` |
| `satis-chroma-data` | Chroma'nın vektör deposu (`CHROMA_HACMI`) |

Chroma'nın konteyner adı (`CHROMA_KONTEYNERI`, varsayılan `satis-chroma`) ve hacmi
(`CHROMA_HACMI`, varsayılan `satis-chroma-data`) **ONEK'e bağlı değil**. Bilgi bankası
başka adlı bir hacimde duran kurulum o adları ortamda verir; yanlış hacim bilgi bankasını
boş açar. Aynı sunucuda ikinci kurulumda ikisi de değiştirilir, yoksa iki kurulum aynı
bilgi bankasını paylaşır.


## ⚠ APP_KEY

Oturum çerezleri ve şifrelenen her alan buna bağlı. Boş bırakılırsa ilk açılışta
üretilir ve `storage/app/app-key.txt` içinde saklanır (değeri günlüğe basılmaz); oradan alıp
ortama yazın ve **storage hacmiyle birlikte yedekleyin**.


## Teklif motoru

Metni Gemini üretiyor; kota dolduğunda Groq'a düşülür (`GEMINI_API_KEY`, `GROQ_API_KEY`
ve modelleri, `.env.example`). İkisi de boşsa teklif üretimi çalışmaz: kuyruktaki iş
"hata" durumuyla biter. Anahtarlar yalnız kurulumun ortamında durur; koda, depoya ve
panelin Ayarlar ekranına girmez.

### Şirkete özgü ayarlar

Depoda hiçbir şirketin adı, sitesi ya da iş kuralı durmaz; hepsi kurulumdan gelir
(`config/sirket.php`). Hiçbiri verilmezse panel genel bir istemle çalışır ve site
taraması atlanır.

| Ayar | Ne |
|---|---|
| `SIRKET_ADI` | teklif isteminde asistanın adına çalıştığı şirket; boşsa ad geçmez |
| `SIRKET_SITESI` | kurumsal sitenin kök adresi; `bilgi:siteden` sayfaları buna göre okur, siteden gelen kayıtların kaynak etiketi bunun alan adıdır (`www.` atılır) |
| `storage/app/sirket/teklif-istemi.txt` | şirkete özgü teklif istemi; varsa genel istemin **yerine** geçer. `{fiyat_baglami}`, `{bilgi_baglami}`, `{kosullar}`, `{talep}` yer tutucularının dördü de olmalı, biri eksikse dosya kullanılmaz ve günlüğe uyarı düşer |
| `storage/app/sirket/site-sayfalari.json` | `bilgi:siteden`'in okuyacağı sayfalar: `{"/yol": {"baslik": "...", "kategori": "..."}}`; anahtar göreli yol ya da tam adres. Dosya yoksa tur hiçbir kayda dokunmadan biter |

İki dosya storage hacmindedir (`satis_storage`): depoya ve imaja girmez, yeniden
dağıtımda yerinde kalır, her kullanımda yeniden okunur (yeniden başlatma gerekmez).
Panel `satis` kullanıcısıyla okur:

    docker compose cp teklif-istemi.txt panel:/uygulama/storage/app/sirket/teklif-istemi.txt
    docker compose exec panel chown -R satis:satis /uygulama/storage/app/sirket

Ortam değişkeni değil dosya, çünkü istem çok satırlı ve uzun: `.env`'de ve platformların
değişken ekranında tırnak ve satır sonu sorunu çıkarırdı.

Kuyruk işçisi teklif üretimini koşturuyor; `--timeout=600` çünkü LLM yanıtı
dakikalar sürebiliyor. İşin kendisi `tries = 2` diyor ve bu değer komut
satırındakini yener — iki yere ayrı yazılsaydı biri değişince diğeri sessizce
eski kalırdı. Panelin kapanış payı bu yüzden 15 dakika (`stop_grace_period`):
yarıda kesilen iş konuşmayı "üretiliyor" durumunda bırakırdı.


## Veritabanı

Panel veritabanını ortamdan alır (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`). Varsayılan compose'taki `db` servisi; parolası `DB_PASSWORD`, ilk
açılışta kullanıcı ve veritabanı bununla kurulur. Açılışta panel veritabanını en çok
üç dakika bekler, gelmezse açılmaz (konteyner yeniden dener).

Dışarıdaki ya da platformun yönettiği bir MariaDB/MySQL: `DB_*`'ı ona çevirin ve
`VT_KOPYA=0` deyin.

Oturumun saat dilimi konteynerin saatidir (`DB_TIMEZONE`, varsayılan `+03:00`): UTC'de
çalışan bir sunucuda TIMESTAMP sütunları 3 saat kaymış okunmasın.

### Panelden taşıma (Ayarlar → Veritabanı)

Yalnız yönetici. Panel hedefi sınar: bağlantı, sürüm, hedefin boş ve şu anki
veritabanından farklı olması, göçlerin ihtiyaç duyduğu yetkiler (tablo, dizin, yabancı
anahtar, değişiklik, yazma, silme). Sınama geçerse **Taşı** konteyneri yeniden başlatır.
Açılışta panel kapalıyken şu anki veritabanı hedefe kopyalanır ve her tablonun satır
sayısı iki tarafta karşılaştırılır. Bir adım tutmazsa panel şu anki veritabanıyla açılır
ve sebep sekmede görünür.

Panelden tanımlanan bağlantı `storage/app/veritabani/baglanti.json` dosyasında APP_KEY ile
şifreli durur ve ortamdaki `DB_*` değerlerinin yerine geçer. `durum.json` taşımanın
aşamasını tutar ve sır içermez. Yeniden başlatmayı konteyner içindeki `dc-yeniden-baslat`
yapar; bunun için yeniden başlatma politikası **`unless-stopped`** olmalı (compose'ta öyle).

Taşıma bittikten sonra eski veritabanına **kendiliğinden dönülmez**; taşıma anındaki
hâliyle durur. Bilerek dönmek için: uzak veritabanının dökümünü eskisine yükleyin,
sonra `storage/app/veritabani` altındaki `baglanti.json` ve `durum.json`'u silip
konteyneri yeniden başlatın. Dökümü yüklemezseniz taşımadan sonraki her şey kaybolur.

Chroma taşınmaz: bilgi bankası kendi hacminde kalır.

APP_KEY değişirse bağlantı dosyası çözülemez ve panel açılmaz.


## İlk giriş

Kullanıcı tablosu boşsa başlatıcı bir yönetici açar (`PANEL_EMAIL`; adres verilmezse
hesap açılmaz). `PANEL_PASSWORD` boşsa parola **rastgele üretilir** ve
`storage/app/ilk-parola.txt` içine yazılır (günlüğe basılmaz). Sabit bir varsayılan parola konulmaz: imajı
kuran herkeste aynı giriş bulunurdu. Aynı açılışta fiyat listesi de yüklenir
(`DatabaseSeeder`).

    docker compose exec panel cat /uygulama/storage/app/ilk-parola.txt

Giriş yapıp parolayı değiştirdikten sonra o dosyayı silin.


## Güncelleme

    git pull && docker compose up -d --build

Bir platformda: yeniden dağıtın (platform depoyu çekip derler). Göçler her açılışta
`--isolated` ile koşar; iki örnek aynı anda kalksa bile bir kez çalışır.

GitHub Actions her main'de imajı derleyip `ghcr.io/mustafa-ozen95/satis` altına da
yükler (`.github/workflows/imaj.yml`): hazır imaj isteyen kurulum `docker/Dockerfile`'ı
derlemek yerine onu kullanabilir.

### Güncelleme kontrolü

Ayarlar → Güncelleme (yalnız yönetici) çalışan sürümü GitHub'da main dalının **son başarılı
imaj derlemesiyle** karşılaştırır: kaç commit geride, hangi commit'ler yeni. Yön de ölçülür;
çalışan sürüm son derlemeden yeniyse "güncelleme" denmez. Kontrol her sabah 06:50'de kendiliğinden
yapılır (`guncelleme:denetle`). Yeni sürüm varsa yöneticiler panelin üstünde uyarı görür.
Ekran **yalnızca bakar**; güncelleme sunucudan, yedekle ve onayla yapılır.

- Çalışan sürüm imaja derlemede yazılır (`ARG SURUM` → `UYGULAMA_SURUMU`; iş akışı commit'i verir).
- Depo açık: jeton gerekmez, GitHub'a jetonsuz sorulur (sınır IP başına saatte 60 istek). Depo
  özelse `GUNCELLEME_JETONU` salt okunur bir jeton olmalı (fine-grained, yalnız panel depoları,
  Contents ve Actions: read). Erişilemezse kontrol "hata" der, panelin başka yeri etkilenmez.
- Çalışan sürüm derlemede imaja yazılır (`ARG SURUM`, iş akışı commit'i verir). Derlemeye commit
  vermeyen platformlarda (NineDeploy gibi) bağlamdaki `.git`'ten okunur (`docker/surum-oku.sh`;
  `.dockerignore` yalnız `HEAD` ve ref'leri alır).
- Panel GitHub'a çıkamıyorsa (kapalı ağ) kontrol "erişilemedi" der.


## Sertifika sekmesi

Ayarlar → Sertifika, sunucudaki sertifika ajanının durumunu gösterir ve ona tur isteği
bırakır (`/etc/dc/sertifika-durum.json`, `/etc/dc/sertifika-panel`). Standart kurulumda
TLS'i vekil yönettiği için bu dosyalar bağlı değildir; sekme "bilinmiyor" der ve
düğmeler çalışmaz. Depodaki `sertifika.sh`, `sertifika-ajan.sh` ve
`docker/sertifika-kur.sh`, önünde vekil olmadan TLS'i panelin kendisinin bitirdiği kurulum
içindir.


## Sorun giderme

    docker compose logs -f panel
    docker compose exec panel supervisorctl status
    docker compose exec panel php artisan tinker
