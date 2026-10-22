<x-layout.app title="İstatistikler">
<div class="page">
  <div class="page__inner">
    <header class="page-head">
      <div class="page-head__text">
        <h1>Kayıtlar</h1>
        <p class="page-head__subtitle">Sistemin genel durumuna dair özet sayılar.</p>
      </div>
    </header>

    <x-logs.tabs aktif="istatistik" />

    <div class="figures">
      <div class="figure">
        <div class="figure__value">{{ $istatistik['kullanici_sayisi'] }}</div>
        <div class="figure__label">Toplam kullanıcı ({{ $istatistik['aktif_kullanici_sayisi'] }} aktif)</div>
      </div>
      <div class="figure">
        <div class="figure__value">{{ $istatistik['fiyat_sayisi'] }}</div>
        <div class="figure__label">Fiyat kalemi</div>
      </div>
      <div class="figure">
        <div class="figure__value">{{ $istatistik['bilgi_sayisi'] ?? '—' }}</div>
        <div class="figure__label">Bilgi bankası kaydı</div>
      </div>
      <div class="figure">
        <div class="figure__value">{{ $istatistik['konusma_sayisi'] }}</div>
        <div class="figure__label">Teklif görüşmesi</div>
      </div>
    </div>

    <section class="panel field--separated">
      <header class="panel__head">
        <div>
          <h2 class="panel__title">Kullanıcı Aktivitesi</h2>
          <p class="text-sm text-muted">Kimin kaç teklif oluşturduğu, fiyat kalemi ve bilgi bankası kaydı eklediği.</p>
        </div>
      </header>
      <div class="panel__body panel__body--flush">
        @if ($kullaniciAktivite->isEmpty())
        <div class="empty">
          <p class="empty__text">Henüz kayıtlı bir aktivite yok.</p>
        </div>
        @else
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Kullanıcı</th>
                <th class="text-right">Teklif oluşturdu</th>
                <th class="text-right">Fiyat kalemi ekledi</th>
                <th class="text-right">Bilgi bankası ekledi</th>
                <th class="text-right">Toplam</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($kullaniciAktivite as $kullanici)
              <tr>
                <td data-label="Kullanıcı" class="row-title">{{ $kullanici->name }}</td>
                <td data-label="Teklif oluşturdu" class="table__cell--mono text-right">{{ $kullanici->teklif_sayisi }}</td>
                <td data-label="Fiyat kalemi ekledi" class="table__cell--mono text-right">{{ $kullanici->fiyat_sayisi }}</td>
                <td data-label="Bilgi bankası ekledi" class="table__cell--mono text-right">{{ $kullanici->bilgi_sayisi }}</td>
                <td data-label="Toplam" class="table__cell--mono text-right">
                  <span class="badge badge--green">{{ $kullanici->teklif_sayisi + $kullanici->fiyat_sayisi + $kullanici->bilgi_sayisi }}</span>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endif
      </div>
    </section>
  </div>
</div>
</x-layout.app>
