<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BilgiKaydi;
use App\Services\KnowledgeBaseService;
use Illuminate\Database\Seeder;

/**
 * Sektörde genel geçer, doğrulanabilir teknik/kavramsal bilgiler (RAID,
 * DDoS türleri, Tier sınıflandırması, güç/soğutma birimleri, vb.).
 *
 * BUNLAR ŞİRKETE ÖZEL İDDİA DEĞİLDİR. "Şirket şunu sunuyor/sunmuyor" gibi
 * cümleler kasıtlı olarak yok; yalnızca sektörün ortak tanım ve
 * terminolojisi var. Şirkete özgü kural/istisna/fiyat bilgisi
 * KnowledgeBaseSeeder'da (kaynak: ilk-kurulum) ve sitedeki gerçek
 * içerik `bilgi:siteden` ile (kaynak: sitenin alan adı) ayrı duruyor.
 *
 * NEDEN AYRI KAYNAK ETİKETİ (egitim-genel): `BilgiSitedenCommand`,
 * kaynak alanı sitenin alan adı olan kayıtları site taramasıyla
 * senkronize tutar ve sitede olmayanı siler. Bu kayıtlar sitede
 * birebir metin olarak yer almadığından o mekanizmaya hiç girmemeli -
 * aksi halde ilk taramada "sitede yok" diye silinirlerdi.
 *
 * Satış temsilcisi ya da müşteri "RAID 5 nedir", "Tier III ne demek",
 * "SYN flood nasıl çalışır" gibi kavramsal bir soru sorduğunda model
 * kaçamak yanıt vermesin ya da uydurmasın diye eklenmiştir. Fiyat ya da
 * şirketin spesifik olarak neyi sunduğuna dair sorularda hâlâ fiyat
 * motorunu ve şirkete özgü ilk-kurulum/site kayıtlarını esas alır.
 *
 *     php artisan db:seed --class=Database\\Seeders\\GenelTeknikBilgiSeeder
 *
 * YENİDEN ÇALIŞTIRILABİLİR: aynı başlıklı kayıt varsa metni güncellenir
 * (değişmediyse dokunulmaz). Her çalıştırmada baştan eklemek, bir
 * düzeltmeyi yaymak için yeniden çalıştırıldığında bütün kayıtların bir
 * kopyasını daha üretirdi.
 */
class GenelTeknikBilgiSeeder extends Seeder
{
    private const KAYNAK = 'egitim-genel';

    public function run(KnowledgeBaseService $knowledgeBase): void
    {
        $mevcut = BilgiKaydi::query()->where('kaynak', self::KAYNAK)->get()->keyBy('baslik');

        foreach ($this->notes() as $note) {
            $kayit = $mevcut->get($note['title']);

            if ($kayit !== null) {
                if ($kayit->metin !== $note['text'] || $kayit->kategori !== $note['category']) {
                    $knowledgeBase->updateEntry($kayit->id, $note['text'], $note['title'], $note['category']);
                }

                continue;
            }

            $knowledgeBase->addEntry(
                text: $note['text'],
                title: $note['title'],
                category: $note['category'],
                source: self::KAYNAK,
            );
        }
    }

