<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\User;

/**
 * JETON SAHİBİNİ AŞAMAZ.
 *
 * API isteği bir kullanıcı adına gelir ve o kullanıcının panel yetkileri
 * neyse jeton en fazla onu yapabilir. Jetonun kendi listesi bunu yalnızca
 * DARALTIR; genişletemez.
 *
 * Neden böyle: jeton bir kez üretilip bir yere yazılıyor ve orada unutuluyor.
 * Sahibinin yetkisi daraltıldığında jeton da daralmazsa, kimsenin haberi
 * olmayan bir arka kapı kalırdı.
 *
 * Tarayıcıdan gelen isteklerde HİÇ DEVREYE GİRMEZ: bağlamda jeton yoksa
 * karar Gate'in kendi zincirine bırakılır (null).
 */
class TokenAbilityGate
{
    public function __construct(private ApiTokenContext $context) {}

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function check(User $user, string $ability, array $arguments = []): ?bool
    {
        $token = $this->context->get();

        if ($token === null) {
            return null;
        }

        /*
         * Yetenek adı "kaynak:aksiyon" biçiminde; panelin kendi kapıları
         * (perm_teklif gibi) bu biçimde değil ve jetonla sorulmaz.
         */
        if (! str_contains($ability, ':')) {
            return null;
        }

        [$kaynak, $aksiyon] = explode(':', $ability, 2);

        /*
         * Jetonun listesinde yoksa REDDEDİLİR (false döner, zincir burada
         * biter). Sahibinin yetkisi ayrıca sorulur: false dönmediğimizde
         * karar panelin kendi kapılarına kalır.
         */
        return $token->permits($kaynak, $aksiyon) ? null : false;
    }
}
