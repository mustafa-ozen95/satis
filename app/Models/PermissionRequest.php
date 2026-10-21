<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kullanıcı kendi profilinden istediği
 * sayfa izinlerini işaretler, admin panelden onaylar/reddeder. Onaylanınca
 * User'daki perm_* sütunları bu kayıttaki değerlerle güncellenir.
 */
class PermissionRequest extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'perm_teklif', 'perm_bilgi', 'perm_fiyat', 'durum'];

    protected function casts(): array
    {
        return [
            'perm_teklif' => 'boolean',
            'perm_bilgi' => 'boolean',
            'perm_fiyat' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $talep) {
            $talep->created_at ??= now();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
