<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bir teklif konuşmasının API görünümü.
 *
 * METİN HAZIR OLMAYABİLİR: teklif kuyrukta üretiliyor ve istemci durumu
 * sorarak bekliyor. Bu yüzden "durum" her yanıtta var, mesajlar ancak
 * yüklendiyse yazılıyor — kuyruğa yeni atılmış bir kayıtta sorgu
 * çalıştırmanın anlamı yok.
 *
 * @mixin Conversation
 */
class TeklifApiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'durum' => $this->durum,
            'talep' => $this->ilk_talep,
            'hata' => $this->hata_mesaji,
            'olusturma' => $this->created_at?->toIso8601String(),

            'mesajlar' => $this->whenLoaded('messages', fn (): array => $this->messages
                ->map(fn ($mesaj): array => [
                    'rol' => $mesaj->role,
                    'metin' => $mesaj->content,
                    'zaman' => $mesaj->created_at?->toIso8601String(),
                ])->all()),
        ];
    }
}
