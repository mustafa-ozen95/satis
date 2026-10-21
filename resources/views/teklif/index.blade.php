<x-layout.app :title="$secili ? Str::limit($secili->ilk_talep, 30) : 'Teklifler'">
<div class="columns" data-acilis="son">
  <div class="column column--wide">
    <div class="column__head column__head--dikey">
      <div class="row row--between">
        <span class="eyebrow">Tekliflerim</span>
        <span class="column__count">{{ $tekliflerim->count() }}</span>
        <div class="column__hint">
          <a href="{{ route('teklif.create') }}" class="btn btn--secondary btn--sm" @if (! $secili) aria-pressed="true" @endif>
            <x-icon name="plus" />Yeni
          </a>
        </div>
      </div>
      <form method="GET" action="{{ route('teklif.create') }}" class="filter teklif-arama">
        <x-icon name="search" />
        <input class="filter__input" type="search" name="q" value="{{ $arama }}" placeholder="Ara…" aria-label="Tekliflerimde ara">
      </form>
    </div>

    @if ($tekliflerim->isNotEmpty())
    {{--
      README kuralı: "Form içine form konmaz." Burada İKİ ayrı form var
      (toplu silme + her satırın kendi tekli silmesi) ve satırlar (link,
      checkbox, sil düğmesi) ikisinin de GÖRSEL olarak İÇİNDE durması
      gerekiyor. Çözüm: hiçbir kontrol DOM'da bir <form>'un içine
      YERLEŞTİRİLMEZ; hepsi `form="kimlik"` özniteliğiyle kendi (gizli,
      gövdede duran) formuna bağlanır. Böylece `kaynak.closest('form')`
      (pencere.js/tasarim.js'in onay ve gönderim mantığı) hep `null` döner
      ve doğru formu `form` özniteliğinden bulur - yanlışlıkla en yakın
      SARMALAYAN formu değil.
    --}}
    <form method="post" action="{{ route('teklif.bulk-destroy') }}" id="teklif-toplu-form" data-bulk-form hidden>
      @csrf
    </form>

    @foreach ($tekliflerim as $teklif)
    <form method="post" action="{{ route('teklif.destroy', $teklif) }}" id="teklif-sil-{{ $teklif->id }}" hidden>
      @csrf
    </form>
    @endforeach

    <div class="bulk teklif-toplu-cubuk">
      <label class="bulk__all">
        <input type="checkbox" data-bulk-all form="teklif-toplu-form" aria-label="Tüm teklifleri seç">
        <span>Tümü</span>
      </label>
      <button
        type="submit"
        form="teklif-toplu-form"
        class="btn btn--sm btn--danger"
        data-bulk-submit
        data-confirm="Seçili teklifler kalıcı olarak silinecek."
        data-confirm-title="Teklifleri sil"
        data-confirm-variant="danger"
        disabled
      >
        <x-icon name="trash" />Sil (<span data-bulk-count>0</span>)
      </button>
    </div>

    <div class="column__body" data-teklif-listesi>
      @include('teklif.partials.satirlar')
    </div>
    @else
    <div class="column__body">
      <div class="empty">
        <p class="empty__text">
          @if ($arama)
            "{{ $arama }}" ile eşleşen bir teklif bulunamadı.
          @else
            Henüz teklif talebiniz yok. Yukarıdaki "Yeni" ile başlayın.
          @endif
        </p>
      </div>
    </div>
    @endif
  </div>

  <div class="column column--fill">
    @if ($secili)
      <div class="column__head">
        <span class="eyebrow" title="{{ $secili->ilk_talep }}">{{ Str::limit($secili->ilk_talep, 60) }}</span>

        @if ($secili->durum === 'bekliyor')
        <span class="status"><span class="dot dot--yellow" aria-hidden="true"></span>Hazırlanıyor</span>
        @elseif ($secili->durum === 'hata')
        <span class="status"><span class="dot dot--red" aria-hidden="true"></span>Hata</span>
        @else
        <span class="status"><span class="dot dot--green" aria-hidden="true"></span>Hazır</span>
        @endif
      </div>

      <div
        class="column__body column__body--padded"
        id="mesaj-akisi"
        data-teklif-durum="{{ $secili->durum }}"
        data-conversation-id="{{ $secili->id }}"
      >
        @foreach ($secili->messages as $mesaj)
        <div class="mesaj mesaj--{{ $mesaj->role }}">
          <div class="mesaj__rol">
            {{ $mesaj->role === 'user' ? 'Siz' : 'Teklif AI' }}
            @if ($mesaj->role === 'assistant')
            <button type="button" class="btn btn--ghost btn--icon btn--sm" data-kopyala-metin title="Panoya kopyala">
              <x-icon name="copy" />
            </button>
            @endif
          </div>
          <div class="mesaj__icerik" data-kopya-kaynak>{{ $mesaj->content }}</div>
        </div>
        @endforeach

        @if ($secili->durum === 'bekliyor')
        {{--
          Bekleme göstergesi: animasyon yerine düz metin; ortak şablonun
          bileşenleri dışında bir görsel dil kullanılmaz.
        --}}
        <div class="mesaj mesaj--assistant mesaj--bekleniyor" id="bekleme-modulu">
          <span id="durum-metni">Fiyat motoru ve bilgi bankası taranıyor, AI yanıtı bekleniyor…</span>
        </div>
        @elseif ($secili->durum === 'hata')
        <div class="alert alert--danger">
          Bir hata oluştu: {{ $secili->hata_mesaji ?? 'bilinmeyen hata' }} — tekrar deneyin.
        </div>
        @endif
      </div>

      <form method="post" action="{{ route('teklif.reply', $secili) }}" class="mesaj-kutusu">
        @csrf
        {{--
          ENTER GÖNDERİR, SHIFT+ENTER ALT SATIR (bkz. js/components/enter-gonder.js).
          İpucu yer kaplamasın diye placeholder'da duruyor.
        --}}
        <textarea
          name="mesaj"
          class="textarea"
          data-enter-gonder
          placeholder="Ek talep veya soru yazın…  (Enter gönderir, Shift+Enter alt satır)"
          required
          @if ($secili->durum === 'bekliyor') disabled @endif
        ></textarea>
        <button type="submit" class="btn btn--primary" @if ($secili->durum === 'bekliyor') disabled @endif>
          Gönder <x-icon name="send" />
        </button>
      </form>

    @else
      <div class="column__head">
        <span class="eyebrow">Yeni Teklif</span>
      </div>

      <div class="column__body column__body--padded">
        <div class="yeni-teklif">
          <div class="figures figures--tight">
            <div class="figure">
              <div class="figure__value">{{ $istatistik['fiyat_sayisi'] }}</div>
              <div class="figure__label">Fiyat Kalemi</div>
            </div>
            <div class="figure">
              <div class="figure__value">{{ $istatistik['bilgi_sayisi'] }}</div>
              <div class="figure__label">Bilgi Bankası</div>
            </div>
            <div class="figure">
              <div class="figure__value">{{ $istatistik['kullanici_sayisi'] }}</div>
              <div class="figure__label">Kullanıcı</div>
            </div>
          </div>

          @error('talep')
          <div class="alert alert--danger">{{ $message }}</div>
          @enderror

          <form method="post" action="{{ route('teklif.store') }}">
            @csrf
            <div class="field">
              <label class="field__label" for="talep">Müşteri talebi</label>
              <textarea
                class="textarea"
                id="talep"
                name="talep"
                rows="6"
                placeholder="Örn: Müşteri iki sunucu ve bir yıllık bakım istiyor."
                required
                autofocus
              >{{ old('talep') }}</textarea>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn--primary">Teklif Üret <x-icon name="send" /></button>
            </div>
          </form>
        </div>
      </div>
    @endif
  </div>
</div>
</x-layout.app>
