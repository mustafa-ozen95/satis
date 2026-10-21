<?php

declare(strict_types=1);

namespace App\Http\Requests\Teklif;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Seçili konuşmaları silme.
 *
 * Yetkilendirme: sorgu KULLANICININ KENDİ konuşmalarıyla sınırlı
 * (controller'daki `conversations()` ilişkisi). Listeyi isteğe göre daraltmak
 * "ekranda gizlemek" olurdu; burada daraltma sorgunun kendisinde.
 */
class TopluSilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'secili_id' => ['required', 'array', 'min:1'],
            'secili_id.*' => ['integer'],
        ];
    }
}
