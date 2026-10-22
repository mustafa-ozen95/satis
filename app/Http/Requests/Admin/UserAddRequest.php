<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Yeni kullanıcı açma — YALNIZCA yönetici.
 *
 * "Kullanıcıları düzenle" yetkisi, kendini yönetici yapma yetkisi değildir;
 * bu yüzden kapı burada `admin` kapısıdır.
 */
class UserAddRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'ad' => ['required', 'string', 'max:100'],
            /*
             * E-POSTA MERKEZLE GİRİŞİN ANAHTARI: kimlik yerel hesapla bu
             * alandan eşleşiyor. Benzersiz olmak ZORUNDA — iki hesap aynı
             * adresi taşırsa hangisine girileceği belli olmaz ve eşleştirici
             * de tam olarak bu yüzden "tek karşılık" arıyor.
             */
            /*
             * E-POSTA ZORUNLU: giriş anahtarı ve merkezle eşleşmenin kendisi
             * o. Adressiz bir hesap ne parolayla ne merkezle girebilirdi.
             */
            'eposta' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'sifre' => ['required', 'string', 'min:'.config('panel.password_length')],
            /*
             * ROL BİR KAYITTIR: slug'ı doğrulanır, listeye koda gömülü bir
             * dizi yazılmaz. Yeni bir rol eklendiğinde burayı da düzenlemek
             * gerekseydi biri unutulurdu.
             */
            'rol' => ['required', 'string', 'exists:roller,slug'],
            'perm_teklif' => ['boolean'],
            'perm_bilgi' => ['boolean'],
            'perm_fiyat' => ['boolean'],
        ];
    }
}
