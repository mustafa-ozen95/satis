<?php

declare(strict_types=1);

namespace App\Http\Requests\Teklif;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Yeni teklif talebi.
 *
 * Uzunluk sınırı keyfi değil: metin olduğu gibi dil modeline gidiyor ve
 * sınırsız bir gövde hem maliyet hem zaman aşımı demek.
 */
class TalepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('perm_teklif', User::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'talep' => ['required', 'string', 'max:4000'],
        ];
    }
}
