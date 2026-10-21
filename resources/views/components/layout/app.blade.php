@props(['title' => null])

{{--
  Panelin kabuğu: ortak şablonun düzeni ve sırası; panel şablona uyar.
--}}
<!doctype html>
<html lang="tr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{--
        Tema çerezinin alan adı. Merkez ve paneller ayrı köklerdir; üst alan
        adı verilince seçim hepsinde paylaşılır (bkz. config/tema.php).
    --}}
    <meta name="tema-alan" content="{{ config('tema.cerez_alani') }}">
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}">

    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

    {{--
        YAZI TİPİ ŞABLONUN KAYNAĞINDAN GELİR.

        Şablon neyi nasıl yüklüyorsa aynısı; ayrı bir paketleme yolu
        tarayıcıyı sistem fontuna düşürebilir.
    --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="shell">
    <x-nav.topbar />
    <x-ui.guncelleme-uyarisi />

    {{ $slot }}
</div>

<x-ui.toast-host />
<x-ui.confirm-modal />
</body>
</html>
