{{--
    Teklif listesinin SATIRLARI — hem ilk çizimde hem "Devamını gör"
    isteğinde aynı dosya kullanılır.

    Ayrı bir parça olması şart: iki yerde ayrı yazılsaydı biri değişince
    öteki sessizce eski kalır ve liste, kaydırıldıkça biçim değiştirirdi.
--}}
@foreach ($tekliflerim as $teklif)
      <div class="teklif-satiri">
        <input type="checkbox" data-bulk-item name="secili_id[]" form="teklif-toplu-form" value="{{ $teklif->id }}" aria-label="&quot;{{ $teklif->ilk_talep }}&quot; seç">
        {{--
          SATIR İKİ SATIRDIR.
          Başlık, tarih ve durum tek satırda yarışırken başlık üç harfe
          düşüyordu: sütun dar, tarih ve onay kutusu payını alınca
          geriye neredeyse yer kalmıyordu. Başlık artık kendi
          satırında tam genişlikte; tarih ve durum altına, küçük ve sessiz.
        --}}
        <a href="{{ route('teklif.show', $teklif) }}"
           class="row-item teklif-baglanti @if ($teklif->okunmamisMi()) teklif-baglanti--okunmamis @endif"
           @if ($secili && $secili->id === $teklif->id) aria-selected="true" @endif>
          <span class="teklif-baslik">
            <span
              class="dot dot--{{ $teklif->durum === 'bekliyor' ? 'yellow' : ($teklif->durum === 'hata' ? 'red' : 'green') }} @if ($teklif->durum === 'bekliyor') dot--calisiyor @endif"
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

          <span class="teklif-alt">
            @if ($teklif->durum === 'bekliyor')
              <span class="teklif-durum teklif-durum--calisiyor">Hazırlanıyor…</span>
            @elseif ($teklif->durum === 'hata')
              <span class="teklif-durum teklif-durum--hata">Hata</span>
            @elseif ($teklif->okunmamisMi())
              <span class="teklif-durum teklif-durum--yeni">Yeni yanıt</span>
            @endif

            {{--
              YÖNETİCİ KİMİN İSTEDİĞİNİ GÖRÜR. Liste kişiye özeldir; yönetici
              hepsini görüyorsa satırın sahibi de yazmalı, yoksa "kim ne
              istemiş" sorusu yanıtsız kalır.
            --}}
            @if (auth()->user()?->isAdmin() && $teklif->user_id !== auth()->id())
              <span class="teklif-durum">{{ Str::before($teklif->user?->name ?? '?', ' ') }}</span>
            @endif

            <span class="row-item__meta">{{ $teklif->created_at->format('d.m H:i') }}</span>
          </span>
        </a>
        <button
          type="submit"
          form="teklif-sil-{{ $teklif->id }}"
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
        buradaki düğmeyle gelir (bkz. js/components/teklif-daha.js).

        `data-oncesi` son satırın kimliğidir: sayfa numarası değil İMLEÇ.
        Numarayla sayfalasaydık, aradaki bir teklif silindiğinde sonraki
        sayfa bir satır atlardı.
    --}}
    <div class="teklif-daha" data-teklif-daha>
        <button type="button" class="btn btn--ghost btn--block btn--sm"
                data-teklif-daha-dugme
                data-adres="{{ route('teklif.daha') }}"
                data-oncesi="{{ $tekliflerim->last()->id }}">
            Devamını gör
        </button>
    </div>
@endif
