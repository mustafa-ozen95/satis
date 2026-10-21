<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OKUNMAMIŞ YANIT GÖRÜNSÜN.
 *
 * Teklif kuyrukta üretiliyor ve kullanıcı bu sırada başka bir işe geçiyor.
 * Metin hazır olduğunda listede bir işaret olmazsa yanıtın geldiği ancak
 * konuşmayı açınca fark edilir.
 *
 * İşaret ZAMAN olarak tutuluyor, "okundu" bayrağı olarak değil: aynı
 * konuşmaya sonradan yeni bir yanıt geldiğinde satır yeniden okunmamış
 * sayılmalı ve bayrak bunu ayırt edemezdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('okundu_at')->nullable()->after('durum');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('okundu_at');
        });
    }
};
