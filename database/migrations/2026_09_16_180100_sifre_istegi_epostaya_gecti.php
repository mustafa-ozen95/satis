<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parola sıfırlama isteği de E-POSTA taşır.
 *
 * İstek, giriş yapamayan kişinin bıraktığı nottur; yöneticinin hesabı
 * bulabilmesi için giriş anahtarını taşımalı. Kullanıcı adı kalktığına göre
 * (bkz. 2026_09_16_180000) buradaki alan da anlamını yitirmişti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_reset_requests', function (Blueprint $table): void {
            $table->renameColumn('username', 'email');
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_requests', function (Blueprint $table): void {
            $table->renameColumn('email', 'username');
        });
    }
};
