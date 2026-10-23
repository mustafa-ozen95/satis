<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Database\ConnectionFile;
use App\Services\Database\RemoteDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Veritabanı sekmesi: sına, sonra taşı.
 *
 * Asıl kurallar: sınanmamış bir hedefe taşınmaz, taşı düğmesi sınamaları
 * yeniden yapar, parola ne sayfada ne denetim kaydında görünür. Kopyanın
 * kendisi başlatıcıda yapılır; burada yalnızca isteğin doğru bırakıldığı
 * sınanır.
 */
class DatabaseScreenTest extends TestCase
{
    use RefreshDatabase;

    private const HEDEF = [
        'host' => 'db.ornek.local',
        'port' => 3306,
        'database' => 'satis_uzak',
        'username' => 'satis',
        'password' => 'Gizli-Parola-123',
    ];

    private string $dizin;

    private ConnectionFile $dosya;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dizin = sys_get_temp_dir().'/veritabani-'.bin2hex(random_bytes(4));
        $this->dosya = new ConnectionFile(app('encrypter'), $this->dizin);
        $this->instance(ConnectionFile::class, $this->dosya);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dizin.'/{,.}*', GLOB_BRACE) ?: [] as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        @rmdir($this->dizin);

        parent::tearDown();
    }

    /**
     * Ağa çıkmayan sahte: sınamaların sonucu sırayla verilir.
     *
     * @param  list<bool>  $sirayla  her sina() çağrısında hepsi geçiyor mu
     */
    private function sahte(array $sirayla = [true], string $kaynak = 'ortam'): RemoteDatabase
    {
        $sahte = new class($this->dosya, $sirayla, $kaynak) extends RemoteDatabase
        {
            public int $sinama = 0;

            /** @var list<string> */
            public array $islemler = [];

            public function __construct(ConnectionFile $dosya, private array $sirayla, private string $kaynak)
            {
                parent::__construct($dosya);
            }

            public function info(): array
            {
                return ['kaynak' => $this->kaynak, 'host' => 'db', 'port' => '3306', 'database' => 'satis',
                    'username' => 'satis', 'surum' => '10.11.14-MariaDB', 'boyut' => 2_500_000, 'tablo' => 48, 'ozel' => 0];
            }

            public function test(array $hedef, string $islem = 'tasi'): array
            {
                $this->islemler[] = $islem;
                $gecti = $this->sirayla[min($this->sinama++, count($this->sirayla) - 1)];

                return [
                    ['ad' => 'Bağlantı', 'tamam' => true, 'ayrinti' => $hedef['username'].'@'.$hedef['host']],
                    ['ad' => 'Hedef veritabanı boş', 'tamam' => $gecti, 'ayrinti' => $gecti ? 'Tablo yok.' : '3 tablo var; dolu bir veritabanının üzerine yazılmaz.'],
                ];
            }
        };

        $this->instance(RemoteDatabase::class, $sahte);

        return $sahte;
    }

    private function yonetici(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_ekran_yalnizca_yoneticide_acilir(): void
    {
        $this->sahte();

        $this->actingAs(User::factory()->rol('muhasebe')->create())
            ->get(route('admin.database.index'))
            ->assertRedirect(route('unauthorized'));

        $this->actingAs($this->yonetici())
            ->get(route('admin.database.index'))
            ->assertOk()
            ->assertSee('Şu anki veritabanı')
            ->assertSee('Kurulum ayarı')
            ->assertSee('10.11.14-MariaDB')
            ->assertSee('Bağlantıyı sına');
    }

    public function test_sinama_gecerse_tasi_dugmesi_gorunur_parola_gorunmez(): void
    {
        $this->sahte([true]);

        $this->actingAs($this->yonetici())
            ->from(route('admin.database.index'))
            ->followingRedirects()
            ->post(route('admin.database.test'), self::HEDEF)
            ->assertOk()
            ->assertSee('Sınamalar geçti')
            ->assertSee('satis@db.ornek.local:3306/satis_uzak hedefine taşı')
            ->assertDontSee('Gizli-Parola-123');
    }

    public function test_sinama_gecmezse_sebep_gorunur_tasi_dugmesi_gorunmez(): void
    {
        $this->sahte([false]);

        $this->actingAs($this->yonetici())
            ->from(route('admin.database.index'))
            ->followingRedirects()
            ->post(route('admin.database.test'), self::HEDEF)
            ->assertOk()
            ->assertSee('3 tablo var')
            ->assertSee('geçmedi')
            ->assertDontSee('hedefine taşı');
    }

    public function test_konteynerin_kendi_adresi_reddedilir(): void
    {
        $sahte = $this->sahte();

        $this->actingAs($this->yonetici())
            ->from(route('admin.database.index'))
            ->post(route('admin.database.test'), [...self::HEDEF, 'host' => 'localhost'])
            ->assertSessionHasErrors('host');

        $this->assertSame(0, $sahte->sinama, 'Sınama koşmamalıydı.');
    }

    public function test_sinanmadan_tasinmaz(): void
    {
        $this->sahte();

        $this->actingAs($this->yonetici())
            ->from(route('admin.database.index'))
            ->post(route('admin.database.migrate'))
            ->assertSessionHasErrors('database');

        $this->assertNull($this->dosya->status());
        $this->assertNull($this->dosya->connection());
    }

    public function test_tasi_istegi_birakir_ve_yeniden_baslatma_ister(): void
    {
        $this->sahte([true]);
        $yonetici = $this->yonetici();

        $this->actingAs($yonetici)
            ->from(route('admin.database.index'))
            ->post(route('admin.database.test'), self::HEDEF);

        $this->actingAs($yonetici)
            ->from(route('admin.database.index'))
            ->post(route('admin.database.migrate'), ['reason' => 'uzak sunucuya geçiş'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame(self::HEDEF, $this->dosya->connection());
        $this->assertSame('tasinacak', $this->dosya->stage());
        $this->assertSame('satis@db.ornek.local:3306/satis_uzak', $this->dosya->status()['hedef']);
        $this->assertSame($yonetici->name, $this->dosya->status()['isteyen']);
        $this->assertFileExists($this->dizin.'/restart');

        $kayit = ActivityLog::query()->where('tur', 'veritabani_tasi')->sole();
        $this->assertStringContainsString('satis@db.ornek.local:3306/satis_uzak', $kayit->aciklama);
        $this->assertStringContainsString('uzak sunucuya geçiş', $kayit->aciklama);
        $this->assertStringNotContainsString('Gizli-Parola-123', $kayit->aciklama);

        $this->actingAs($yonetici)
            ->get(route('admin.database.index'))
            ->assertSee('istendi, yeniden başlatma bekleniyor')
            ->assertDontSee('Bağlantıyı sına');
    }

    /** Sınama ile taşı arasında hedef dolmuş olabilir: taşı sınamaları baştan yapar. */
    public function test_tasi_sinamalari_yeniden_yapar(): void
    {
        $sahte = $this->sahte([true, false]);
        $yonetici = $this->yonetici();

        $this->actingAs($yonetici)->from(route('admin.database.index'))
            ->post(route('admin.database.test'), self::HEDEF);

        $this->actingAs($yonetici)->from(route('admin.database.index'))
            ->post(route('admin.database.migrate'))
            ->assertSessionHasErrors('database');

        $this->assertSame(2, $sahte->sinama);
        $this->assertNull($this->dosya->status());
        $this->assertNull($this->dosya->connection());
        $this->assertFileDoesNotExist($this->dizin.'/restart');
        $this->assertSame(0, ActivityLog::query()->where('tur', 'veritabani_tasi')->count());
    }

    public function test_tamamlanmis_tasimadan_sonra_form_yok_sonuc_gorunur(): void
    {
        $this->sahte(kaynak: 'panel');
        $this->dosya->writeStage('tasinacak', null, ['hedef' => 'satis@db.ornek.local:3306/satis_uzak']);
        $this->dosya->writeStage('deneniyor', '48 tablo, 1200 satır kopyalandı; iki tarafta sayılar aynı.');
        $this->dosya->writeStage('etkin');

        $this->actingAs($this->yonetici())
            ->get(route('admin.database.index'))
            ->assertOk()
            ->assertSee('Uzak (panel ayarı)')
            ->assertSee('tamamlandı')
            ->assertSee('48 tablo, 1200 satır kopyalandı')
            ->assertDontSee('Bağlantıyı sına');
    }

    public function test_basarisiz_tasimanin_sebebi_gorunur_ve_yeniden_denenebilir(): void
    {
        $this->sahte();
        $this->dosya->writeStage('tasinacak', null, ['hedef' => 'satis@db.ornek.local:3306/satis_uzak']);
        $this->dosya->writeStage('basarisiz', 'Uzak veritabanı boş değil (3 tablo); üzerine yazılmaz.');

        $this->actingAs($this->yonetici())
            ->get(route('admin.database.index'))
            ->assertOk()
            ->assertSee('yapılmadı')
            ->assertSee('Uzak veritabanı boş değil (3 tablo)')
            ->assertSee('Bağlantıyı sına');
    }

    public function test_sekme_ayarlar_seridinde_gorunur(): void
    {
        $this->sahte();

        $this->actingAs($this->yonetici())
            ->get(route('admin.certificate.index'))
            ->assertSee(route('admin.database.index'));
    }

    public function test_baglan_anahtar_onayi_ister(): void
    {
        $sahte = $this->sahte([true]);

        $this->actingAs($this->yonetici())
            ->from(route('admin.database.index'))
            ->post(route('admin.database.test'), [...self::HEDEF, 'islem' => 'baglan'])
            ->assertSessionHasErrors('anahtar_onay');

        $this->assertSame(0, $sahte->sinama, 'Onaysız sınama koşmamalıydı.');
    }

    /** Yeni kurulum eski kurulumun dolu veritabanına: kopya yok, aşama "baglanacak"; onay penceresi veritabanı adını ister. */
    public function test_baglan_istegi_birakir_kopyalamaz(): void
    {
        $sahte = $this->sahte([true]);
        $yonetici = $this->yonetici();
        $adres = self::HEDEF['username'].'@'.self::HEDEF['host'].':'.self::HEDEF['port'].'/'.self::HEDEF['database'];

        $this->actingAs($yonetici)
            ->from(route('admin.database.index'))
            ->followingRedirects()
            ->post(route('admin.database.test'), [...self::HEDEF, 'islem' => 'baglan', 'anahtar_onay' => '1'])
            ->assertOk()
            ->assertSee('Bağlanmak için aşağıdaki düğmeyi kullanın')
            ->assertSee($adres.' veritabanına bağlan')
            ->assertSee('data-confirm-title="Mevcut veritabanına bağlan"', false)
            ->assertSee('data-confirm-verify="'.self::HEDEF['database'].'"', false)
            ->assertDontSee('hedefine taşı')
            ->assertDontSee(self::HEDEF['password']);

        $this->actingAs($yonetici)
            ->from(route('admin.database.index'))
            ->post(route('admin.database.migrate'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $m): bool => str_contains($m, 'Bağlanma istendi'));

        $this->assertSame(['baglan', 'baglan'], $sahte->islemler, 'Bağlan düğmesi de sınamaları bağlan olarak yeniden yapmalı.');
        $this->assertSame(self::HEDEF, $this->dosya->connection());
        $this->assertSame('baglanacak', $this->dosya->stage());
        $this->assertSame('baglan', $this->dosya->status()['islem']);
        $this->assertFileExists($this->dizin.'/restart');
        $this->assertSame(0, ActivityLog::query()->where('tur', 'veritabani_tasi')->count());
        $kayit = ActivityLog::query()->where('tur', 'veritabani_baglan')->sole();
        $this->assertStringContainsString($adres, $kayit->aciklama);
        $this->assertStringNotContainsString(self::HEDEF['password'], $kayit->aciklama);

        $this->actingAs($yonetici)
            ->get(route('admin.database.index'))
            ->assertSee('Son bağlanma')
            ->assertSee('bağlanma istendi, yeniden başlatma bekleniyor')
            ->assertDontSee('Bağlantıyı sına');
    }

    /** Taşı da onay penceresiyle: veritabanı adı yazılmadan gönderilmez. */
    public function test_tasi_varsayilan_islem_ve_onay_penceresi(): void
    {
        $sahte = $this->sahte([true]);

        $this->actingAs($this->yonetici())
            ->from(route('admin.database.index'))
            ->followingRedirects()
            ->post(route('admin.database.test'), self::HEDEF)
            ->assertSee('data-confirm-title="Veritabanını taşı"', false)
            ->assertSee('data-confirm-verify="'.self::HEDEF['database'].'"', false);

        $this->assertSame(['tasi'], $sahte->islemler);
    }

    /** Bağlanılacak hedef bu panelin olmalı ve bu sürümden yeni olmamalı. */
    public function test_goc_karsilastirmasi(): void
    {
        $yerel = ['2026_01_01_a', '2026_01_02_b', '2026_01_03_c'];

        $this->assertSame(['ortak' => 3, 'bilinmeyen' => 0, 'bekleyen' => 0], RemoteDatabase::compareMigrations($yerel, $yerel));
        $this->assertSame(['ortak' => 2, 'bilinmeyen' => 0, 'bekleyen' => 1], RemoteDatabase::compareMigrations(['2026_01_01_a', '2026_01_02_b'], $yerel));
        $this->assertSame(['ortak' => 3, 'bilinmeyen' => 1, 'bekleyen' => 0], RemoteDatabase::compareMigrations([...$yerel, '2026_02_01_yeni'], $yerel));
        $this->assertSame(['ortak' => 0, 'bilinmeyen' => 2, 'bekleyen' => 3], RemoteDatabase::compareMigrations(['baska_a', 'baska_b'], $yerel));
    }

    /** Yerel liste "migrate"in gördüğüyle aynı: çalışan şema bu sürümden yeni sayılmamalı. */
    public function test_yerel_goc_listesi_calisan_semayla_ortusur(): void
    {
        $yerel = (fn (): array => $this->localMigrations())->call(app(RemoteDatabase::class));
        $calisan = DB::table('migrations')->pluck('migration')->all();

        $this->assertSame(['ortak' => count($calisan), 'bilinmeyen' => 0, 'bekleyen' => 0], RemoteDatabase::compareMigrations($calisan, $yerel));
    }
}
