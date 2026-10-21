<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiToken;
use App\Models\User;

/**
 * API jetonu yönetimi yalnızca yöneticidedir.
 *
 * NEDEN SADECE YÖNETİCİ: jeton, sahibinin yetkisini kalıcı bir anahtara
 * dönüştürür. Başkası adına jeton çıkarabilen biri, o kişinin yetkisini
 * istediği yere taşıyabilir — bu, yetki dağıtmakla aynı ağırlıkta bir iştir.
 *
 * Bu panelde Gate::before'da yönetici geçişi YOK, o yüzden kural burada
 * açıkça yazılı. Yöneticinin de yapamayacağı bir şey çıkarsa yeri
 * burasıdır.
 */
class ApiTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ApiToken $token): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ApiToken $token): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ApiToken $token): bool
    {
        return $user->isAdmin();
    }
}
