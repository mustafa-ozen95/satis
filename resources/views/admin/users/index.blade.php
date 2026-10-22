<x-layout.app title="Kullanıcılar">
<div class="page">
  <div class="page__inner page__inner--wide">
    <header class="page-head">
      <div class="page-head__text">
        <h1>Kullanıcı Yönetimi</h1>
        <p class="page-head__subtitle">Panele erişecek kullanıcıları, yetkilerini ve şifre sıfırlama isteklerini buradan yönetin.</p>
      </div>
      <div class="page-head__actions">
        <a href="#" class="btn btn--primary btn--sm" data-modal-open="user-add"><x-icon name="plus" />Kullanıcı ekle</a>
      </div>
    </header>

    @if ($yetkiTalepleri->isNotEmpty())
    <section class="panel">
      <header class="panel__head">
        <div>
          <h2 class="panel__title"><x-icon name="key" /> Bekleyen yetki talepleri</h2>
        </div>
      </header>
      <div class="panel__body panel__body--flush">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Kullanıcı</th><th>Talep edilen yetkiler</th><th>Talep zamanı</th><th></th></tr>
            </thead>
            <tbody>
              @foreach ($yetkiTalepleri as $t)
              <tr>
                <td data-label="Kullanıcı" class="row-title">{{ $t->user->name }}</td>
                <td data-label="Talep edilen yetkiler">
                  {{ collect(['perm_teklif' => 'Teklif Oluştur', 'perm_bilgi' => 'Bilgi Bankası', 'perm_fiyat' => 'Fiyat Kalemleri'])
                      ->filter(fn ($etiket, $alan) => $t->$alan)
                      ->implode(', ') ?: 'hiçbiri (tüm izinleri kaldırma talebi)' }}
                </td>
                <td data-label="Talep zamanı" class="table__cell--mono">{{ $t->created_at->format('d.m.Y H:i') }}</td>
                <td>
                  <div class="table__row-actions">
                    <form method="post" action="{{ route('admin.permission-requests.approve', $t) }}">
                      @csrf
                      <button type="submit" class="btn btn--sm btn--primary"><x-icon name="check" />Onayla</button>
                    </form>
                    <form method="post" action="{{ route('admin.permission-requests.reject', $t) }}">
                      @csrf
                      <button type="submit" class="btn btn--sm">Reddet</button>
                    </form>
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>
    @endif

    <section class="panel">
      <header class="panel__head">
        <div>
          <h2 class="panel__title">Kullanıcılar</h2>
        </div>
      </header>
      <div class="panel__body panel__body--flush">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Kullanıcı</th>
                <th>E-posta</th>
                <th>Rol</th>
                <th>Durum</th>
                <th>İzinler</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($kullanicilar as $k)
              <tr>
                <td data-label="Kullanıcı" class="row-title">{{ $k->name }}</td>
                <td data-label="E-posta" class="mono text-muted">{{ $k->email ?? '—' }}</td>
                {{-- Rozet rengi rolün kendi kaydından gelir; ekrana koda gömülü renk yazılmaz. --}}
                <td data-label="Rol">
                  @if ($k->rol)
                    <span class="badge badge--{{ $k->rol->badge }}">{{ $k->rol->label }}</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif

                  {{--
                    Rol ekrandan verildiyse merkez eşitlemesi ona dokunmuyor;
                    bunu söylemezsek "merkezde başka rol ama burada yönetici"
                    durumu açıklanamaz görünürdü.
                  --}}
                  @if ($k->rol_yerel)
                    <span class="text-sm text-muted" title="Bu rol panelde verildi; merkez eşitlemesi değiştirmiyor.">· yerel</span>
                  @endif
                </td>
                <td data-label="Durum">
                  <span class="status">
                    <span class="dot {{ $k->aktif ? 'dot--green' : 'dot--gray' }}" aria-hidden="true"></span>
                    {{ $k->aktif ? 'Aktif' : 'Pasif' }}
                  </span>
                </td>
                <td data-label="İzinler" class="text-sm text-muted">
                  @if ($k->isAdmin())
                    hepsi
                  @else
                    {{ collect(['perm_teklif' => 'Teklif', 'perm_bilgi' => 'Bilgi', 'perm_fiyat' => 'Fiyat'])
                        ->filter(fn ($etiket, $alan) => $k->$alan)
                        ->implode(', ') ?: '—' }}
                  @endif
                </td>
                <td>
                  <div class="table__row-actions">
                    <a href="#" class="btn btn--sm" data-modal-open="kullanici-duzenle-{{ $k->id }}"><x-icon name="edit" />Düzenle</a>
                    <form
                      method="post"
                      action="{{ route('admin.users.destroy', $k) }}"
                      data-confirm="&quot;{{ $k->email }}&quot; hesabı kalıcı olarak silinecek."
                      data-confirm-title="Kullanıcıyı sil"
                      data-confirm-variant="danger"
                      data-confirm-verify="{{ $k->email }}"
                    >
                      @csrf
                      <button type="submit" class="btn btn--sm btn--danger"><x-icon name="trash" />Sil</button>
                    </form>
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    {{--
      Modallar bilerek `.page__inner`in İÇİNDE (son çocuk olarak) duruyor:
      `navigation.js` yenilemesiz geçişte SADECE `.page__inner` öğesinin
      kendisini değiştiriyor, dışındaki kardeşler fetch edilen belgeden hiç
      çıkarılmıyor. Modal dışarıda kalsaydı bu sayfaya yenilemesiz
      geçişle varıldığında "Kullanıcı ekle" gibi düğmeler tıklanır ama
      modal DOM'da hiç bulunmadığı için hiçbir şey açılmazdı.
      bkz. modal-body-inner.css.
    --}}
    @foreach ($kullanicilar as $k)
