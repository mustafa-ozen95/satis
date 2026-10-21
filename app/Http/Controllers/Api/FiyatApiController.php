<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FiyatApiResource;
use App\Models\PriceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Fiyat kalemleri — okuma.
 *
 * YAZMA UCU YOK ve bu bilinçli: fiyat listesi satış ekibinin elinden
 * değişmeli, bir jetonun arkasından değil. Okuma ise entegrasyonların
 * (site, teklif aracı) asıl ihtiyacı.
 *
 * Yetkilendirme: HasMiddleware ile `can:fiyat:view`. Jetonun kendi listesi
 * bunu ayrıca daraltır (bkz. TokenAbilityGate).
 */
class FiyatApiController extends Controller implements HasMiddleware
{
    /** Sayfa başına kayıt; istemci daha fazlasını isteyemez. */
    private const SAYFA = 100;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('can:fiyat:view')];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $kategori = trim((string) $request->query('kategori', ''));
        $ara = trim((string) $request->query('ara', ''));

        $sorgu = PriceItem::query()
            ->aktif()
            ->when($kategori !== '', fn ($s) => $s->where('category', $kategori))
            ->when($ara !== '', fn ($s) => $s->where(fn ($i) => $i
                ->where('name', 'like', "%{$ara}%")
                ->orWhere('category', 'like', "%{$ara}%")
                ->orWhere('notlar', 'like', "%{$ara}%")))
            ->orderBy('category')
            ->orderBy('name');

        return FiyatApiResource::collection($sorgu->paginate(self::SAYFA)->withQueryString());
    }

    public function show(PriceItem $fiyat): FiyatApiResource
    {
        return new FiyatApiResource($fiyat);
    }
}
