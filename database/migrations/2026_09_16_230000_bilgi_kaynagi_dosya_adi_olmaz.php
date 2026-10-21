<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bilgi kaydının kaynağı bir DOSYA ADI olmaz.
 *
 * Kaynak alanında kaydı üreten betiğin adı (`seed_notes.php`) yazılıysa
 * yerine geçen değer, kaydın NEREDEN geldiğini söylüyor: panel kurulurken
 * gelen ilk bilgi seti.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bilgi_kayitlari')
            ->where('kaynak', 'seed_notes.php')
            ->update(['kaynak' => 'kurulum']);
    }

    public function down(): void
    {
        DB::table('bilgi_kayitlari')
            ->where('kaynak', 'kurulum')
            ->update(['kaynak' => 'seed_notes.php']);
    }
};
