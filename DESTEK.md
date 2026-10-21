# Neler destekleniyor — işletim notları

Bu dosya panelin özelliklerini işletim gözüyle anlatır: ne nasıl çalışır, bir
şey ters gittiğinde nereye bakılır. Kurulum ve güncelleme `docker/README.md`'de,
geliştirme kuralları `CLAUDE.md`'de.


## Giriş ve erişim

İki yol var ve ikisi de açık:

1. **Kimlik Yönetim Merkezi** (KYM) — giriş ekranındaki düğme.
   Parola bu panele hiç uğramaz.
2. **Panelin kendi e-postası/parolası** — merkez kapalıyken de girilsin
   diye. Merkez düştüğü gün panel kilitlenmez.

**Kimin gireceğine merkez karar verir**. Liste panelde DEĞİL, merkezdeki **Uygulamalar** ekranında, satış
istemcisinin kaydında durur; değiştirmek için buraya dokunulmaz. Merkezde
tanınan ama panelde hesabı olmayan birine, yetkisi varsa hesap kendiliğinden
açılır.

Kural iki uçta birden işler:

- girişte reddedilir ve red **denetim kaydına yazılır**,
- sonradan rolü ya da durumu değişeni merkez **anında** bildirir; panel
  doğruyu merkezden kendi sorar. Gecelik eşitleme turu (04:10) yedektir.

Merkezde hesabı askıya alınan burada da kapanır; merkezde geri açılan burada
da açılır. **Merkez erişilemezse kimse atılmaz** — "sorulamadı"yı "erişimi
yok" saymak, bir kesintide bütün kadroyu dışarı atmak olurdu.

Panel içindeki yetkiler (teklif / bilgi bankası / fiyatlar) **merkezden
gelmez**: kişiye özeldir ve yöneticinin Kullanıcılar ekranından verdiği
şeydir.


## Denetim kaydı — değiştirilemez

Panelde yapılan her anlamlı işlem `activity_logs` tablosuna yazılır: giriş,
başarısız giriş denemesi, çıkış, kullanıcı açma/güncelleme/silme, yetki
talebi onayı, fiyat ve bilgi bankası değişiklikleri, teklif üretimi.

Satırlar **HMAC-SHA256 ile birbirine zincirlenir**: her satır bir öncekinin
özetini taşır. Bir satırı sonradan değiştiren ya da silen zinciri kırar.

```sh
docker exec satis php artisan denetim:dogrula
```

Zamanlayıcı bunu her gece 03:30'da koşturur. Çıktı "ZİNCİR KIRIK" diyorsa
kayıt ÜZERİNDE OYNANMIŞ demektir — ya da satırlar elle silinmiştir.

Anahtar ortamdaki `DENETIM_ANAHTARI` ve **APP_KEY'den ayrıdır**: aynı anahtar
kullanılsaydı APP_KEY döndürüldüğü gün bütün geçmiş "kırık" görünürdü.

Denetim satırı canlı bir ilişki değil **tarihsel bir kayıttır**: hesap
silinse bile satır duruyor, kullanıcı kimliği ve o günkü adı yerinde kalıyor.


## Hata kaydı

Kuyrukta ya da istek içinde patlayan hatalar `error_logs` tablosuna da düşer;
sunucuya girmeden görülebilsin diye. Aynı hata yeni satır açmaz, sayacı artar.

```sh
docker exec satis php artisan tinker --execute='
  foreach (App\Models\ErrorLog::latest("last_seen_at")->limit(10)->get() as $h) {
      echo $h->last_seen_at." (".$h->occurrences.") ".$h->type.": ".$h->message.PHP_EOL;
  }'
```

Doğrulama hatası, yetkisiz erişim ve 404 **yazılmaz**: onlar uygulamanın
normal çalışmasıdır ve listeyi kullanılmaz hâle getirirlerdi.


## Teklif üretimi

Metni Gemini üretiyor, kota dolduğunda Groq'a düşülüyor; benzer kayıtlar
bilgi bankasından (Chroma) çekiliyor. İş **kuyrukta** koşar: LLM yanıtı
dakikalar sürebiliyor ve bir web isteğinin içinde beklenemez.

Teklif "üretiliyor"da takılı kaldıysa sırayla:

```sh
docker exec -it satis supervisorctl status isci   # işçi ayakta mı
docker logs satis 2>&1 | tail -50                  # ne dedi
# hata kaydına bakın (yukarıda)
```

İş iki kez denenir (`GenerateTeklifJob::$tries`); ikisi de düşerse konuşma
"hata" durumuna geçer ve sebebi hata kaydında durur.


## API jetonları

Dışarıdaki bir sistemin (site, teklif aracı) panele bağlanması gerekiyorsa
**Ayarlar → API jetonları** ekranından jeton çıkarılır. Aynı ekranda
**Belge** düğmesi uçların listesini, gereken yetkileri ve örnek isteği
gösterir; belge rotalardan üretilir, elle güncellenmez.

Bilinmesi gerekenler:

- **Sır bir kez görünür.** Kaybolursa geri gösterilemez; "Yeni sır üret"
  denir ve eski jeton o anda geçersiz olur.
- **Jeton bir kişinin adına çalışır ve onun yetkisini aşamaz.** Sahibinin
  yetkisi Kullanıcılar ekranından daraltılınca jeton da daralır.
- **İzinli adres** yazılabilir (tek IP ya da `192.0.2.0/24`). Boş bırakılırsa
  her yerden kabul edilir.
- **Ömür** gün cinsindendir; `0` süresiz demektir. Süresiz jeton unutulur,
  unutulan jeton sızar — bir tarih koymak iyidir.
- Jeton sızdıysa: **İptal et**. Satır silinmez, geçmiş okunabilir kalır.
- Her istek dakikada 120 ile sınırlı ve sınır **jeton başına**dır.

Jeton üretmek yalnızca yöneticinin işidir: jeton, birinin yetkisini kalıcı
bir anahtara dönüştürür.


## Yedek

Yedeklenecek iki şey var ve **birlikte** yedeklenmeli:

- veritabanı (compose'taki `db` servisi ya da dışarıdaki MariaDB)
- APP_KEY ve DENETIM_ANAHTARI (ortamda; APP_KEY boşsa `storage` hacmindeki
  `app-key.txt`)

Anahtar olmadan yedekten dönülen veritabanında oturumlar açılmaz ve denetim
zinciri doğrulanamaz. Bilgi bankasının vektör deposu (Chroma hacmi) veritabanından
yeniden kurulabilir (`bilgi:indeksle`).
