<?php

namespace App\Policies;

use App\Models\User;

/**
 * Fiyat Kalemleri sayfasına erişim/işlem yetkisi: admin ya da perm_fiyat.
 */
class PriceItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->perm_fiyat;
    }

    public function manage(User $user): bool
    {
        return $this->viewAny($user);
    }
}
