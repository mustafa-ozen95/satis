<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kullanıcı rolü.
 *
 * Rol bir KAYITTIR, koda gömülü bir dize değil: slug'lar merkezdekiyle ve
 * kardeş panellerdekiyle birebir aynı ve merkez rol değişikliğini bu slug
 * üzerinden bildiriyor.
 *
 * ROL KİMLİKTİR, YETKİ DEĞİL. Bu panelde ne yapılabileceğini kişiye özel
 * bayraklar söyler (perm_teklif/perm_bilgi/perm_fiyat); rol yalnızca "bu kişi
 * kim" sorusunu yanıtlar. Tek istisna yöneticiliktir ve o da roldedir.
 */
class Role extends Model
{
    protected $table = 'roller';

    protected $fillable = ['slug', 'label', 'badge', 'yonetici', 'yerlesik', 'sira'];

    protected function casts(): array
    {
        return ['yonetici' => 'boolean', 'yerlesik' => 'boolean'];
    }

    /** @return HasMany<User, $this> */
    public function kullanicilar(): HasMany
    {
        return $this->hasMany(User::class, 'rol_id');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sira')->orderBy('label');
    }
}
