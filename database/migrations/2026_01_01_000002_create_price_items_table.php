<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_items', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index();
            $table->string('name');
            $table->string('unit')->default('ay');
            $table->decimal('price', 12, 2);
            $table->string('currency', 10)->default('TL');
            $table->boolean('kdv_dahil')->default(false);
            $table->boolean('aktif')->default(true);
            $table->text('notlar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_items');
    }
};
