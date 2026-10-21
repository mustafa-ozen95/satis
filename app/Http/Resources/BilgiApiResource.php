<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BilgiKaydi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bilgi bankası kaydının API görünümü.
 *
 * @mixin BilgiKaydi
 */
class BilgiApiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kategori' => $this->kategori,
            'baslik' => $this->baslik,
            'metin' => $this->metin,
            'kaynak' => $this->kaynak,
            'guncelleme' => $this->updated_at?->toIso8601String(),
        ];
    }
}
