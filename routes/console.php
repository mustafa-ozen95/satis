<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Zamanlanmış işler
|--------------------------------------------------------------------------
|
| Konteynerde bunları cron değil `php artisan schedule:work` koşturuyor
| (bkz. docker/rootfs/etc/supervisor/supervisord.conf); ayrı bir cron satırı
| gerekmez.
|
*/

/*
 * Başarısız iş kayıtları SONSUZA KADAR birikiyor.
 *
 * LLM çağrısı kota ya da ağ yüzünden düşebiliyor ve her düşen iş tabloya
 * tam bir yığın izi yazıyor. Bir hafta geriye bakmak sorunu görmeye yeter;
 * ötesi yalnızca yedeği şişirir.
 */
Schedule::command('queue:prune-failed --hours=168')->daily();

/*
 * MERKEZDEKİ ERİŞİM DEĞİŞİKLİKLERİ — GÜNDE BİR, YEDEK OLARAK.
 *
 * Asıl yol BİLDİRİM: merkez rol ya da durum değişince anında haber veriyor
 * (bkz. routes/api.php → identity/refresh) ve panel doğruyu kendi soruyor.
 * Beş dakikada bir yoklamaya gerek yok — merkez zaten tetikliyor.
 *
 * Tur yine de durur: bildirim kaybolabilir (ağ, yeniden başlatma,
 * panel o an kapalı) ve kaybolan bir bildirimin bedeli KALICI ayrışma
 * olmamalı. Gecede bir hizalama, sessizce eskimiş bir erişimi yakalar.
 *
 * withoutOverlapping: merkez yavaşsa turlar üst üste binmesin.
 */
Schedule::command('identity:sync')->dailyAt('04:10')->withoutOverlapping();

/*
 * DENETİM ZİNCİRİNİ DOĞRULA.
 *
 * Zincir bir şeyi kendiliğinden korumaz; kırıldığını FARK ETMEK korur. Gece
 * bir kez baştan sona doğrulanır ve sonuç önbellekte durur; ekran oradan
 * okur, her açılışta bütün satırları yeniden özetlemez.
 */
Schedule::command('audit:verify')->dailyAt('03:30');

/*
 * BİLGİ BANKASI İNDEKSİ.
 *
 * Asıl veri veritabanında; Chroma ondan türetilen indeks. Bir kayıt
 * yazılırken Chroma erişilemezse kayıt yine durur ama indekste yer almaz —
 * bu tur onu yakalar. Tur olmasaydı eksik indeks sessizce kalır ve teklif
 * üretimi o bilgiyi hiç görmezdi.
 */
Schedule::command('knowledge:index')->hourly()->withoutOverlapping();

/*
 * KURUMSAL SİTEDEKİ BİLGİLER.
 *
 * Site değiştiğinde (yeni hizmet, güncellenen kapsam, eklenen soru) bilgi
 * bankası eskiyor ve model müşteriye artık geçerli olmayan bir şey
 * söyleyebiliyor. Tur haftada bir sayfaları okur, değişeni günceller,
 * siteden kalkanı siler. Sayfa listesi kurulumdan gelir (bkz.
 * config/company.php); liste yoksa tur bir şey yapmadan biter.
 *
 * HAFTALIK YETER: sayfalar günde bir değişmiyor ve değişmeyen bir tur
 * yalnızca sayfaları indirmek demek — gömme çağrısı yalnızca gerçekten
 * değişen kayıt için yapılıyor.
 *
 * Pazartesi sabahı: hafta içi ilk teklif yazılmadan önce taze olsun.
 */
Schedule::command('knowledge:site')->weeklyOn(1, '05:30')->withoutOverlapping();

/*
 * YENİ SÜRÜM VAR MI — HER SABAH.
 *
 * Yalnızca bakar ve sonucu yazar; yeni sürüm varsa yönetici panelin üstünde
 * uyarı görür. Güncellemenin kendisi sunucudan yapılır. Ayarlar →
 * Güncelleme'deki düğme aynı kontrolü hemen yapar.
 */
Schedule::command('update:check')->dailyAt('06:50');
