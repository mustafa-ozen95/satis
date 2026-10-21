<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ApiTokenRequest;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\Api\ApiDocumentation;
use App\Services\Authorization\PermissionRegistry;
use App\Services\Settings\SettingsTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * API jetonlarının yönetimi — Ayarlar'ın bir sekmesi.
 *
 * Jeton bir KİMLİK BİLGİSİDİR: sahibi adına konuşur ve onun yetkilerini
 * taşır. Bu yüzden jeton çıkarmak YALNIZCA yöneticidedir — başkası adına
 * jeton çıkarmak, o kişinin yetkisini kalıcı bir anahtara dönüştürmektir.
 *
 * Sır YALNIZCA bir kez gösterilir. Kaybolursa geri gösterilemez; yenisi
 * üretilir (bkz. sir) ve eskisi anında geçersiz olur.
 *
 * Kardeş panellerdeki ekranın aynısı.
 */
class ApiJetonController extends Controller implements HasMiddleware
{
    public function __construct(
        private SettingsTabs $sekmeler,
        private PermissionRegistry $sozluk,
        private ActivityRecorder $kayit,
    ) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.ApiToken::class, only: ['index', 'belge', 'show']),
            new Middleware('can:create,'.ApiToken::class, only: ['store']),
            new Middleware('can:update,jeton', only: ['update', 'sir', 'iptal']),
            new Middleware('can:delete,jeton', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        return view('settings.api', [
            'sections' => $this->sekmeler->all('api'),
            'tokens' => ApiToken::query()->with(['user', 'creator'])->latest('id')->get(),
            'users' => User::query()->where('aktif', true)->orderBy('name')->pluck('name', 'id')->all(),
            'resources' => $this->kaynaklar(),
            'actionLabels' => $this->sozluk->actions(),
            'defaultLifetime' => (int) config('api.default_lifetime_days'),
        ]);
    }

    /**
     * API belgesi.
     *
     * Uç listesi ROTALARDAN üretilir; elle yazılmış ikinci bir liste yoktur
     * (bkz. ApiDocumentation). Belgesiz bir uç eklendiğinde test kırılır.
     */
    public function belge(ApiDocumentation $belgeler): View
    {
        return view('settings.api-docs', [
            'sections' => $this->sekmeler->all('api'),
            'endpoints' => $belgeler->endpoints(),
            'baseUrl' => rtrim((string) config('app.url'), '/').'/api/v1',
            'rateLimit' => (int) config('api.rate_limit'),
            'prefix' => (string) config('api.prefix'),
        ]);
    }

    public function show(ApiToken $jeton): View
    {
        return view('settings.api-token', [
            'sections' => $this->sekmeler->all('api'),
            'token' => $jeton->load(['user', 'creator']),
            'resources' => $this->kaynaklar(),
            'actionLabels' => $this->sozluk->actions(),
            'abilities' => $jeton->abilityMap(),
        ]);
    }

    public function store(ApiTokenRequest $request): RedirectResponse
    {
        $sahip = User::query()->findOrFail($request->integer('user'));
        $sir = ApiToken::mint();

        $jeton = ApiToken::query()->create([
            'name' => $request->string('name')->toString(),
            'user_id' => $sahip->id,
            'created_by' => $request->user()?->id,
            'token_hash' => $sir['hash'],
            'prefix' => $sir['prefix'],
            'abilities' => $request->abilities($this->sozluk),
            'allowed_ips' => $request->ipList(),
            'expires_at' => $request->expiresAt(),
        ]);

        $this->kayit->kaydet(
            'api_jeton_olustur',
            "API jetonu çıkarıldı: {$jeton->name} · sahibi {$sahip->email}",
            $jeton,
        );

        return redirect()
            ->route('admin.api-jetonlari.show', $jeton)
            // Sır YALNIZCA burada görünür; oturumda tek seferlik durur.
            ->with('token_plain', $sir['plain'])
            ->with('status', 'Jeton çıkarıldı. Sır yalnızca şimdi gösterilir.');
    }

    public function update(ApiTokenRequest $request, ApiToken $jeton): RedirectResponse
    {
        $jeton->update([
            'name' => $request->string('name')->toString(),
            'abilities' => $request->abilities($this->sozluk),
            'allowed_ips' => $request->ipList(),
            'expires_at' => $request->expiresAt(),
        ]);

        $this->kayit->kaydet('api_jeton_guncelle', "API jetonu güncellendi: {$jeton->name}", $jeton);

        return back()->with('status', "{$jeton->name} kaydedildi.");
    }

    /**
     * Yeni bir sır üretir; eskisi anında geçersiz olur.
     */
    public function sir(ApiToken $jeton): RedirectResponse
    {
        $duz = $jeton->rotate();

        $this->kayit->kaydet('api_jeton_sir', "API jetonunun sırrı değiştirildi: {$jeton->name}", $jeton);

        return back()
            ->with('token_plain', $duz)
            ->with('status', 'Yeni sır üretildi. Eski jeton bu andan itibaren geçersiz.');
    }

    /**
     * İptal eder ama SİLMEZ: kimin hangi jetonla ne yaptığı kayıtta durur ve
     * iptal edilmiş jetonun adı da o kaydın parçasıdır.
     */
    public function iptal(ApiToken $jeton): RedirectResponse
    {
        $jeton->revoke();

        $this->kayit->kaydet('api_jeton_iptal', "API jetonu iptal edildi: {$jeton->name}", $jeton);

        return back()->with('status', "{$jeton->name} iptal edildi.");
    }

    public function destroy(ApiToken $jeton): RedirectResponse
    {
        $ad = $jeton->name;
        $jeton->delete();

        $this->kayit->kaydet('api_jeton_sil', "API jetonu silindi: {$ad}", $jeton);

        return redirect()->route('admin.api-jetonlari.index')->with('status', "{$ad} silindi.");
    }

    /**
     * @return list<array{key: string, label: string, actions: list<string>}>
     */
    private function kaynaklar(): array
    {
        return array_map(fn (string $anahtar): array => [
            'key' => $anahtar,
            'label' => $this->sozluk->label($anahtar),
            'actions' => $this->sozluk->actionsFor($anahtar),
        ], $this->sozluk->resourceKeys());
    }
}
