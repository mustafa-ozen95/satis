<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'rol_id',
        'rol_yerel',
        'password',
        'aktif',
        'perm_teklif',
        'perm_bilgi',
        'perm_fiyat',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'rol_yerel' => 'boolean',
            'perm_teklif' => 'boolean',
            'perm_bilgi' => 'boolean',
            'perm_fiyat' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** Merkezden gelen kimlik bağları. */
    public function kimlikler(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    /**
     * Yöneticilik ROLDEDİR.
     *
     * Tek doğruluk kaynağı rol kaydıdır (bkz. roller_veriye_tasindi). Rolsüz kullanıcı
     * yönetici değildir — eksik veri yetki üretmemeli.
     */
    public function isAdmin(): bool
    {
        return $this->rol?->yonetici === true;
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function permissionRequests()
    {
        return $this->hasMany(PermissionRequest::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
