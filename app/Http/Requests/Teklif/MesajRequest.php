<?php

declare(strict_types=1);

namespace App\Http\Requests\Teklif;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Var olan bir konuşmaya devam mesajı.
 *
 * Yetkilendirme: konuşmanın SAHİPLİĞİ controller'da ConversationPolicy ile
 * denetleniyor — kural kaydın kendisine ait, isteğe değil.
 */
class MesajRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'mesaj' => ['required', 'string', 'max:4000'],
        ];
    }
}
