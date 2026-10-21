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
const HUCRE = 'td[data-alan]';

let acikHucre = null;

function bitir(hucre, metin) {
    hucre.textContent = metin;
    hucre.classList.remove('hucre--duzenleniyor');
    acikHucre = null;
}

async function kaydet(hucre, deger) {
    const { adres, alan, ham } = hucre.dataset;

    if (deger === ham) {
        bitir(hucre, hucre.dataset.gosterim);

        return;
    }

    hucre.classList.add('hucre--bekliyor');

    try {
        const yanit = await fetch(adres, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ alan, deger }),
        });

        const veri = await yanit.json().catch(() => ({}));

        if (! yanit.ok) {
            /*
             * Sunucu ne dediyse o gösterilir. "Bir hata oluştu" demek,
             * kullanıcıyı neyi düzelteceğini bilmeden bırakmaktır.
             */
            throw new Error(veri.hata ?? 'Kaydedilemedi.');
        }

        hucre.dataset.ham = veri.ham;
        hucre.dataset.gosterim = veri.gosterim;
        bitir(hucre, veri.gosterim);
        emit(veri.mesaj ?? 'Kaydedildi.', 'success');
    } catch (hata) {
        bitir(hucre, hucre.dataset.gosterim);
        emit(hata.message, 'danger');
    } finally {
        hucre.classList.remove('hucre--bekliyor');
    }
}

function ac(hucre) {
    if (acikHucre !== null) {
        return;
    }

    acikHucre = hucre;
    hucre.classList.add('hucre--duzenleniyor');

    /*
     * KATEGORİ GİBİ ALANLAR SEÇİLİR, YAZILMAZ.
     *
     * Serbest metin her yazımda yeni bir kategori doğuruyordu ("IP / Network"
     * ile "IP/Network" ayrı iki kategori). Hücre seçeneklerini kendisi
     * taşıyor; liste sunucudan geliyor ve ekranla aynı kaynaktan besleniyor.
     */
    const secenekler = hucre.dataset.secenekler
        ? JSON.parse(hucre.dataset.secenekler)
        : null;

    const alan = secenekler
        ? document.createElement('select')
        : document.createElement('input');

    alan.className = 'input input--hucre';

    if (secenekler) {
        for (const secenek of secenekler) {
            const secim = document.createElement('option');
            secim.value = secenek;
            secim.textContent = secenek;
            alan.append(secim);
        }

        /* Kayıtlı değer listede yoksa yine de seçilebilir kalsın. */
        if (!secenekler.includes(hucre.dataset.ham)) {
            const mevcut = document.createElement('option');
            mevcut.value = hucre.dataset.ham;
            mevcut.textContent = hucre.dataset.ham;
            alan.prepend(mevcut);
        }
    } else {
        alan.type = hucre.dataset.tur === 'sayi' ? 'number' : 'text';

        if (alan.type === 'number') {
            alan.step = '0.01';
            alan.min = '0';
        }
    }

    alan.value = hucre.dataset.ham;

    hucre.textContent = '';
    hucre.append(alan);
    alan.focus();

    /* Metin kutusunda içerik seçilir; listede seçilecek bir metin yok. */
    if (typeof alan.select === 'function' && !secenekler) {
        alan.select();
    }

    let kapandi = false;

    const tamamla = (kaydedilsin) => {
        if (kapandi) {
            return;
        }

        kapandi = true;

        if (kaydedilsin) {
            kaydet(hucre, alan.value.trim());
        } else {
            bitir(hucre, hucre.dataset.gosterim);
        }
    };

    alan.addEventListener('keydown', (olay) => {
        if (olay.key === 'Enter') {
            olay.preventDefault();
            tamamla(true);
        }

        if (olay.key === 'Escape') {
            olay.preventDefault();
            tamamla(false);
        }
    });

    /* Listede seçim yapmak işin kendisidir; ayrıca odak çıkmasını beklemeye gerek yok. */
    if (secenekler) {
        alan.addEventListener('change', () => tamamla(true));
    }

    /* Odak çıkınca kaydedilir: kullanıcı başka bir hücreye geçtiğinde işi bitmiştir. */
    alan.addEventListener('blur', () => tamamla(true));
}

export function init() {
    on('dblclick', HUCRE, (olay, hucre) => {
        if (hucre.classList.contains('hucre--duzenleniyor')) {
            return;
        }

        window.getSelection()?.removeAllRanges();
        ac(hucre);
    });
}
