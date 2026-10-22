@props(['aktif' => 'bilgi'])

{{--
    Teklif üretiminin beslendiği iki kaynak: bilgi bankası ve fiyat listesi.

    Üst menüde ayrı ayrı duruyorlardı; menü telefonda ekrana sığmıyordu ve
    ikisi zaten aynı işin iki yarısı.
--}}
<x-ui.tabs :items="[
    [
        'label' => 'Bilgi Bankası',
        'url' => route('knowledge-base.index'),
        'active' => $aktif === 'bilgi',
    ],
    [
        'label' => 'Fiyat Kalemleri',
        'url' => route('prices.index'),
        'active' => $aktif === 'fiyat',
    ],
]" />
