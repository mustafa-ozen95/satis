import { on } from '../lib/dom.js';

/**
 * Kategori seçimi: listeden seç ya da yenisini yaz.
 *
 * Serbest metin kutusu her yazımda yeni bir kategori doğuruyordu; liste
 * asıl yol oldu, yeni kategori istisna. Kutu KAPALIYKEN `disabled`:
 * gönderilmeyen bir alan doğrulamayı kirletmez ve sunucu "hangisi geçerli"
 * diye sormak zorunda kalmaz.
 */
const YENI = '__yeni';

function uygula(secim, odakla = false) {
    const alan = secim.closest('[data-kategori]');
    const kutu = alan?.querySelector('[data-kategori-yeni]');

    if (!kutu) {
        return;
    }

    const yeni = secim.value === YENI;

    kutu.hidden = !yeni;
    kutu.disabled = !yeni;

    if (yeni && odakla) {
        kutu.focus();
    }

    if (!yeni) {
        kutu.value = '';
    }
}

/** Her çizimde: ekrandaki seçimlerin kutusu doğru durumda başlasın. */
export function prepare() {
    document.querySelectorAll('[data-kategori-secim]').forEach((secim) => uygula(secim));
}

/** Bir kez: dinleyici gövdede durur, yeniden çizilen alanlar da kapsanır. */
export function init() {
    on('change', '[data-kategori-secim]', (olay, secim) => uygula(secim, true));
}
