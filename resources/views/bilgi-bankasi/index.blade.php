<x-layout.app title="Bilgi Bankası">
<div class="page">
  <div class="page__inner">
    <header class="page-head">
      <div class="page-head__text">
        <h1>Kaynaklar</h1>
        <p class="page-head__subtitle">Teklif AI'ın referans aldığı kurallar ve dokümanlar burada tutulur.</p>
      </div>
      <div class="page-head__actions">
        <a href="#" class="btn btn--primary btn--sm" data-modal-open="bilgi-ekle"><x-icon name="plus" />Bilgi ekle</a>
      </div>
    </header>

    <x-kaynak.tabs aktif="bilgi" />

    @forelse ($kayitlar as $k)
    <section class="panel">
      <header class="panel__head">
        <div>
          <h2 class="panel__title">{{ $k['title'] }}</h2>
        </div>
        <div class="panel__actions">
          <span class="badge">{{ $k['category'] }}</span>
          <span class="kod">{{ $k['source'] }}</span>
          <a href="#" class="btn btn--sm" data-modal-open="bilgi-duzenle-{{ $k['id'] }}"><x-icon name="edit" />Düzenle</a>
          <form
            method="post"
            action="{{ route('bilgi-bankasi.destroy', $k['id']) }}"
            data-confirm="&quot;{{ $k['title'] }}&quot; kalıcı olarak silinecek."
            data-confirm-title="Bilgi kaydını sil"
            data-confirm-variant="danger"
            data-confirm-verify="{{ $k['title'] }}"
          >
            @csrf
            <button type="submit" class="btn btn--danger btn--sm"><x-icon name="trash" />Sil</button>
          </form>
        </div>
      </header>
      <div class="panel__body">
        <p>{{ $k['text'] }}</p>
      </div>
    </section>

    @php $buFormDuzenle = old('_form') === "duzenle-{$k['id']}"; @endphp
    <div
      class="modal"
      id="bilgi-duzenle-{{ $k['id'] }}"
      data-modal
      @unless ($buFormDuzenle) hidden @endunless
      @if ($buFormDuzenle) data-modal-acik data-modal-kapanis="{{ route('bilgi-bankasi.index') }}" @endif
      role="dialog" aria-modal="true" aria-labelledby="bilgi-duzenle-{{ $k['id'] }}-baslik"
    >
      <div class="modal__box modal__box--wide">
        <header class="modal__header">
          <h2 class="modal__title" id="bilgi-duzenle-{{ $k['id'] }}-baslik">Bilgiyi düzenle</h2>
          <button type="button" class="modal__close" data-modal-close aria-label="Kapat">×</button>
        </header>

        <form method="post" action="{{ route('bilgi-bankasi.update', $k['id']) }}">
          @csrf
          <input type="hidden" name="_form" value="duzenle-{{ $k['id'] }}">
          <div class="modal__body">
            @if ($buFormDuzenle && $errors->any())
            <div class="alert alert--danger modal__alert">
              <ul class="alert__list">
                @foreach ($errors->all() as $hataMesaji)
                <li>{{ $hataMesaji }}</li>
                @endforeach
              </ul>
            </div>
            @endif

            <div class="field">
              <label class="field__label" for="baslik-{{ $k['id'] }}">Başlık</label>
              <input type="text" class="input" id="baslik-{{ $k['id'] }}" name="baslik" value="{{ $buFormDuzenle ? old('baslik') : $k['title'] }}" aria-invalid="{{ $buFormDuzenle && $errors->has('baslik') ? 'true' : 'false' }}" required>
            </div>
            <x-form.kategori :id="'kategori-'.$k['id']"
                             :secenekler="$kategoriler"
                             :deger="$buFormDuzenle ? old('kategori') : $k['category']" />
            <div class="field">
              <label class="field__label" for="bilgi-{{ $k['id'] }}">Bilgi</label>
              <textarea class="textarea" id="bilgi-{{ $k['id'] }}" name="bilgi" aria-invalid="{{ $buFormDuzenle && $errors->has('bilgi') ? 'true' : 'false' }}" required>{{ $buFormDuzenle ? old('bilgi') : $k['text'] }}</textarea>
            </div>
          </div>
          <footer class="modal__footer">
            <button type="button" class="btn btn--secondary" data-modal-close>Vazgeç</button>
            <button type="submit" class="btn btn--primary">Kaydet</button>
          </footer>
        </form>
      </div>
    </div>
    @empty
    <div class="empty">
      <p class="empty__text">Henüz bilgi eklenmedi.</p>
    </div>
    @endforelse

    {{-- "ekle" modalı da bilerek `.page__inner`in İÇİNDE - bkz. modal-govde-ici.css. --}}
    @php $buFormEkle = old('_form') === 'ekle'; @endphp
<div
  class="modal"
  id="bilgi-ekle"
  data-modal
  @unless ($buFormEkle) hidden @endunless
  @if ($buFormEkle) data-modal-acik data-modal-kapanis="{{ route('bilgi-bankasi.index') }}" @endif
  role="dialog" aria-modal="true" aria-labelledby="bilgi-ekle-baslik"
>
  <div class="modal__box modal__box--wide">
    <header class="modal__header">
      <h2 class="modal__title" id="bilgi-ekle-baslik">Bilgi ekle</h2>
      <button type="button" class="modal__close" data-modal-close aria-label="Kapat">×</button>
    </header>

    <form method="post" action="{{ route('bilgi-bankasi.store') }}">
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

        <div class="field">
          <label class="field__label" for="yeni-baslik">Başlık</label>
          <input type="text" class="input" id="yeni-baslik" name="baslik" value="{{ old('baslik') }}" aria-invalid="{{ $errors->has('baslik') ? 'true' : 'false' }}" required>
        </div>
        <x-form.kategori id="yeni-kategori" :secenekler="$kategoriler" :deger="old('kategori')" />
        <div class="field">
          <label class="field__label" for="yeni-bilgi">Bilgi</label>
          <textarea class="textarea" id="yeni-bilgi" name="bilgi" aria-invalid="{{ $errors->has('bilgi') ? 'true' : 'false' }}" required>{{ old('bilgi') }}</textarea>
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
