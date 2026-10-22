<x-layout.app title="Fiyat Kalemleri">
<div class="page">
  <div class="page__inner page__inner--wide">
    <header class="page-head">
      <div class="page-head__text">
        <h1>Kaynaklar</h1>
        <p class="page-head__subtitle">Teklif AI fiyatları buradan okur, asla kendi hesaplamaz.</p>
      </div>
      <div class="page-head__actions">
        <a href="#" class="btn btn--primary btn--sm" data-modal-open="price-add"><x-icon name="plus" />Fiyat kalemi ekle</a>
      </div>
    </header>

    <x-source.tabs aktif="fiyat" />

    <section class="panel">
      <div class="panel__body panel__body--flush">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Kategori</th>
                <th>Ad</th>
                <th>Fiyat</th>
                <th>Birim</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @forelse ($fiyatlar as $f)
              <tr>
                {{--
                  HÜCREYE ÇİFT TIKLAYINCA YERİNDE DÜZENLENİR (inline-edit.js).

                  `ham` düzenlenecek değer, `gosterim` ekranda duran hâli;
                  ikisi ayrı çünkü fiyat "1500" olarak düzenlenip
                  "1.500 TL" olarak görünüyor. Kaydettikten sonra hücre ne
                  yazacağını SUNUCUDAN öğrenir, biçimi kendi uydurmaz.
                --}}
                {{-- Kategori SEÇİLİR: hücre seçenekleri kendisi taşıyor (bkz. inline-edit.js). --}}
                <td data-label="Kategori" data-field="category" data-type="select"
                    data-options="{{ json_encode($kategoriler, JSON_UNESCAPED_UNICODE) }}"
                    data-url="{{ route('prices.field', $f) }}"
                    data-raw="{{ $f->category }}" data-display="{{ $f->category }}"
                    title="Düzenlemek için çift tıklayın">{{ $f->category }}</td>

                <td data-label="Ad" class="row-title" data-field="name" data-type="text"
                    data-url="{{ route('prices.field', $f) }}"
                    data-raw="{{ $f->name }}" data-display="{{ $f->name }}"
                    title="Düzenlemek için çift tıklayın">{{ $f->name }}</td>

                <td data-label="Fiyat" class="table__cell--mono" data-field="price" data-type="number"
                    data-url="{{ route('prices.field', $f) }}"
                    data-raw="{{ $f->rawPrice() }}"
                    data-display="{{ $f->displayPrice() }}"
                    title="Düzenlemek için çift tıklayın">{{ $f->displayPrice() }}</td>

                <td data-label="Birim" data-field="unit" data-type="text"
                    data-url="{{ route('prices.field', $f) }}"
                    data-raw="{{ $f->unit }}" data-display="{{ $f->unit }}"
                    title="Düzenlemek için çift tıklayın">{{ $f->unit }}</td>
                <td>
                  <div class="table__row-actions">
                    <a href="#" class="btn btn--sm btn--icon" data-modal-open="fiyat-duzenle-{{ $f->id }}" title="Düzenle"><x-icon name="edit" /></a>
                    <form
                      method="post"
                      action="{{ route('prices.destroy', $f) }}"
                      data-confirm="&quot;{{ $f->name }}&quot; kalıcı olarak silinecek."
                      data-confirm-title="Fiyat kalemini sil"
                      data-confirm-variant="danger"
                      data-confirm-verify="{{ $f->name }}"
                    >
                      @csrf
                      <button type="submit" class="btn btn--sm btn--icon" title="Sil"><x-icon name="trash" /></button>
                    </form>
                  </div>
                </td>
              </tr>
              @empty
              <tr><td colspan="5"><div class="empty"><p class="empty__text">Henüz fiyat kalemi eklenmedi.</p></div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </section>

    {{-- Modallar bilerek `.page__inner`in İÇİNDE duruyor - bkz. modal-body-inner.css. --}}
    @foreach ($fiyatlar as $f)
<div
  class="modal"
  id="fiyat-duzenle-{{ $f->id }}"
  data-modal
  @unless ($duzenleAcik[$f->id]) hidden @endunless
  @if ($duzenleAcik[$f->id]) data-modal-visible data-modal-close-url="{{ route('prices.index') }}" @endif
  role="dialog" aria-modal="true" aria-labelledby="fiyat-duzenle-{{ $f->id }}-baslik"
