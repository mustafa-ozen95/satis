<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Bilgi bankası kaydı (ekleme ve güncelleme aynı kuralları paylaşır).
 *
 * Kurallar TEK YERDE: ekleme ve güncelleme ekranlarında ayrı ayrı yazılıydı
 * ve biri değişince diğeri sessizce eski kalabilirdi.
 */
class BilgiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('perm_bilgi', User::class);
    }

    /**
     * Kategori listeden seçilir; "+ Yeni kategori…" seçildiyse gerçek değer
     * yanındaki kutudadır. Çevrim BURADA yapılır: kural listesi ve ekranın
     * geri kalanı tek bir alan görmeye devam etsin.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('kategori') === '__yeni') {
            $this->merge(['kategori' => trim((string) $this->input('kategori_yeni'))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'baslik' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'string', 'max:255'],
            'bilgi' => ['required', 'string'],
        ];
    }
}
