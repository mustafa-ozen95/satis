<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Denetim kaydı: kim, ne zaman, ne yaptı.
 *
 * Satırlar HMAC-SHA256 ile zincirlenir (bkz. Services\Activity\ActivityChain):
 * her satır bir öncekinin özetini taşır. Sonradan değiştirilen ya da silinen
 * bir satır zinciri kırar ve `denetim:dogrula` bunu söyler.
 *
 * Tablo "kim kaç teklif üretti" sayacı olarak doğmuştu; istatistik ekranı
 * hâlâ `tur` alanına bakıyor ve o üç değer (teklif_olustur, fiyat_ekle,
 * bilgi_ekle) aynen çalışıyor.
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    /*
     * ozet, onceki_ozet ve zincir_surumu BİLEREK fillable DEĞİLDİR: onları
     * yalnızca ActivityChain yazar. Toplu atamaya açılsalardı bir controller
     * kendi özetini uydurabilir ve zincirin varlık sebebi ortadan kalkardı.
     */
    protected $fillable = ['user_id', 'kullanici_adi', 'tur', 'aciklama', 'konu_type', 'konu_id', 'ip_adresi'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->created_at ??= now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function konu(): MorphTo
    {
        return $this->morphTo();
    }
}