    protected function notes(): array
    {
        return [
            // ---- Sunucu Donanımı ----
            [
                'title' => 'RAID Seviyeleri',
                'category' => 'Sunucu Donanımı',
                'text' => 'RAID (Redundant Array of Independent Disks), birden fazla diski birleştirerek performans ve/veya '
                    .'veri güvenliği sağlayan bir yapılandırmadır. RAID 0: veri diskler arasında şeritler hâlinde '
                    .'dağıtılır (striping); tam kapasite ve en yüksek hız elde edilir ama yedeklilik yoktur - bir disk '
                    .'arızalanırsa tüm veri kaybolur. '
                    .'RAID 1: iki disk birbirinin aynısını tutar (mirroring); kapasite yarıya düşer ama bir disk arızalansa '
                    .'da veri korunur. RAID 5: en az 3 diskte veri + parity (eşlik biti) dağıtılır; tek disk arızasına '
                    .'dayanıklıdır, bir diskin kapasitesi eşliğe gider: kullanılabilir alan (n-1)/n (örn. 4 x 2 TB '
                    .'RAID 5 = 6 TB). RAID 6, RAID 5\'in aynısıdır ama çift parity ile aynı anda iki disk arızasına '
                    .'dayanır (en az 4 disk, kullanılabilir alan (n-2)/n). RAID 10 (1+0): disklerin önce ayna (mirror) çiftleri '
                    .'oluşturulup sonra bu çiftlerin striping ile birleştirilmesidir; hem yüksek performans hem yedeklilik '
                    .'sunar ama kapasitenin yarısı kullanılır - veritabanı gibi yoğun yazma yapan iş yükleri için tercih '
                    .'edilir. RAID kendisi bir yedekleme (backup) yöntemi DEĞİLDİR; sunucu/kabin tamamen kaybolursa (yangın, '
                    .'hırsızlık, çoklu disk arızası) veriyi korumaz, ayrı bir yedekleme çözümü gerekir.',
            ],
            [
                'title' => 'HDD, SSD ve NVMe Farkları',
                'category' => 'Sunucu Donanımı',
                'text' => 'HDD (mekanik disk), dönen manyetik plakalar ve hareketli bir kafa ile veriye erişir; kapasite '
                    .'başına maliyeti düşüktür ama rastgele erişim (IOPS) performansı sınırlıdır ve mekanik olduğu için '
                    .'arıza riski SSD\'ye göre daha yüksektir. SSD (Solid State Drive), hareketli parça içermeyen flash '
                    .'bellek kullanır; HDD\'ye göre çok daha yüksek IOPS, düşük gecikme (latency) ve daha az elektrik '
                    .'tüketimi sağlar. Bağlantı arayüzüne göre SATA SSD (klasik disk arayüzünü kullanır, hız sınırı '
                    .'~550-600 MB/s civarındadır) ve NVMe SSD (doğrudan PCIe hattına bağlanır, çok daha yüksek bant '
                    .'genişliği ve düşük gecikme sunar, birkaç GB/s\'e ulaşabilir) olarak ikiye ayrılır. Veritabanı, '
                    .'e-ticaret veya yoğun I/O gerektiren uygulamalarda SSD/NVMe tercih edilir; arşiv, yedekleme veya büyük '
                    .'hacimli/az erişilen veri için HDD hâlâ maliyet avantajı sunar. Kurumsal (enterprise) diskler, '
                    .'tüketici disklerine göre daha yüksek dayanıklılık (TBW/DWPD) ve MTBF (arızalar arası ortalama süre) '
                    .'değerleriyle üretilir; sunucularda bu yüzden enterprise sınıf disk tercih edilmesi önerilir.',
            ],
            [
                'title' => 'ECC RAM Nedir',
                'category' => 'Sunucu Donanımı',
                'text' => 'ECC (Error-Correcting Code) RAM, bellekte oluşabilecek tek bitlik hataları (kozmik ışınlar, '
                    .'elektriksel gürültü gibi nedenlerle) otomatik olarak tespit edip düzeltebilen bir bellek türüdür. '
                    .'Standart (ECC olmayan) bellekte fark edilmeyen bir bit hatası veride sessiz bozulmaya (silent data '
                    .'corruption) ya da beklenmedik çökmelere yol açabilir; sunucu sınıfı donanımlarda 7/24 çalışma '
                    .'beklendiği için ECC bellek standarttır. ECC belleğin normal bellekten farkı, ekstra bir bellek '
                    .'çipiyle hata denetim biti taşımasıdır; bu nedenle anakart ve işlemcinin ECC desteklemesi gerekir - '
                    .'her anakart/CPU kombinasyonu ECC ile çalışmaz.',
            ],
            [
                'title' => 'IP-KVM (Uzaktan Erişim Kartı)',
                'category' => 'Sunucu Donanımı',
                'text' => 'IP-KVM (KVM over IP), bir sunucunun klavye, video (ekran) ve fare (mouse) çıkışına internet '
                    .'üzerinden uzaktan erişim sağlayan donanım/yazılım çözümüdür. İşletim sistemi hiç açılmasa, ağ kartı '
                    .'çalışmasa ya da disk arızası nedeniyle boot ekranına takılsa bile BIOS/UEFI seviyesinde ekranı '
                    .'görüp müdahale etmeyi sağlar - SSH veya RDP\'nin çalışmadığı durumlarda tek erişim yoludur. Sanal '
                    .'medya (virtual media/ISO mount) özelliğiyle uzaktaki bir ISO dosyası sunucuya sanal CD/USB olarak '
                    .'takılıp işletim sistemi kurulumu uzaktan yapılabilir. Sunucu üreticilerine göre farklı adlarla '
                    .'anılır: HP\'de iLO, Dell\'de iDRAC, Supermicro\'da IPMI/BMC gibi.',
            ],

            // ---- IP / Network ----
            [
                'title' => 'CIDR ve Subnet Mantığı (Genel)',
                'category' => 'IP / Network',
                'text' => 'CIDR (Classless Inter-Domain Routing) gösterimi, bir IP bloğunun büyüklüğünü "/" işaretinden '
                    .'sonraki prefix uzunluğuyla ifade eder (örn. /24, /29). Prefix küçüldükçe (sayı azaldıkça) bloktaki '
                    .'IP sayısı artar. Yaygın bloklar ve toplam adres sayıları: /32 = 1 IP, /30 = 4 IP, /29 = 8 IP, '
                    .'/28 = 16 IP, /27 = 32 IP, /26 = 64 IP, /25 = 128 IP, /24 = 256 IP. Her blokta bir ağ adresi (network '
                    .'address, bloğun ilk adresi) ve bir yayın adresi (broadcast address, bloğun son adresi) cihazlara '
                    .'atanamaz; IPv4\'te genelde bir de ağ geçidi (gateway) adresi ayrılır - bu yüzden "kullanılabilir" IP '
                    .'sayısı toplam bloktan 2-3 eksik çıkar (örn. /29\'da 8 adresten 5\'i kullanılabilir). Subnet mask '
                    .'gösterimi (255.255.255.0 gibi) CIDR ile aynı bilgiyi ondalık formatta ifade eder; /24 = '
                    .'255.255.255.0, /29 = 255.255.255.248.',
            ],
            [
                'title' => 'IPv4 ve IPv6 Farkı',
                'category' => 'IP / Network',
                'text' => 'IPv4, 32 bit uzunluğunda adresler kullanır (örn. 192.168.1.1) ve toplam ~4,3 milyar benzersiz '
                    .'adres sunar; bölgesel kayıt otoritelerinin (Türkiye\'nin bağlı olduğu RIPE dahil) serbest IPv4 '
                    .'havuzu tükendiği için IPv4 kıtlığı yaşanmaktadır ve bu da IPv4 bloklarının maliyetini artırmaktadır. IPv6, 128 bit uzunluğunda '
                    .'adresler kullanır (örn. 2001:db8::1) ve pratikte tükenmeyecek kadar büyük bir adres alanı sunar; '
                    .'ayrıca NAT ihtiyacını azaltır ve bazı senaryolarda yönlendirme verimliliğini artırır. Sunucu/hosting '
                    .'hizmetlerinde günümüzde de facto standart IPv4 kullanımı sürerken, IPv6 desteği ek/tamamlayıcı '
                    .'olarak sunulur; bir sistemin "dual-stack" olması hem IPv4 hem IPv6 üzerinden erişilebilir olduğu '
                    .'anlamına gelir.',
            ],
            [
                'title' => 'DNS Kayıt Türleri',
                'category' => 'IP / Network',
                'text' => 'DNS (Domain Name System), alan adlarını IP adreslerine çevirir. Yaygın kayıt türleri: A kaydı '
                    .'(alan adını bir IPv4 adresine bağlar), AAAA kaydı (bir IPv6 adresine bağlar), CNAME (bir alan adını '
                    .'başka bir alan adına yönlendirir - takma ad gibi çalışır), MX (o alan adına gelen e-postaların hangi '
                    .'sunucuya gideceğini belirtir, öncelik değeriyle birlikte), TXT (serbest metin; SPF/DKIM/DMARC gibi '
                    .'e-posta doğrulama kayıtları ve alan adı sahiplik doğrulamaları burada tutulur), NS (bir alan adının '
                    .'hangi isim sunucuları tarafından yönetildiğini belirtir), PTR (ters DNS/reverse DNS - bir IP '
                    .'adresini bir alan adına bağlar; e-posta sunucularının spam filtrelerinden geçmesi için PTR kaydının '
                    .'doğru ve tutarlı olması genellikle beklenir). DNS değişikliklerinin dünya genelinde yayılması '
                    .'(propagation) kaydın TTL (time to live) değerine bağlı olarak dakikalar ile 48 saat arasında '
                    .'sürebilir.',
            ],
            [
                'title' => 'Whois ve RDNS Kavramı (Genel)',
                'category' => 'IP / Network',
                'text' => 'Whois, bir IP bloğunun veya alan adının kime tahsisli olduğunu sorgulamaya yarayan genel '
                    .'erişimli bir veritabanı sorgu protokolüdür; IP blokları için RIPE (Avrupa/Türkiye dahil), ARIN '
                    .'(Kuzey Amerika), APNIC (Asya-Pasifik) gibi bölgesel internet kayıt otoriteleri (RIR) tarafından '
                    .'işletilir. RDNS (Reverse DNS), bir IP adresinden alan adına doğru çalışan DNS çözümlemesidir '
                    .'(normal DNS\'in tersi yönü) ve PTR kaydıyla yapılandırılır. Kurumsal e-posta gönderiminde RDNS '
                    .'kaydının, gönderen sunucunun HELO/EHLO adıyla tutarlı olması itibar (reputation) açısından önemlidir '
                    .'- tutarsız ya da eksik RDNS, e-postaların alıcı tarafta spam olarak işaretlenme ihtimalini artırır.',
            ],
            [
                'title' => 'BGP ve Peering Nedir',
                'category' => 'IP / Network',
                'text' => 'BGP (Border Gateway Protocol), internetin omurgasını oluşturan otonom sistemler (AS - Autonomous '
                    .'System, her biri bir ASN ile tanımlanır) arasında hangi IP bloklarına hangi yoldan ulaşılacağı '
                    .'bilgisini paylaşan yönlendirme protokolüdür. Peering, iki ağın (örn. bir veri merkezi ile bir '
                    .'internet servis sağlayıcısı) trafiği doğrudan birbirine vermesidir; bu sayede trafik üçüncü bir '
                    .'sağlayıcı (transit) üzerinden dolaşmadan daha kısa yoldan, daha düşük gecikmeyle ve genelde daha '
                    .'düşük maliyetle taşınır. Bir veri merkezinin/hosting sağlayıcısının birden fazla büyük operatörle '
                    .'doğrudan peering anlaşması olması, o bölgedeki son kullanıcılara daha hızlı ve kesintiye karşı daha '
                    .'dirençli erişim sunması anlamına gelir (tek bir sağlayıcıya bağımlılık azalır).',
            ],
            [
                'title' => 'Bant Genişliği Terimleri',
                'category' => 'IP / Network',
                'text' => 'Mbps (megabit/saniye) ile MB/s (megabyte/saniye) sıkça karıştırılır: 1 byte = 8 bit olduğundan '
                    .'100 Mbps\'lik bir hat teorik olarak ~12,5 MB/s indirme hızına karşılık gelir. Simetrik hat, upload '
                    .'(yükleme) ve download (indirme) hızının eşit olduğu; asimetrik hat ise bu ikisinin farklı olduğu '
                    .'(çoğu ev interneti gibi, download\'ın upload\'tan hızlı olduğu) bağlantı türüdür - sunucu/veri '
                    .'merkezi hizmetlerinde simetrik hat tercih edilir çünkü sunucular veri servis ederken (upload) yoğun '
                    .'çalışır. "Unmetered" (trafik ölçülmeyen) port, port hızı sınırlı ama toplam veri hacmi '
                    .'sınırlanmayan bağlantı anlamına gelir; "trafik limitli" pakette ise belirli bir port hızı üzerinden '
                    .'aylık toplam veri transferi (upload+download toplamı, GB/TB cinsinden) sınırlıdır ve aşımda ek ücret '
                    .'ya da hız kısıtlaması uygulanabilir. Bazı sağlayıcılar "95. persentil" (95th percentile) faturalama '
                    .'kullanır: ayın her 5 dakikalık diliminde ölçülen trafiğin en yüksek %5\'i göz ardı edilip kalan en '
                    .'yüksek değer faturalandırılır - kısa süreli trafik patlamalarının (burst) faturayı doğrudan '
                    .'etkilememesini sağlar.',
            ],

            // ---- Firewall / Güvenlik ----
            [
                'title' => 'DDoS Saldırı Türleri',
                'category' => 'Firewall',
                'text' => 'DDoS (Distributed Denial of Service), çok sayıda kaynaktan gelen trafikle bir hedefi hizmet '
                    .'veremez hale getirmeyi amaçlayan saldırı türüdür. Başlıca kategoriler: (1) Hacimsel saldırılar - '
                    .'UDP flood, ICMP flood gibi, amaç bant genişliğini doldurmaktır; DNS/NTP/memcached amplification '
                    .'(yükseltme) saldırılarında, saldırgan sahte kaynak IP\'siyle (spoofing) küçük bir istek gönderip '
                    .'açık sunuculardan hedefe çok daha büyük yanıtlar döndürülmesini sağlar. (2) Protokol saldırıları - '
                    .'SYN flood (TCP el sıkışmasının yarıda bırakılıp sunucu kaynaklarının (bağlantı tablosu) tüketilmesi), '
                    .'ping of death, teardrop (parçalanmış/bozuk paketlerle hedef sistemi çökertmeye çalışma) gibi, amaç '
                    .'sunucu/ağ cihazı kaynaklarını (CPU, bağlantı tablosu) tüketmektir. (3) Uygulama katmanı (Layer 7) '
                    .'saldırıları - HTTP flood gibi, normal kullanıcı isteklerine benzeyen ama çok yüksek hacimde istekle '
                    .'web sunucusunu/uygulamayı yorar; hacim olarak küçük görünse de tespiti daha zordur çünkü meşru '
                    .'trafiğe benzer. IP spoofing (kaynak IP\'yi sahtelemek), port scan (açık portları taramak) ve land '
                    .'attack (kaynak ve hedef adresi aynı olan paket göndermek) de yaygın saldırı/keşif teknikleridir.',
            ],
            [
                'title' => 'Firewall ve WAF Farkı',
                'category' => 'Firewall',
                'text' => 'Geleneksel firewall (güvenlik duvarı), IP adresi, port ve protokol gibi ağ/taşıma katmanı '
                    .'(Layer 3/4) bilgilerine bakarak trafiği izin verir ya da engeller; DDoS koruma sistemleri de '
                    .'genelde bu katmanda hacim/desen bazlı filtreleme yapar. WAF (Web Application Firewall), bunun bir '
                    .'katman üstünde, HTTP isteklerinin İÇERİĞİNE bakan bir korumadır; SQL injection, XSS (cross-site '
                    .'scripting), zararlı dosya yükleme gibi uygulama seviyesindeki saldırıları (OWASP Top 10 kategorisi) '
                    .'hedefler - IP/port doğru ve izinli olsa bile isteğin içeriği zararlıysa WAF onu durdurabilir. İkisi '
                    .'birbirinin yerine geçmez: bir web uygulamasının hem ağ seviyesinde (DDoS/firewall) hem uygulama '
                    .'seviyesinde (WAF) korunması gerekir.',
            ],
            [
                'title' => 'Scrubbing Center (Trafik Temizleme Merkezi) Kavramı',
                'category' => 'Firewall',
                'text' => 'Scrubbing center, hedefe giden tüm trafiğin önce yönlendirildiği, zararlı/saldırı trafiğinin '
                    .'ayıklanıp yalnızca temiz trafiğin hedefe iletildiği yüksek kapasiteli bir filtreleme noktasıdır. '
                    .'Özellikle yurtdışı kaynaklı büyük hacimli saldırılarda, saldırıyı hedefe yakın yerde değil, '
                    .'kaynağa yakın ya da ağın giriş noktasında (mümkün olduğunca erken) durdurmak amaçlanır; bu sayede '
                    .'zararlı trafik hedef ağın kendi bağlantı kapasitesini hiç doldurmadan temizlenmiş olur. Anycast '
                    .'yönlendirme, aynı IP adresinin birden fazla coğrafi noktadan yayınlanmasını sağlayarak gelen '
                    .'trafiğin (saldırı dahil) en yakın/en uygun scrubbing noktasına otomatik dağılmasına yardımcı olan '
                    .'yaygın bir tekniktir.',
            ],

            // ---- Veri Merkezi Standartları ----
            [
                'title' => 'Veri Merkezi Tier Sınıflandırması (Uptime Institute)',
                'category' => 'Veri Merkezi Standartları',
                'text' => 'Uptime Institute\'un Tier sınıflandırması, bir veri merkezinin altyapı yedekliliğini ve '
                    .'garanti edilen çalışma süresini dört seviyede tanımlar. Tier I: tek yol, yedeksiz altyapı (%99,671 '
                    .'yıllık çalışma süresi hedefi, yılda ~28,8 saate kadar planlı/plansız kesinti). Tier II: kısmi '
                    .'yedekli bileşenler eklenmiş tek yol (%99,741, ~22 saat/yıl). Tier III: "concurrently maintainable" '
                    .'- her bileşen için birden fazla dağıtım yolu vardır ve bakım/arıza sırasında hizmeti kesmeden '
                    .'müdahale edilebilir (%99,982, ~1,6 saat/yıl). Tier IV: tamamen yedekli ve hataya dayanıklı '
                    .'(fault-tolerant) altyapı; tek bir bileşen arızası hizmeti etkilemez (%99,995, ~26 dakika/yıl). '
                    .'Tier seviyesi yükseldikçe enerji, soğutma ve network gibi kritik sistemlerin kaç kopya/yol '
                    .'üzerinden sağlandığı (N, N+1, 2N gibi) artar.',
            ],
            [
                'title' => 'N+1 ve 2N Yedeklilik',
                'category' => 'Veri Merkezi Standartları',
                'text' => 'N, bir sistemin (örn. UPS, soğutma ünitesi, jeneratör) o anki yükü karşılamak için gereken '
                    .'minimum bileşen/kapasite sayısıdır. N+1 yedeklilik, ihtiyaç duyulandan bir fazla bileşen '
                    .'bulundurmak anlamına gelir; bu sayede bir bileşen arızalansa veya bakıma alınsa bile kalan '
                    .'bileşenler yükü karşılamaya devam eder. 2N yedeklilik ise sistemin tamamen ikilenmesidir - iki '
                    .'bağımsız ve birbirinden habersiz çalışabilen tam kapasiteli sistem anlamına gelir, en yüksek '
                    .'yedeklilik seviyelerinden biridir (Tier IV veri merkezlerinde tipik olarak beklenir). 2N+1, iki bağımsız '
                    .'sistemin her birinin de kendi içinde N+1 yedekli olması demektir.',
            ],
            [
                'title' => 'PUE (Power Usage Effectiveness) Nedir',
                'category' => 'Veri Merkezi Standartları',
                'text' => 'PUE (Power Usage Effectiveness), bir veri merkezinin enerji verimliliğini ölçen yaygın bir '
                    .'metriktir: Toplam Tesis Enerjisi / BT Ekipmanı Enerjisi formülüyle hesaplanır. 1,0 değeri, '
                    .'tüketilen tüm enerjinin doğrudan sunuculara/BT ekipmanına gittiği, soğutma/aydınlatma gibi ek '
                    .'yüklerin (overhead) hiç olmadığı teorik ideal durumu ifade eder - pratikte ulaşılamaz. Sektör '
                    .'ortalaması genelde 1,5-1,6 civarındadır; modern, verimli tasarlanmış veri merkezleri 1,2-1,4 '
                    .'aralığına inebilir. Düşük PUE, soğutma ve güç dağıtımında daha az enerji israfı, dolayısıyla '
                    .'işletme maliyetlerinin bir kısmının daha verimli yönetildiği anlamına gelir.',
            ],
            [
                'title' => 'ISO 27001 ve ISO 9001 Sertifikaları (Genel)',
                'category' => 'Veri Merkezi Standartları',
                'text' => 'ISO 27001, bir kurumun bilgi güvenliği yönetim sistemi (BGYS) kurduğunu ve sürekli '
                    .'iyileştirdiğini gösteren uluslararası bir sertifikadır; erişim kontrolü, risk yönetimi, olay '
                    .'müdahalesi, fiziksel güvenlik gibi alanlarda belirli süreçlerin tanımlı ve denetlenebilir olmasını '
                    .'şart koşar - veri merkezi/hosting sağlayıcıları için müşterilere verilerinin süreçli bir güvenlik '
                    .'disipliniyle yönetildiğinin bağımsız bir kanıtıdır. ISO 9001 ise kalite yönetim sistemi standardıdır; '
                    .'süreçlerin tutarlı, ölçülebilir ve sürekli iyileştirilebilir şekilde yürütüldüğünü gösterir. Her '
                    .'ikisi de bağımsız denetim kuruluşları tarafından düzenli aralıklarla (genelde yıllık) yeniden '
                    .'denetlenip sertifikanın geçerliliği teyit edilir.',
            ],

            // ---- Enerji ve Soğutma ----
            [
                'title' => 'Güç Birimleri: Amper, Volt, Watt, kVA',
                'category' => 'Enerji',
                'text' => 'Watt (W), gerçek güç tüketimini; Volt (V), elektriksel gerilimi; Amper (A), akım şiddetini '
                    .'ifade eder. Tek fazlı bir sistemde temel ilişki Watt = Volt × Amper × Güç Faktörü şeklindedir '
                    .'(Türkiye şebekesinde tipik gerilim ~220-230V\'tur). kVA (kilovolt-amper), görünür gücü ifade eder '
                    .'ve güç faktörü 1\'e yakın olmadığı sürece kW\'tan (gerçek güç) biraz büyük çıkar; veri merkezi/UPS '
                    .'kapasiteleri genelde kVA cinsinden belirtilir. Üç fazlı sistemlerde (büyük kabin/veri merkezi '
                    .'beslemelerinde yaygındır) yük üç ayrı faza dengeli dağıtılarak daha yüksek toplam güç, daha ince '
                    .'kablolarla ve daha düşük kayıpla taşınabilir. Bir sunucu/kabinin güç tüketimi genelde amper '
                    .'cinsinden ölçülür/faturalandırılır çünkü PDU (Power Distribution Unit - güç dağıtım ünitesi) ve '
                    .'devre kesicilerin kapasite sınırı amper bazlıdır.',
            ],
            [
                'title' => 'UPS, Jeneratör ve ATS',
                'category' => 'Enerji',
                'text' => 'UPS (Uninterruptible Power Supply - kesintisiz güç kaynağı), şebeke elektriği kesildiği anda '
                    .'bataryadan anlık olarak devreye girip ekipmanın kapanmadan çalışmaya devam etmesini sağlayan '
                    .'sistemdir; UPS bataryaları genelde dakikalar mertebesinde bir köprü süresi sunar - amaç uzun süre '
                    .'beslemek değil, jeneratör devreye girene kadar kesintisiz geçiş sağlamaktır. Jeneratör (genelde '
                    .'dizel), şebeke kesildiğinde saniyeler/dakikalar içinde devreye girip uzun süreli (saatler/günler) '
                    .'enerji ihtiyacını karşılar; yakıt deposu kapasitesine göre çalışma süresi belirlenir. ATS '
                    .'(Automatic Transfer Switch - otomatik transfer şalteri), şebeke ile jeneratör arasında geçişi '
                    .'otomatik olarak (insan müdahalesi olmadan) yapan anahtarlama sistemidir. Birlikte çalışma sırası '
                    .'genelde şöyledir: şebeke kesilir → UPS bataryası anlık besler → ATS jeneratörü devreye alır → '
                    .'jeneratör kararlı hale gelince yük jeneratöre aktarılır → UPS bataryaları jeneratör beslemesiyle '
                    .'tekrar şarj olur.',
            ],
            [
                'title' => 'Veri Merkezi Soğutma Yöntemleri',
                'category' => 'Enerji',
                'text' => 'Sunucular çalışırken elektrik enerjisinin büyük bölümünü ısıya çevirir; bu ısının '
                    .'uzaklaştırılmaması durumunda ekipman aşırı ısınıp performans düşürür (thermal throttling) ya da '
                    .'arızalanır. CRAC/CRAH üniteleri (Computer Room Air Conditioning/Handler), veri merkezi salonuna '
                    .'özel, hassas sıcaklık/nem kontrolü yapan iklimlendirme sistemleridir. "Hot aisle / cold aisle" '
                    .'(sıcak koridor / soğuk koridor) düzeni, kabinlerin ön yüzlerinin (soğuk hava emişi) birbirine, arka '
                    .'yüzlerinin (sıcak hava atışı) birbirine bakacak şekilde dizilmesidir; bu sayede soğuk ve sıcak hava '
                    .'karışmadan verimli bir döngü kurulur. "Free cooling" (serbest soğutma), dış hava sıcaklığının '
                    .'yeterince düşük olduğu dönemlerde mekanik soğutma (kompresör) yerine doğrudan veya dolaylı dış '
                    .'havayı kullanarak enerji tasarrufu sağlayan yöntemdir. Raised floor (yükseltilmiş döşeme), soğuk '
                    .'havanın kabinlerin altından, kablo tesisatının ise ayrı bir kanaldan geçmesini sağlayarak hem '
                    .'soğutma verimini artırır hem kablo yönetimini kolaylaştırır.',
            ],
            [
                'title' => 'Veri Merkezi Yangın Söndürme Sistemleri',
                'category' => 'Enerji',
                'text' => 'Veri merkezlerinde sulu (sprinkler) söndürme sistemleri tercih edilmez çünkü su, elektronik '
                    .'ekipmana ciddi ve kalıcı hasar verir. Bunun yerine gazlı söndürme sistemleri kullanılır: FM200 '
                    .'(HFC-227ea) gibi temiz ajanlar (clean agent), yanma reaksiyonunu kimyasal olarak keserek yangını '
                    .'söndürür, elektronik ekipmana zarar vermez, iletken değildir ve söndürme sonrası kalıntı '
                    .'bırakmaz - insanların bulunduğu ortamlarda da (belirli konsantrasyon sınırları içinde) güvenli '
                    .'kabul edilir. Erken uyarı için VESDA (Very Early Smoke Detection Apparatus) gibi hassas duman '
                    .'algılama sistemleri, ortamdaki havayı sürekli örnekleyerek klasik duman dedektörlerinden çok daha '
                    .'erken aşamada (yangın alevlenmeden önce) uyarı verebilir.',
            ],

            // ---- Hizmet Karşılaştırma ----
            [
                'title' => 'Co-location, Dedicated Sunucu, Bulut Sunucu ve VPS Farkları',
                'category' => 'Hizmet Karşılaştırma',
                'text' => 'Co-location (sunucu barındırma), müşterinin KENDİ SATIN ALDIĞI fiziksel sunucu donanımını bir '
                    .'veri merkezindeki kabin/alanda barındırmasıdır; donanım müşteriye ait olduğundan donanım seçimi '
                    .'tamamen serbesttir, veri merkezi yalnızca alan, enerji, soğutma ve network sağlar. Dedicated '
                    .'sunucu (kiralık sunucu), sağlayıcıya ait fiziksel bir sunucunun tamamının kiralanmasıdır; müşteri '
                    .'donanımı satın almaz, aylık kira öder ve sunucu paylaşımsızdır (tüm CPU/RAM/disk tek müşteriye '
                    .'aittir). Bulut sunucu (VPS/cloud), fiziksel bir sunucunun sanallaştırma yazılımıyla (hypervisor) '
                    .'birden fazla izole sanal sunucuya bölünmesiyle oluşur; her sanal sunucu kendi işletim sistemine ve '
                    .'ayrılmış kaynağına (CPU/RAM/disk) sahiptir ama fiziksel donanım birden fazla müşteri arasında '
                    .'paylaşılır - bu sayede kaynaklar ihtiyaca göre esnek şekilde büyütülüp küçültülebilir ve genelde '
                    .'dakikalar içinde teslim edilir (fiziksel sunucu tedarik süresi gerektirmez). VPS ve "bulut sunucu" '
                    .'terimleri çoğu zaman birbirinin yerine kullanılır; bulut sunucu genelde ek olarak yedekli/cluster '
                    .'altyapı (storage seviyesinde donanım arızasına karşı otomatik toparlanma) vurgusu taşır.',
            ],
            [
                'title' => 'Yönetilen (Managed) ve Yönetilmeyen (Unmanaged) Hizmet Farkı',
                'category' => 'Hizmet Karşılaştırma',
                'text' => 'Unmanaged (yönetilmeyen) hizmette sağlayıcı yalnızca donanımın/altyapının çalışır durumda '
                    .'olmasından (fiziksel arıza, network, enerji) sorumludur; işletim sistemi kurulumu, güncellemeleri, '
                    .'güvenlik yamaları, uygulama/panel yönetimi gibi yazılımsal her şey müşterinin sorumluluğundadır. '
                    .'Managed (yönetilen) hizmette sağlayıcı bunlara ek olarak yazılım seviyesinde de destek verir - '
                    .'işletim sistemi kurulumu, güncellemeler, temel sorun giderme, bazen 7/24 izleme (monitoring) dahil '
                    .'olabilir; kapsam sağlayıcıdan sağlayıcıya değişir ve genelde ek ücretlidir. Co-location hizmetinde '
                    .'tanım gereği donanım müşteriye ait olduğundan yazılımsal destek tipik olarak kapsam dışıdır (yalnızca '
                    .'fiziksel müdahale sağlanır); dedicated ve bulut sunucularda ise yönetilen/yönetilmeyen seçeneği '
                    .'sağlayıcının sunduğu pakete göre değişir.',
            ],

            // ---- Yedekleme ----
            [
                'title' => '3-2-1 Yedekleme Kuralı',
                'category' => 'Yedekleme',
                'text' => '3-2-1 kuralı, veri kaybı riskini azaltmak için yaygın kabul gören bir yedekleme stratejisidir: '
                    .'verinin en az 3 kopyası bulunmalı (1 asıl + 2 yedek), bu kopyalar en az 2 farklı ortam/medya '
                    .'türünde tutulmalı (örn. yerel disk + bulut, ya da farklı RAID/storage sistemleri - aynı diskin iki '
                    .'klasörü "2 kopya" sayılmaz) ve bunlardan en az 1 kopya farklı bir fiziksel lokasyonda (off-site) '
                    .'saklanmalıdır - böylece yangın, hırsızlık ya da o veri merkezini etkileyen bir felaket durumunda '
                    .'bile en az bir yedek erişilebilir kalır. RAID, disk arızasına karşı süreklilik sağlar ama yedekleme '
                    .'yerine geçmez; yanlışlıkla silinen, fidye yazılımıyla (ransomware) şifrelenen ya da bozulan bir veri '
                    .'RAID ile kurtarılamaz çünkü RAID değişikliği anında tüm disklere yansıtır.',
            ],
            [
                'title' => 'Yedekleme Türleri: Full, Incremental, Differential',
                'category' => 'Yedekleme',
                'text' => 'Full (tam) yedekleme, her seferinde verinin tamamını kopyalar; en fazla depolama alanı ve '
                    .'süreyi gerektirir ama geri yükleme (restore) en hızlı ve basit olanıdır (tek bir yedek seti '
                    .'yeterlidir). Incremental (artımlı) yedekleme, yalnızca bir önceki yedeklemeden (ister full ister '
                    .'incremental olsun) BU YANA değişen verileri kopyalar; en az yer/süre gerektirir ama geri yüklemek '
                    .'için son full yedek + o zamandan beri alınan TÜM incremental yedeklerin sırayla uygulanması gerekir '
                    .'- bir halka eksik/bozuksa zincir kırılır. Differential (fark) yedekleme, son FULL yedekten bu yana '
                    .'değişen her şeyi kopyalar (bir önceki differential\'a değil); incremental\'a göre daha fazla yer '
                    .'kaplar ama geri yükleme daha basittir (yalnızca son full + son differential gerekir). Pratikte çoğu '
                    .'sistem bunların bir kombinasyonunu kullanır (örn. haftalık full + günlük incremental).',
            ],
            [
                'title' => 'RPO ve RTO Kavramları',
                'category' => 'Yedekleme',
                'text' => 'RPO (Recovery Point Objective - kurtarma noktası hedefi), bir felaket anında ne kadar '
                    .'geriye kadar veri kaybını kabul edilebilir saymak istediğinizi ifade eder; örneğin RPO = 24 saat '
                    .'ise günlük yedekleme yeterlidir, ama RPO = 15 dakika isteniyorsa çok daha sık (neredeyse sürekli) '
                    .'yedekleme veya gerçek zamanlı replikasyon gerekir. RTO (Recovery Time Objective - kurtarma süresi '
                    .'hedefi), bir kesintiden sonra sistemin yeniden tam olarak çalışır hale gelmesi için kabul edilebilir '
                    .'azami süredir; örneğin RTO = 4 saat ise felaket anından itibaren 4 saat içinde hizmetin ayağa '
                    .'kalkması hedeflenir. Bu iki hedef birlikte yedekleme sıklığını, yedeklerin nerede tutulacağını ve '
                    .'felaket kurtarma (disaster recovery) planının tasarımını belirler; daha düşük RPO/RTO hedefleri '
                    .'genelde daha yüksek maliyet gerektirir (daha sık yedekleme, yedek/standby altyapı, otomasyon).',
            ],

            // ---- Uptime / SLA ----
            [
                'title' => 'Uptime Yüzdeleri ve Yıllık Kesinti Süresi',
                'category' => 'Hizmet Karşılaştırma',
                'text' => 'SLA (Service Level Agreement - hizmet seviyesi anlaşması) metinlerinde sık görülen uptime '
                    .'yüzdeleri, izin verilen yıllık kesinti süresine karşılık gelir: %99 = yılda ~3 gün 15 saat, '
                    .'%99,9 ("üç dokuz") = yılda ~8 saat 45 dakika, %99,95 = yılda ~4 saat 22 dakika, %99,99 ("dört '
                    .'dokuz") = yılda ~52 dakika, %99,999 ("beş dokuz") = yılda ~5 dakika. Her ek "dokuz" (9), izin '
                    .'verilen kesinti süresini yaklaşık 10 kat azaltır ve bunu sağlamanın maliyeti/karmaşıklığı da '
                    .'orantısız şekilde artar (daha fazla yedeklilik, otomasyon, izleme gerektirir). SLA\'lerde genelde '
                    .'kesinti oranına göre kademeli kredi/tazminat maddeleri bulunur; "planlı bakım" pencereleri (önceden '
                    .'duyurulan kısa kesintiler) çoğu SLA\'de uptime hesabına dahil edilmez.',
            ],

            // ---- Lisans ve Sanallaştırma ----
            [
                'title' => 'Kontrol Panelleri: Plesk, DirectAdmin, cPanel',
                'category' => 'Lisans ve Yazılım',
                'text' => 'Kontrol panelleri, sunucu üzerinde web sitesi/domain/e-posta/veritabanı yönetimini komut '
                    .'satırı bilmeden, görsel bir arayüzden yapılabilir hale getiren lisanslı yazılımlardır. Plesk, '
                    .'hem Linux hem Windows sunucularda çalışabilir ve genelde çoklu domain/reseller (bayilik) '
                    .'senaryolarında tercih edilir; paket seviyeleri (Web Admin/Pro/Host Edition gibi) yönetilebilecek '
                    .'domain sayısı ve ek özelliklerle (WordPress Toolkit, hesap/abonelik yönetimi) farklılaşır. '
                    .'DirectAdmin, daha hafif kaynak tüketimi ve sadeliğiyle bilinir; lisans paketleri yönetilecek '
                    .'domain sayısına göre kademelidir (sınırsız paket de vardır). cPanel/WHM, özellikle Amerika merkezli hosting pazarında yaygın olan '
                    .'bir diğer Linux paneldir. Panel seçimi işletim sistemi uyumluluğuna (Plesk/DirectAdmin/cPanel '
                    .'çoğunlukla Linux; Windows sunucularda seçenek büyük ölçüde Plesk\'tir), yönetilecek domain '
                    .'sayısına ve ekibin aşinalığına göre yapılır.',
            ],
            [
                'title' => 'Sanallaştırma: Hypervisor ve Konteyner Farkı',
                'category' => 'Lisans ve Yazılım',
                'text' => 'Hypervisor, fiziksel bir sunucu üzerinde birden fazla bağımsız sanal makine (VM) '
                    .'çalıştırmayı sağlayan yazılım katmanıdır. Tip 1 (bare-metal) hypervisor\'lar (VMware ESXi, '
                    .'Microsoft Hyper-V, KVM, Xen) doğrudan donanım üzerinde çalışır ve sunucu/bulut ortamlarında '
                    .'performans avantajı nedeniyle tercih edilir; Tip 2 hypervisor\'lar (VirtualBox, VMware Workstation '
                    .'gibi) bir işletim sisteminin üzerinde uygulama olarak çalışır, genelde masaüstü/test '
                    .'senaryolarında kullanılır. Her VM kendi tam işletim sistemi çekirdeğini çalıştırır, bu da güçlü '
                    .'izolasyon sağlar ama ek kaynak (RAM/CPU) tüketir. Konteynerler (Docker, LXC gibi) ise işletim '
                    .'sistemi çekirdeğini ana makineyle paylaşır, yalnızca uygulama ve bağımlılıklarını izole eder; bu '
                    .'sayede VM\'lere göre çok daha hafif ve hızlı başlatılabilirdir ama izolasyon seviyesi VM kadar güçlü '
                    .'değildir (aynı çekirdeği paylaştıkları için). Pratikte ikisi genelde bir arada kullanılır: fiziksel '
                    .'sunucu hypervisor ile VM\'lere bölünür, VM\'lerin içinde de konteynerler çalıştırılır.',
            ],

            // ---- Domain ----
            [
                'title' => 'Domain Uzantı Türleri: gTLD ve ccTLD',
                'category' => 'Domain ve DNS',
                'text' => 'TLD (Top Level Domain), bir alan adının son eki/uzantısıdır. gTLD (generic TLD - jenerik '
                    .'uzantı), belirli bir ülkeye bağlı olmayan, dünya genelinde herkesin alabildiği uzantılardır '
                    .'(.com, .net, .org gibi); ICANN tarafından düzenlenir. ccTLD (country code TLD - ülke kodlu '
                    .'uzantı), ISO ülke koduna dayanan ve genelde o ülkenin yerel otoritesi tarafından yönetilen '
                    .'uzantılardır (.tr, .de, .uk gibi); bazı ccTLD\'ler ek kayıt koşulları isteyebilir (Türkiye\'de '
                    .'örn. .gov.tr, .edu.tr ve .av.tr belge ister). Türkiye\'de .tr uzantıları 2022\'den beri BTK\'nın '
                    .'TRABİS sistemi (nic.tr) üzerinden, yetkili kayıt kuruluşları aracılığıyla yönetilir; daha önce '
                    .'ODTÜ yönetiyordu. Domain kaydı belirli bir süre (genelde 1 yıl) için yapılır ve '
                    .'süresi dolmadan yenilenmelidir; süre dolduktan sonra genelde kısa bir "grace period" (ek süre) '
                    .'tanınır ama bu süre de geçerse domain serbest kalıp başkası tarafından alınabilir. Domain '
                    .'transferi (bir sağlayıcıdan başka bir sağlayıcıya taşıma), genelde domainin en az 60 gündür kayıtlı '
                    .'olmasını ve bir "transfer kilidi açma kodu" (auth/EPP code) almayı gerektirir.',
            ],
        ];
    }
}
