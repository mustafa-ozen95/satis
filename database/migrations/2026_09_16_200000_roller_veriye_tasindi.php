<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ROLLER VERİYE TAŞINIR — kardeş panellerdeki yapının aynısı.
 *
 * İki değerli bir rol dizesi (admin/user) merkezdeki rollerle eşleşmez:
 * merkezde bir birim rolü olan biri burada "user" görünür ve "kim hangi
 * birimde" sorusu buradan yanıtlanamaz. Göç dizeyi rol kaydına çevirir.
 *
 * Slug'lar kardeş panellerde de BİLEREK AYNI: merkez rol slug'ını gönderiyor ve
 * eşitleme onu buradaki karşılığına bağlıyor (bkz. IdentitySyncer).
 *
 * YETKİ ROLDEN TÜRETİLMEZ. Bu panelde ne yapılabileceğini kişiye özel
 * bayraklar söylüyor (perm_teklif/perm_bilgi/perm_fiyat); rol kimliğin
 * kendisidir, yetkinin değil. Yöneticilik ise roldedir (yonetici).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roller', function (Blueprint $table): void {
            $table->id();

            /* Kod ve merkez bu adla atıfta bulunur; yerleşik rollerde değişmez. */
            $table->string('slug', 40)->unique();
            $table->string('label');

            /* Rozet rengi: ekranda rolü ayırt etmek için (şablonla aynı adlar). */
            $table->string('badge', 20)->default('gray');

            /* Bütün kontrolleri geçen rol. */
            $table->boolean('yonetici')->default(false);

            /* Yerleşik rol: silinemez, slug'ı değişmez. */
            $table->boolean('yerlesik')->default(false);

            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();
        });

        $this->yerlesikRoller();

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('rol_id')->nullable()->after('email')->constrained('roller')->nullOnDelete();
        });

        $this->rolleriBagla();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('user')->after('email');
        });

        DB::table('users')
            ->whereIn('rol_id', DB::table('roller')->where('yonetici', true)->pluck('id'))
            ->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rol_id');
        });

        Schema::dropIfExists('roller');
    }

    /** Slug'lar merkezdekiyle ve kardeş panellerdekiyle birebir aynı. */
    private function yerlesikRoller(): void
    {
        $simdi = now();

        DB::table('roller')->insert([
            ['slug' => 'admin', 'label' => 'Yönetici', 'badge' => 'purple', 'yonetici' => true, 'yerlesik' => true, 'sira' => 1, 'created_at' => $simdi, 'updated_at' => $simdi],
            ['slug' => 'muhasebe', 'label' => 'Muhasebe', 'badge' => 'cyan', 'yonetici' => false, 'yerlesik' => true, 'sira' => 2, 'created_at' => $simdi, 'updated_at' => $simdi],
            ['slug' => 'teknik', 'label' => 'Teknik', 'badge' => 'gray', 'yonetici' => false, 'yerlesik' => true, 'sira' => 3, 'created_at' => $simdi, 'updated_at' => $simdi],
            ['slug' => 'network', 'label' => 'Network', 'badge' => 'blue', 'yonetici' => false, 'yerlesik' => true, 'sira' => 4, 'created_at' => $simdi, 'updated_at' => $simdi],
        ]);
    }

    /**
     * Var olan hesapları bağlar.
     *
     * "admin" yönetici rolüne gider. Geri kalanlar ROLSÜZ kalır ve BU
     * BİLİNÇLİ: eski "user" değeri hiçbir merkez rolüne karşılık gelmiyor ve
     * tahmin etmek, kimseye sormadan rol dağıtmak olurdu. Rolü merkez
     * söyleyecek (eşitleme turu) ya da yönetici ekrandan verecek.
     */
    private function rolleriBagla(): void
    {
        $yonetici = DB::table('roller')->where('slug', 'admin')->value('id');

        DB::table('users')->where('role', 'admin')->update(['rol_id' => $yonetici]);
    }
};