<div
  class="modal"
  id="kullanici-duzenle-{{ $k->id }}"
  data-modal
  @unless ($duzenleAcik[$k->id]) hidden @endunless
  @if ($duzenleAcik[$k->id]) data-modal-visible data-modal-close-url="{{ route('admin.users.index') }}" @endif
  role="dialog" aria-modal="true" aria-labelledby="kullanici-duzenle-{{ $k->id }}-baslik"
>
  <div class="modal__box modal__box--wide">
    <header class="modal__header">
      <h2 class="modal__title" id="kullanici-duzenle-{{ $k->id }}-baslik">{{ $k->name }} — düzenle</h2>
      <button type="button" class="modal__close" data-modal-close aria-label="Kapat">×</button>
    </header>

    <form method="post" action="{{ route('admin.users.update', $k) }}">
      @csrf
      <input type="hidden" name="_form" value="duzenle-{{ $k->id }}">
      <div class="modal__body">
        @if ($duzenleAcik[$k->id] && $errors->any())
        <div class="alert alert--danger modal__alert">
          <ul class="alert__list">
            @foreach ($errors->all() as $hataMesaji)
            <li>{{ $hataMesaji }}</li>
            @endforeach
          </ul>
        </div>
        @endif

        <div class="field">
          <label class="field__label" for="ad-{{ $k->id }}">Ad soyad</label>
          <input type="text" class="input" id="ad-{{ $k->id }}" name="ad"
                 value="{{ $duzenleAcik[$k->id] ? old('ad') : $k->name }}" required
                 aria-invalid="{{ $duzenleAcik[$k->id] && $errors->has('ad') ? 'true' : 'false' }}">
        </div>

        <div class="field">
          <label class="field__label" for="eposta-{{ $k->id }}">E-posta <span class="text-muted">(giriş anahtarı)</span></label>
          <input type="email" class="input input--mono" id="eposta-{{ $k->id }}" name="eposta" required
                 value="{{ $duzenleAcik[$k->id] ? old('eposta') : $k->email }}" placeholder="ad.soyad@ornek.test"
                 aria-invalid="{{ $duzenleAcik[$k->id] && $errors->has('eposta') ? 'true' : 'false' }}">
        </div>

        <div class="field-row">
          <div class="field">
            <label class="field__label" for="yeni-sifre-{{ $k->id }}">Yeni şifre</label>
            <input type="password" class="input" id="yeni-sifre-{{ $k->id }}" name="yeni_sifre" placeholder="değişmesin (boş bırak)" aria-invalid="{{ $duzenleAcik[$k->id] && $errors->has('yeni_sifre') ? 'true' : 'false' }}">
          </div>
          <div class="field">
            <label class="field__label" for="rol-{{ $k->id }}">Rol</label>
            {{--
              Roller VERİDEN gelir (merkezdekiyle aynı slug'lar). Seçenekleri
              koda gömmek, yeni bir rol eklendiğinde iki yeri birden
              düzenlemeyi gerektirirdi.
            --}}
            <select class="select" id="rol-{{ $k->id }}" name="rol">
              @foreach ($roller as $rol)
                <option value="{{ $rol->slug }}" {{ ($duzenleAcik[$k->id] ? old('rol') : $k->rol?->slug) === $rol->slug ? 'selected' : '' }}>{{ $rol->label }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <label class="checkbox field--separated">
          <input type="checkbox" name="aktif" value="1" {{ ($duzenleAcik[$k->id] ? old('aktif') : $k->aktif) ? 'checked' : '' }}>
          Hesap aktif
        </label>

        <div class="field field--separated">
          <span class="field__label">Sayfa izinleri <span class="text-muted">(admin rolü zaten her şeye erişir)</span></span>
          <div class="choices">
            <label class="checkbox">
              <input type="checkbox" name="perm_teklif" value="1" {{ ($duzenleAcik[$k->id] ? old('perm_teklif') : $k->perm_teklif) ? 'checked' : '' }}>
              Teklif Oluştur
            </label>
            <label class="checkbox">
              <input type="checkbox" name="perm_bilgi" value="1" {{ ($duzenleAcik[$k->id] ? old('perm_bilgi') : $k->perm_bilgi) ? 'checked' : '' }}>
              Bilgi Bankası
            </label>
            <label class="checkbox">
              <input type="checkbox" name="perm_fiyat" value="1" {{ ($duzenleAcik[$k->id] ? old('perm_fiyat') : $k->perm_fiyat) ? 'checked' : '' }}>
              Fiyat Kalemleri
            </label>
          </div>
        </div>
      </div>
      <footer class="modal__footer">
        <button type="button" class="btn btn--secondary" data-modal-close>Vazgeç</button>
        <button type="submit" class="btn btn--primary">Güncelle</button>
      </footer>
    </form>
  </div>
</div>
@endforeach

<div
  class="modal"
  id="user-add"
  data-modal
  @unless ($buFormEkle) hidden @endunless
  @if ($buFormEkle) data-modal-visible data-modal-close-url="{{ route('admin.users.index') }}" @endif
  role="dialog" aria-modal="true" aria-labelledby="user-add-title"
>
  <div class="modal__box modal__box--wide">
    <header class="modal__header">
      <h2 class="modal__title" id="user-add-title">Kullanıcı ekle</h2>
      <button type="button" class="modal__close" data-modal-close aria-label="Kapat">×</button>
    </header>

    <form method="post" action="{{ route('admin.users.store') }}">
      @csrf
      <input type="hidden" name="_form" value="ekle">
      <div class="modal__body">
        @if ($buFormEkle && $errors->any())
        <div class="alert alert--danger modal__alert">
          <ul class="alert__list">
            @foreach ($errors->all() as $hataMesaji)
            <li>{{ $hataMesaji }}</li>
            @endforeach
          </ul>
        </div>
        @endif

        <div class="field-row">
          <div class="field">
            <label class="field__label" for="new-name">Ad soyad</label>
            <input type="text" class="input" id="new-name" name="ad" value="{{ old('ad') }}" aria-invalid="{{ $errors->has('ad') ? 'true' : 'false' }}" required>
          </div>
          <div class="field">
            <label class="field__label" for="new-password">Şifre</label>
            <input type="password" class="input" id="new-password" name="sifre" aria-invalid="{{ $errors->has('sifre') ? 'true' : 'false' }}" required>
          </div>
        </div>

        {{--
          E-POSTA, MERKEZLE GİRİŞİN ANAHTARIDIR.

          Merkezden gelen kimlik yerel hesapla e-posta üzerinden eşleşiyor
          (bkz. AccountMatcher). E-postası olmayan hesaba merkezle giren
          biri "eşleşen hesap yok" duvarına çarpar.

          Kişinin kendisi değiştiremez: başkasının adresini yazan biri onun
          merkez kimliğiyle bu hesaba girerdi.
        --}}
        <div class="field">
          <label class="field__label" for="new-email">E-posta <span class="text-muted">(giriş anahtarı)</span></label>
          <input type="email" class="input input--mono" id="new-email" name="eposta" value="{{ old('eposta') }}" placeholder="ad.soyad@ornek.test" aria-invalid="{{ $errors->has('eposta') ? 'true' : 'false' }}" required>
          <span class="field__hint">Hem parolayla giriş hem merkezle eşleşme bu adresten geçer; merkezdekiyle aynı olmalı.</span>
        </div>

        <div class="field">
          <label class="field__label" for="new-role">Rol</label>
          <select class="select" id="new-role" name="rol">
            @foreach ($roller as $rol)
              <option value="{{ $rol->slug }}" {{ old('rol', 'muhasebe') === $rol->slug ? 'selected' : '' }}>{{ $rol->label }}</option>
            @endforeach
          </select>
        </div>

        <div class="field field--separated">
          <span class="field__label">Sayfa izinleri <span class="text-muted">(yönetici olmayan roller için geçerli)</span></span>
          <div class="choices">
            <label class="checkbox">
              <input type="checkbox" name="perm_teklif" value="1" {{ $buFormEkle ? (old('perm_teklif') ? 'checked' : '') : 'checked' }}>
              Teklif Oluştur
            </label>
            <label class="checkbox">
              <input type="checkbox" name="perm_bilgi" value="1" {{ $buFormEkle ? (old('perm_bilgi') ? 'checked' : '') : 'checked' }}>
              Bilgi Bankası
            </label>
            <label class="checkbox">
              <input type="checkbox" name="perm_fiyat" value="1" {{ $buFormEkle ? (old('perm_fiyat') ? 'checked' : '') : 'checked' }}>
              Fiyat Kalemleri
            </label>
          </div>
        </div>
      </div>
      <footer class="modal__footer">
        <button type="button" class="btn btn--secondary" data-modal-close>Vazgeç</button>
        <button type="submit" class="btn btn--primary">Kaydet</button>
      </footer>
    </form>
  </div>
</div>
  </div>
</div>
</x-layout.app>
