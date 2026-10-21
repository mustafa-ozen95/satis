<x-layout.app title="Hesabım">
<div class="page">
  <div class="page__inner">
    <header class="page-head">
      <div class="page-head__text">
        <h1>Hesabım</h1>
        <p class="page-head__subtitle">Kendi hesap bilgileriniz, parolanız ve yetki talepleriniz.</p>
      </div>
    </header>

    <section class="panel">
      <header class="panel__head">
        <div>
          <h2 class="panel__title">Bilgiler</h2>
        </div>
      </header>
      <div class="panel__body">
        @if ($buFormAd && $errors->any())
        <div class="alert alert--danger field--separated">
          <ul class="alert__list">
            @foreach ($errors->all() as $hataMesaji)
            <li>{{ $hataMesaji }}</li>
            @endforeach
          </ul>
        </div>
        @endif

        <form method="post" action="{{ route('profil.ad') }}" class="form-dar">
          @csrf
          <input type="hidden" name="_form" value="ad">
          <div class="field">
            <label class="field__label" for="ad">Ad soyad</label>
            <input
              type="text"
              class="input"
              id="ad"
              name="ad"
              value="{{ $buFormAd ? old('ad') : $navUser->name }}"
              aria-invalid="{{ $buFormAd && $errors->has('ad') ? 'true' : 'false' }}"
              required
            >
          </div>
          <div class="field">
            <span class="field__label">Rol</span>
            <div>
              @if ($navUser->rol)
                <span class="badge badge--{{ $navUser->rol->badge }}">{{ $navUser->rol->label }}</span>
              @else
                <span class="text-muted">rol atanmamış</span>
              @endif
            </div>
            <span class="field__hint">Rolünüzü yalnızca yöneticiniz değiştirebilir.</span>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--sm">Ad soyadnı güncelle</button>
          </div>
        </form>
      </div>
    </section>

    <section class="panel">
      <header class="panel__head">
        <div>
          <h2 class="panel__title">Parola</h2>
          <p class="text-sm text-muted">Değiştirdiğinizde tekrar giriş yapmanız gerekmez, mevcut oturumunuz açık kalır.</p>
        </div>
      </header>
      <div class="panel__body">
        @if ($buFormSifre && $errors->any())
        <div class="alert alert--danger field--separated">
          <ul class="alert__list">
            @foreach ($errors->all() as $hataMesaji)
            <li>{{ $hataMesaji }}</li>
            @endforeach
          </ul>
        </div>
        @endif

        <form method="post" action="{{ route('profil.sifre') }}" class="form-dar">
          @csrf
          <input type="hidden" name="_form" value="sifre">
          <div class="field">
            <label class="field__label" for="mevcut">Mevcut parola</label>
            <input type="password" class="input" id="mevcut" name="mevcut" autocomplete="current-password" aria-invalid="{{ $buFormSifre && $errors->has('mevcut') ? 'true' : 'false' }}" required>
          </div>
          <div class="field">
            <label class="field__label" for="yeni_sifre">Yeni parola</label>
            <input type="password" class="input" id="yeni_sifre" name="yeni_sifre" autocomplete="new-password" aria-invalid="{{ $buFormSifre && $errors->has('yeni_sifre') ? 'true' : 'false' }}" required>
            <span class="field__hint">En az 4 karakter.</span>
          </div>
          <div class="field">
            <label class="field__label" for="yeni_sifre_confirmation">Yeni parola (tekrar)</label>
            <input type="password" class="input" id="yeni_sifre_confirmation" name="yeni_sifre_confirmation" autocomplete="new-password" required>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--sm">Parolayı güncelle</button>
          </div>
        </form>
      </div>
    </section>

    <section class="panel">
      <header class="panel__head">
        <div>
          <h2 class="panel__title">Yetkiler</h2>
          <p class="text-sm text-muted">Sayfa izinlerinizi kendiniz değiştiremezsiniz; istediklerinizi işaretleyip yöneticinizin onayına gönderebilirsiniz.</p>
        </div>
      </header>
      <div class="panel__body">
        <div class="facts field--separated">
          <div class="facts__row">
            <span class="facts__key">Şu anki yetkileriniz</span>
            <span class="facts__value">
              @if ($navUser->isAdmin())
                <span class="badge badge--purple">admin — hepsi</span>
              @else
                {{ collect(['perm_teklif' => 'Teklif Oluştur', 'perm_bilgi' => 'Bilgi Bankası', 'perm_fiyat' => 'Fiyat Kalemleri'])
                    ->filter(fn ($etiket, $alan) => $navUser->$alan)
                    ->implode(', ') ?: 'yok' }}
              @endif
            </span>
          </div>
        </div>

        @if ($navUser->isAdmin())
        <p class="text-sm text-muted field--separated">Admin rolü zaten tüm sayfalara erişir, yetki talebi göndermeniz gerekmez.</p>
        @elseif ($bekleyenTalep)
        <div class="alert alert--info field--separated">
          <strong>Onay bekleyen talebiniz var:</strong>
          {{ collect(['perm_teklif' => 'Teklif Oluştur', 'perm_bilgi' => 'Bilgi Bankası', 'perm_fiyat' => 'Fiyat Kalemleri'])
              ->filter(fn ($etiket, $alan) => $bekleyenTalep->$alan)
              ->implode(', ') ?: 'hiçbiri (tüm izinleri kaldırma talebi)' }}
          — {{ $bekleyenTalep->created_at->format('d.m.Y H:i') }}
        </div>
        @else
        <form method="post" action="{{ route('profil.yetki-talebi') }}" class="field--separated">
          @csrf
          <div class="choices">
            <label class="checkbox">
              <input type="checkbox" name="perm_teklif" value="1" {{ $navUser->perm_teklif ? 'checked' : '' }}>
              Teklif Oluştur
            </label>
            <label class="checkbox">
              <input type="checkbox" name="perm_bilgi" value="1" {{ $navUser->perm_bilgi ? 'checked' : '' }}>
              Bilgi Bankası
            </label>
            <label class="checkbox">
              <input type="checkbox" name="perm_fiyat" value="1" {{ $navUser->perm_fiyat ? 'checked' : '' }}>
              Fiyat Kalemleri
            </label>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--sm">Onaya gönder</button>
          </div>
        </form>
        @endif
      </div>
    </section>
  </div>
</div>
</x-layout.app>
