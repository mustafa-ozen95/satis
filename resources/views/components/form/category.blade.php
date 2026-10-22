@props([
    'name' => 'kategori',
    'id' => 'kategori',
    'secenekler' => [],
    'deger' => null,
    'label' => 'Kategori',
])

{{--
    KATEGORİ SEÇİLİR, YAZILMAZ.

    Serbest metin kutusuydu ve her yazımda yeni bir kategori doğuyordu
    ("IP / Network" ile "IP/Network" ayrı iki kategori). Kategori bir
    ETİKETTİR: var olanı seçmek asıl iş, yenisini açmak istisnadır.

    Yeni kategori yolu KAPANMIYOR: listenin sonundaki seçenek metin
    kutusunu açıyor. Kutu kapalıyken `disabled` durur; gönderilmez ve
    doğrulamayı kirletmez.
--}}
<div class="field" data-category>
    <label class="field__label" for="{{ $id }}">{{ $label }}</label>

    <select class="input" id="{{ $id }}" name="{{ $name }}" data-category-select required
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}">
        <option value="" @selected(blank($deger))>Seçin…</option>

        @foreach ($secenekler as $secenek)
            <option value="{{ $secenek }}" @selected($deger === $secenek)>{{ $secenek }}</option>
        @endforeach

        {{-- Kayıtlı kategori listeden silinmişse yine de görünsün. --}}
        @if (filled($deger) && ! in_array($deger, (array) $secenekler, true))
            <option value="{{ $deger }}" selected>{{ $deger }}</option>
        @endif

        <option value="__yeni">+ Yeni kategori…</option>
    </select>

    <input type="text" class="input" name="{{ $name }}_yeni" data-category-new
           placeholder="Yeni kategori adı" maxlength="255" disabled hidden
           aria-label="Yeni kategori adı">

    @error($name)
        <span class="field__error">{{ $message }}</span>
    @enderror
    @error($name.'_yeni')
        <span class="field__error">{{ $message }}</span>
    @enderror
</div>
