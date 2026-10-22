import { on } from '../lib/dom.js';

/**
 * Kategori seçimi: listeden seç ya da yenisini yaz.
 *
 * Serbest metin kutusu her yazımda yeni bir kategori doğuruyordu; liste
 * asıl yol oldu, yeni kategori istisna. Kutu KAPALIYKEN `disabled`:
 * gönderilmeyen bir alan doğrulamayı kirletmez ve sunucu "hangisi geçerli"
 * diye sormak zorunda kalmaz.
 */
const FRESH = '__yeni';

function apply(selection, shouldFocus = false) {
    const field = selection.closest('[data-category]');
    const box = field?.querySelector('[data-category-new]');

    if (!box) {
        return;
    }

    const fresh = selection.value === FRESH;

    box.hidden = !fresh;
    box.disabled = !fresh;

    if (fresh && shouldFocus) {
        box.focus();
    }

    if (!fresh) {
        box.value = '';
    }
}

/** Her çizimde: ekrandaki seçimlerin kutusu doğru durumda başlasın. */
export function prepare() {
    document.querySelectorAll('[data-category-select]').forEach((selection) => apply(selection));
}

/** Bir kez: dinleyici gövdede durur, yeniden çizilen alanlar da kapsanır. */
export function init() {
    on('change', '[data-category-select]', (event, selection) => apply(selection, true));
}