>
  <div class="modal__box modal__box--wide">
    <header class="modal__header">
      <h2 class="modal__title" id="fiyat-duzenle-{{ $f->id }}-baslik">Fiyat kalemini düzenle</h2>
      <button type="button" class="modal__close" data-modal-close aria-label="Kapat">×</button>
    </header>

    <form method="post" action="{{ route('prices.update', $f) }}">
      @csrf
      <input type="hidden" name="_form" value="duzenle-{{ $f->id }}">
      <div class="modal__body">
        @if ($duzenleAcik[$f->id] && $errors->any())
        <div class="alert alert--danger modal__alert">
          <ul class="alert__list">
            @foreach ($errors->all() as $hataMesaji)
            <li>{{ $hataMesaji }}</li>
            @endforeach
          </ul>
        </div>
        @endif

        <div class="field-row">
          <x-form.category name="category"
                           :id="'category-'.$f->id"
                           :secenekler="$kategoriler"
                           :deger="$duzenleAcik[$f->id] ? old('category') : $f->category" />
          <div class="field">
            <label class="field__label" for="name-{{ $f->id }}">Ad</label>
            <input type="text" class="input" id="name-{{ $f->id }}" name="name" value="{{ $duzenleAcik[$f->id] ? old('name') : $f->name }}" aria-invalid="{{ $duzenleAcik[$f->id] && $errors->has('name') ? 'true' : 'false' }}" required>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label class="field__label" for="price-{{ $f->id }}">Fiyat</label>
            <input type="number" step="0.01" class="input input--mono" id="price-{{ $f->id }}" name="price" value="{{ $duzenleAcik[$f->id] ? old('price') : $f->price }}" aria-invalid="{{ $duzenleAcik[$f->id] && $errors->has('price') ? 'true' : 'false' }}" required>
          </div>
          <div class="field">
            <label class="field__label" for="unit-{{ $f->id }}">Birim</label>
            <input type="text" class="input" id="unit-{{ $f->id }}" name="unit" value="{{ $duzenleAcik[$f->id] ? old('unit') : $f->unit }}">
          </div>
        </div>
        <label class="checkbox field--separated">
          <input type="checkbox" name="kdv_dahil" value="1" {{ ($duzenleAcik[$f->id] ? old('kdv_dahil') : $f->kdv_dahil) ? 'checked' : '' }}>
          KDV dahil
        </label>
        <div class="field">
          <label class="field__label" for="notlar-{{ $f->id }}">Not</label>
          <input type="text" class="input" id="notlar-{{ $f->id }}" name="notlar" value="{{ $duzenleAcik[$f->id] ? old('notlar') : $f->notlar }}">
        </div>
      </div>
      <footer class="modal__footer">
        <button type="button" class="btn btn--secondary" data-modal-close>Vazgeç</button>
        <button type="submit" class="btn btn--primary">Kaydet</button>
      </footer>
    </form>
  </div>
</div>
@endforeach

<div
  class="modal"
  id="price-add"
  data-modal
  @unless ($buFormEkle) hidden @endunless
  @if ($buFormEkle) data-modal-visible data-modal-close-url="{{ route('prices.index') }}" @endif
  role="dialog" aria-modal="true" aria-labelledby="price-add-title"
>
  <div class="modal__box modal__box--wide">
    <header class="modal__header">
      <h2 class="modal__title" id="price-add-title">Fiyat kalemi ekle</h2>
      <button type="button" class="modal__close" data-modal-close aria-label="Kapat">×</button>
    </header>

    <form method="post" action="{{ route('prices.store') }}">
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
          <x-form.category name="category" id="yeni-category"
                           :secenekler="$kategoriler" :deger="old('category')" />
          <div class="field">
            <label class="field__label" for="yeni-name">Ad</label>
            <input type="text" class="input" id="yeni-name" name="name" value="{{ old('name') }}" placeholder="örn. HP ProLiant DL160-Gen9" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" required>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label class="field__label" for="yeni-price">Fiyat</label>
            <input type="number" step="0.01" class="input input--mono" id="yeni-price" name="price" value="{{ old('price') }}" aria-invalid="{{ $errors->has('price') ? 'true' : 'false' }}" required>
          </div>
          <div class="field">
            <label class="field__label" for="yeni-unit">Birim</label>
            <input type="text" class="input" id="yeni-unit" name="unit" value="{{ old('unit', 'ay') }}">
          </div>
        </div>
        <label class="checkbox field--separated">
          <input type="checkbox" name="kdv_dahil" value="1" {{ old('kdv_dahil') ? 'checked' : '' }}>
          KDV dahil
        </label>
        <div class="field">
          <label class="field__label" for="new-notes">Not (opsiyonel)</label>
          <input type="text" class="input" id="new-notes" name="notlar" value="{{ old('notlar') }}">
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
