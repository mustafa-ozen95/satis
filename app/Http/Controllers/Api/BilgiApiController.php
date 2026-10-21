<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BilgiApiResource;
use App\Models\BilgiKaydi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Bilgi bankası — okuma.
 *
 * Kayıtlar VERİTABANINDAN okunur, vektör indeksinden değil: liste ve süzme
 * için anlamsal aramaya gerek yok ve dış servis düştüğünde bu uç çalışmaya
 * devam etmeli.
 *
 * Yetkilendirme: HasMiddleware ile `can:bilgi:view`.
 */
class BilgiApiController extends Controller implements HasMiddleware
{
    private const SAYFA = 100;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('can:bilgi:view')];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $kategori = trim((string) $request->query('kategori', ''));
        $ara = trim((string) $request->query('ara', ''));

        $sorgu = BilgiKaydi::query()
            ->when($kategori !== '', fn ($s) => $s->where('kategori', $kategori))
            ->when($ara !== '', fn ($s) => $s->metinde($ara))
            ->orderBy('kategori')
            ->orderBy('baslik');

        return BilgiApiResource::collection($sorgu->paginate(self::SAYFA)->withQueryString());
    }
}
