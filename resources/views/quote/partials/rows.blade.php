{{--
    Teklif listesinin SATIRLARI — hem ilk çizimde hem "Devamını gör"
    isteğinde aynı dosya kullanılır.

    Ayrı bir parça olması şart: iki yerde ayrı yazılsaydı biri değişince
    öteki sessizce eski kalır ve liste, kaydırıldıkça biçim değiştirirdi.
--}}
@foreach ($tekliflerim as $teklif)
      <div class="quote-row">
        <input type="checkbox" data-bulk-item name="secili_id[]" form="quote-bulk-form" value="{{ $teklif->id }}" aria-label="&quot;{{ $teklif->ilk_talep }}&quot; seç">
        {{--
          SATIR İKİ SATIRDIR.
          Başlık, tarih ve durum tek satırda yarışırken başlık üç harfe
          düşüyordu: sütun dar, tarih ve onay kutusu payını alınca
          geriye neredeyse yer kalmıyordu. Başlık artık kendi
          satırında tam genişlikte; tarih ve durum altına, küçük ve sessiz.
        --}}
        <a href="{{ route('quote.show', $teklif) }}"
           class="row-item quote-link @if ($teklif->isUnread()) quote-link--unread @endif"
           @if ($secili && $secili->id === $teklif->id) aria-selected="true" @endif>
          <span class="quote-title">
            <span
              class="dot dot--{{ $teklif->durum === 'bekliyor' ? 'yellow' : ($teklif->durum === 'hata' ? 'red' : 'green') }} @if ($teklif->durum === 'bekliyor') dot--running @endif"
              aria-hidden="true"
            ></span>
            {{--
              BAŞLIK PHP'DE KIRPILMAZ.
              `Str::limit(…, 60)` CSS'ten önce ikinci bir kesme yapıyordu:
              sütun ne kadar geniş olursa olsun başlık 60 karakterde bitip
              sağında boşluk bırakıyordu.
              Kırpma tek yerde, çizim sırasında olsun: satıra ne sığıyorsa o
              görünür, geri kalanı üç noktaya iner.
            --}}
            <span class="row-item__label">{{ $teklif->ilk_talep }}</span>
          </span>

          <span class="quote-footer">
            @if ($teklif->durum === 'bekliyor')
              <span class="quote-status quote-status--running">Hazırlanıyor…</span>
            @elseif ($teklif->durum === 'hata')
              <span class="quote-status quote-status--error">Hata</span>
            @elseif ($teklif->isUnread())
              <span class="quote-status quote-status--new">Yeni yanıt</span>
            @endif

            {{--
              YÖNETİCİ KİMİN İSTEDİĞİNİ GÖRÜR. Liste kişiye özeldir; yönetici
              hepsini görüyorsa satırın sahibi de yazmalı, yoksa "kim ne
              istemiş" sorusu yanıtsız kalır.
            --}}
            @if (auth()->user()?->isAdmin() && $teklif->user_id !== auth()->id())
              <span class="quote-status">{{ Str::before($teklif->user?->name ?? '?', ' ') }}</span>
            @endif

            <span class="row-item__meta">{{ $teklif->created_at->format('d.m H:i') }}</span>
          </span>
        </a>
        <button
          type="submit"
          form="quote-delete-{{ $teklif->id }}"
          class="btn btn--icon btn--sm"
          title="Sil"
          data-confirm="&quot;{{ Str::limit($teklif->ilk_talep, 60) }}&quot; kalıcı olarak silinecek."
          data-confirm-title="Teklifi sil"
          data-confirm-variant="danger"
        >
          <x-icon name="trash" />
        </button>
  </div>

@endforeach

@if ($devamiVar ?? false)
    {{--
        DEVAMI VAR. Liste zamanla binlerce satıra çıkabilir; hepsini tek
        seferde çizmek telefonda sayfayı kilitler. Sonraki on beş,
        buradaki düğmeyle gelir (bkz. js/components/quote-more.js).

        `data-before` son satırın kimliğidir: sayfa numarası değil İMLEÇ.
        Numarayla sayfalasaydık, aradaki bir teklif silindiğinde sonraki
        sayfa bir satır atlardı.
    --}}
    <div class="quote-more" data-quote-more>
        <button type="button" class="btn btn--ghost btn--block btn--sm"
                data-quote-more-button
                data-url="{{ route('quote.more') }}"
                data-before="{{ $tekliflerim->last()->id }}">
            Devamını gör
        </button>
    </div>
@endif
