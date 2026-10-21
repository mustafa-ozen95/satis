<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BİLGİ BANKASININ ASLI VERİTABANINDA DURUR.
 *
 * Chroma bir vektör İNDEKSİDİR, veri deposu değil. Kayıtlar yalnızca
 * Chroma'da dursaydı:
 *
 *  1. YEDEĞE GİRMEZDİ. Panelin yedeği veritabanı hacmini alır; Chroma başka
 *     bir konteynerin hacminde durur.
 *  2. CHROMA DÜŞÜNCE EKRAN DA DÜŞERDİ. Liste, arama, hatta teklif üretimi
 *     dış bir servise bağlı kalırdı.
 *  3. METİN ARAMASI OLMAZDI. "Şu cümle hangi kayıtta geçiyor" sorusu için
 *     embedding üretip vektör araması yapmak gerekirdi.
 *
 * Asıl veri burada; Chroma ondan TÜRETİLEN indekstir ve
 * `bilgi:indeksle` ile her an yeniden kurulabilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bilgi_kayitlari', function (Blueprint $table): void {
            $table->id();

            $table->string('baslik');
            $table->string('kategori');
            $table->text('metin');

            /* Nereden geldi: elle mi girildi, ilk kurulumla mı. */
            $table->string('kaynak', 60)->default('manuel');

            /*
             * Chroma'daki karşılığının kimliği. NULL ise kayıt henüz
             * indekslenmemiş demektir (Chroma o an erişilemezdi); arka plandaki
             * indeksleme onu yakalar.
             */
            $table->uuid('chroma_id')->nullable()->unique();
            $table->timestamp('indekslendi')->nullable();

            $table->foreignIdFor(User::class, 'ekleyen_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['kategori', 'baslik']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bilgi_kayitlari');
    }
};
