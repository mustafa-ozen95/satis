<x-layout.app title="Bilgi Bankası">
<div class="page">
  <div class="page__inner">
    <header class="page-head">
      <div class="page-head__text">
        <h1>Kaynaklar</h1>
        <p class="page-head__subtitle">Teklif AI'ın referans aldığı kurallar ve dokümanlar burada tutulur.</p>
      </div>
      <div class="page-head__actions">
        <a href="#" class="btn btn--primary btn--sm" data-modal-open="knowledge-add"><x-icon name="plus" />Bilgi ekle</a>
      </div>
    </header>

    <x-source.tabs aktif="bilgi" />

    @forelse ($kayitlar as $k)
    <section class="panel">
      <header class="panel__head">
        <div>
          <h2 class="panel__title">{{ $k['title'] }}</h2>
        </div>
        <div class="panel__actions">
          <span class="badge">{{ $k['category'] }}</span>
          <span class="code">{{ $k['source'] }}</span>
          <a href="#" class="btn btn--sm" data-modal-open="bilgi-duzenle-{{ $k['id'] }}"><x-icon name="edit" />Düzenle</a>
          <form
            method="post"
            action="{{ route('knowledge-base.destroy', $k['id']) }}"
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

    <div
      class="modal"
      id="bilgi-duzenle-{{ $k['id'] }}"
      data-modal
      @unless ($duzenleAcik[$k['id']]) hidden @endunless
      @if ($duzenleAcik[$k['id']]) data-modal-visible data-modal-close-url="{{ route('knowledge-base.index') }}" @endif
      role="dialog" aria-modal="true" aria-labelledby="bilgi-duzenle-{{ $k['id'] }}-baslik"
    >
      <div class="modal__box modal__box--wide">
        <header class="modal__header">
          <h2 class="modal__title" id="bilgi-duzenle-{{ $k['id'] }}-baslik">Bilgiyi düzenle</h2>
          <button type="button" class="modal__close" data-modal-close aria-label="Kapat">×</button>
        </header>

        <form method="post" action="{{ route('knowledge-base.update', $k['id']) }}">
          @csrf
          <input type="hidden" name="_form" value="duzenle-{{ $k['id'] }}">
          <div class="modal__body">
            @if ($duzenleAcik[$k['id']] && $errors->any())
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
              <input type="text" class="input" id="baslik-{{ $k['id'] }}" name="baslik" value="{{ $duzenleAcik[$k['id']] ? old('baslik') : $k['title'] }}" aria-invalid="{{ $duzenleAcik[$k['id']] && $errors->has('baslik') ? 'true' : 'false' }}" required>
            </div>
            <x-form.category :id="'category-'.$k['id']"
                             :secenekler="$kategoriler"
                             :deger="$duzenleAcik[$k['id']] ? old('kategori') : $k['category']" />
            <div class="field">
              <label class="field__label" for="bilgi-{{ $k['id'] }}">Bilgi</label>
              <textarea class="textarea" id="bilgi-{{ $k['id'] }}" name="bilgi" aria-invalid="{{ $duzenleAcik[$k['id']] && $errors->has('bilgi') ? 'true' : 'false' }}" required>{{ $duzenleAcik[$k['id']] ? old('bilgi') : $k['text'] }}</textarea>
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

    {{-- "ekle" modalı da bilerek `.page__inner`in İÇİNDE - bkz. modal-body-inner.css. --}}
<div
  class="modal"
  id="knowledge-add"
  data-modal
  @unless ($buFormEkle) hidden @endunless
  @if ($buFormEkle) data-modal-visible data-modal-close-url="{{ route('knowledge-base.index') }}" @endif
  role="dialog" aria-modal="true" aria-labelledby="knowledge-add-title"
>
  <div class="modal__box modal__box--wide">
    <header class="modal__header">
      <h2 class="modal__title" id="knowledge-add-title">Bilgi ekle</h2>
      <button type="button" class="modal__close" data-modal-close aria-label="Kapat">×</button>
    </header>

    <form method="post" action="{{ route('knowledge-base.store') }}">
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
          <label class="field__label" for="new-title">Başlık</label>
          <input type="text" class="input" id="new-title" name="baslik" value="{{ old('baslik') }}" aria-invalid="{{ $errors->has('baslik') ? 'true' : 'false' }}" required>
        </div>
        <x-form.category id="new-category" :secenekler="$kategoriler" :deger="old('kategori')" />
        <div class="field">
          <label class="field__label" for="new-info">Bilgi</label>
          <textarea class="textarea" id="new-info" name="bilgi" aria-invalid="{{ $errors->has('bilgi') ? 'true' : 'false' }}" required>{{ old('bilgi') }}</textarea>
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
