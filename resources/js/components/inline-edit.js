import { on } from '../lib/dom.js';
import { emit } from './toast.js';

/**
 * HÜCRE YERİNDE DÜZENLENİR.
 *
 * Fiyat listesinde art arda birkaç değer güncellerken pencere açıp kapatmak
 * her seferinde dört tıklama demekti. Hücreye çift tıklamak onu doğrudan
 * düzenlenebilir yapar; Enter kaydeder, Escape vazgeçer, odak çıkınca da
 * kaydeder.
 *
 * PENCERE KALDIRILMIYOR: çift tıklama keşfedilebilir değil, dokunmatik
 * ekranda karşılığı yok ve bütün alanlar (KDV, notlar, aktiflik) burada
 * düzenlenmiyor. Bu bir KISA YOL, tek yol değil.
 *
 * YETKİ SUNUCUDA: uç kendi yetkisini ve doğrulamasını kendisi yapar
 * (bkz. PriceFieldRequest). Buradaki iş yalnızca alanı gösterip değeri
 * göndermek; ekranda kilitlemek güvenlik değildir.
 */
const CELL = 'td[data-field]';

let openCell = null;

function finish(cell, text) {
    cell.textContent = text;
    cell.classList.remove('cell--editing');
    openCell = null;
}

async function save(cell, value) {
    const { url: address, field: field, raw: raw } = cell.dataset;

    if (value === raw) {
        finish(cell, cell.dataset.display);

        return;
    }

    cell.classList.add('cell--pending');

    try {
        const response = await fetch(address, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ field, value }),
        });

        const data = await response.json().catch(() => ({}));

        if (! response.ok) {
            /*
             * Sunucu ne dediyse o gösterilir. "Bir hata oluştu" demek,
             * kullanıcıyı neyi düzelteceğini bilmeden bırakmaktır.
             */
            throw new Error(data.error ?? 'Kaydedilemedi.');
        }

        cell.dataset.raw = data.raw;
        cell.dataset.display = data.display;
        finish(cell, data.display);
        emit(data.message ?? 'Kaydedildi.', 'success');
    } catch (error) {
        finish(cell, cell.dataset.display);
        emit(error.message, 'danger');
    } finally {
        cell.classList.remove('cell--pending');
    }
}

function open(cell) {
    if (openCell !== null) {
        return;
    }

    openCell = cell;
    cell.classList.add('cell--editing');

    /*
     * KATEGORİ GİBİ ALANLAR SEÇİLİR, YAZILMAZ.
     *
     * Serbest metin her yazımda yeni bir kategori doğuruyordu ("IP / Network"
     * ile "IP/Network" ayrı iki kategori). Hücre seçeneklerini kendisi
     * taşıyor; liste sunucudan geliyor ve ekranla aynı kaynaktan besleniyor.
     */
    const options = cell.dataset.options
        ? JSON.parse(cell.dataset.options)
        : null;

    const field = options
        ? document.createElement('select')
        : document.createElement('input');

    field.className = 'input input--cell';

    if (options) {
        for (const option of options) {
            const selection = document.createElement('option');
            selection.value = option;
            selection.textContent = option;
            field.append(selection);
        }

        /* Kayıtlı değer listede yoksa yine de seçilebilir kalsın. */
        if (!options.includes(cell.dataset.raw)) {
            const current = document.createElement('option');
            current.value = cell.dataset.raw;
            current.textContent = cell.dataset.raw;
            field.prepend(current);
        }
    } else {
        field.type = cell.dataset.type === 'number' ? 'number' : 'text';

        if (field.type === 'number') {
            field.step = '0.01';
            field.min = '0';
        }
    }

    field.value = cell.dataset.raw;

    cell.textContent = '';
    cell.append(field);
    field.focus();

    /* Metin kutusunda içerik seçilir; listede seçilecek bir metin yok. */
    if (typeof field.select === 'function' && !options) {
        field.select();
    }

    let closed = false;

    const complete = (shouldSave) => {
        if (closed) {
            return;
        }

        closed = true;

        if (shouldSave) {
            save(cell, field.value.trim());
        } else {
            finish(cell, cell.dataset.display);
        }
    };

    field.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            complete(true);
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            complete(false);
        }
    });

    /* Listede seçim yapmak işin kendisidir; ayrıca odak çıkmasını beklemeye gerek yok. */
    if (options) {
        field.addEventListener('change', () => complete(true));
    }

    /* Odak çıkınca kaydedilir: kullanıcı başka bir hücreye geçtiğinde işi bitmiştir. */
    field.addEventListener('blur', () => complete(true));
}

export function init() {
    on('dblclick', CELL, (event, cell) => {
        if (cell.classList.contains('cell--editing')) {
            return;
        }

        window.getSelection()?.removeAllRanges();
        open(cell);
    });
}
