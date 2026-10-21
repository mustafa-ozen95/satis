<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kullanıcı güncelleme — YALNIZCA yönetici.
 */
class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'yeni_sifre' => ['nullable', 'string', 'min:'.config('panel.parola_uzunluk')],
            /* Gerekçesi UserAddRequest'te; kural aynı olmak zorunda. */
            'ad' => ['required', 'string', 'max:100'],

            'eposta' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('kullanici')?->getKey()),
            ],
            'rol' => ['required', 'string', 'exists:roller,slug'],
            'aktif' => ['boolean'],
            'perm_teklif' => ['boolean'],
            'perm_bilgi' => ['boolean'],
            'perm_fiyat' => ['boolean'],
        ];
    }

    /**
     * Parola alanı FORMDAN GELMEYEBİLİR ve "nullable" onu var saymaz.
     * Doğrudan indislemek "Undefined array key" ile düşüyordu; burada
     * bir kez normalleştirilir, controller indisleme derdine girmez.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['yeni_sifre' => $this->input('yeni_sifre')]);
    }
}
