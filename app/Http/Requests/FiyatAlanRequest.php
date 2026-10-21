<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PriceItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Tek bir fiyat alanının yerinde düzenlenmesi.
 *
 * ALAN LİSTESİ KAPALI: istek gövdesinden gelen alan adı doğrudan modele
 * yazılsaydı, ekranda hiç görünmeyen sütunlar da (aktif, kdv_dahil, notlar)
 * bu uçtan değiştirilebilirdi. Yazılabilecekler burada sayılı.
 *
 * KURALLAR TAM FORMLA AYNI OLMAK ZORUNDA: iki yerde ayrı yazılsaydı biri
 * gevşer ve aynı sütuna iki farklı kural uygulanırdı. Bu yüzden kurallar
 * StorePriceItemRequest'ten okunuyor.
 */
class FiyatAlanRequest extends FormRequest
{
    /** Yerinde düzenlenebilen alanlar. */
    public const ALANLAR = ['category', 'name', 'price', 'unit', 'currency'];

    public function authorize(): bool
    {
        return Gate::allows('manage', PriceItem::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $alan = (string) $this->input('alan');

        /* Para birimi tam formda yok; kuralı burada, tek yerde duruyor. */
        /*
         * DÜZ NESNE, konteynerden DEĞİL: konteynerden çözülen bir FormRequest
         * geçerli isteğe bağlanıp kendi doğrulamasını koşturuyor ve bu uç
         * "category zorunlu" diyerek düşüyordu. Bize yalnızca kural listesi
         * lazım.
         */
        $kurallar = [
            ...(new StorePriceItemRequest)->rules(),
            'currency' => ['required', 'string', 'max:10'],
        ];

        return [
            'alan' => ['required', 'string', 'in:'.implode(',', self::ALANLAR)],
            'deger' => $kurallar[$alan] ?? ['required'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'deger' => match ((string) $this->input('alan')) {
                'category' => 'kategori',
                'name' => 'ad',
                'price' => 'fiyat',
                'unit' => 'birim',
                'currency' => 'para birimi',
                default => 'değer',
            },
        ];
    }
}
