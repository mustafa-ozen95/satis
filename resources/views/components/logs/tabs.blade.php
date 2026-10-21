@props(['aktif' => 'denetim'])

{{--
    Kayıt ekranlarının şeridi.

    Üçü AYRI SORULARDIR ve ayrı ekranlardır: denetim kaydı "kim ne yaptı",
    hata kaydı "ne patladı", istatistik "ne kadar". Ama üçü de aynı
    kaynaktan — panelde biriken kayıtlardan — okunuyor; sayfa başlığına
    düğme koymak onları ilgisiz sayfalar gibi gösteriyordu.

    İstatistikler üst menüden buraya alındı: menü telefonda ekrana
    sığmıyordu ve istatistik kendi başına bir bölüm
    olacak kadar büyük değil.
--}}
<x-ui.tabs :items="[
    [
        'label' => 'Denetim kayıtları',
        'url' => route('admin.kayitlar.denetim'),
        'active' => $aktif === 'denetim',
    ],
    [
        'label' => 'Hata kayıtları',
        'url' => route('admin.kayitlar.hatalar'),
        'active' => $aktif === 'hata',
    ],
    [
        'label' => 'İstatistikler',
        'url' => route('admin.istatistikler.index'),
        'active' => $aktif === 'istatistik',
    ],
]" />
