<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Yetkilendirme: PriceItemPolicy@manage üzerinden Controller'da ayrıca kontrol ediliyor.
        return true;
    }

    /**
     * Kategori listeden seçilir; "+ Yeni kategori…" seçildiyse gerçek değer
     * yanındaki kutudadır.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('category') === '__yeni') {
            $this->merge(['category' => trim((string) $this->input('category_yeni'))]);
        }
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'kdv_dahil' => ['boolean'],
            'notlar' => ['nullable', 'string'],
        ];
    }
}
