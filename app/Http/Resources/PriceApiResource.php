<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PriceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bir fiyat kaleminin API görünümü.
 *
 * Alan listesi TEK YERDE: liste ucu ile tek kayıt ucu aynı biçimi döndürmek
 * zorunda, yoksa istemci iki ayrı çözümleyici yazmak zorunda kalır.
 *
 * @mixin PriceItem
 */
class PriceApiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kategori' => $this->category,
            'ad' => $this->name,
            'fiyat' => (float) $this->price,
            'para_birimi' => $this->currency,
            'birim' => $this->unit,
            'kdv_dahil' => (bool) $this->kdv_dahil,
            'notlar' => $this->notlar,
            'guncelleme' => $this->updated_at?->toIso8601String(),
        ];
    }
}
