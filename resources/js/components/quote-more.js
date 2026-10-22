import { emit } from './toast.js';

/**
 * "DEVAMINI GÖR" — sonraki on beş teklif.
 *
 * Liste zamanla binlerce satıra çıkabilir; hepsini tek seferde çizmek
 * telefonda sayfayı kilitler. Sunucu ilk on beşi veriyor, gerisi bu
 * düğmeyle geliyor ve listenin SONUNA ekleniyor — sayfa yenilenmiyor, açık
 * olan teklif ekranda kalıyor.
 *
 * İMLEÇ, SAYFA NUMARASI DEĞİL: düğme son satırın kimliğini taşır ve sunucu
 * "bundan küçük olanlar" diye sorar. Numarayla sayfalasaydık, aradaki bir
 * teklif silindiğinde sonraki sayfa bir satır atlardı.
 *
 * SUNUCU HTML DÖNER, JSON DEĞİL: satırın biçimi tek yerde (partials/satirlar)
 * duruyor. JSON dönseydi aynı satırı bir de burada, JavaScript'le çizmek
 * gerekir ve iki çizim zamanla ayrışırdı.
 */
export function init() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-quote-more-button]');

        if (!button) {
            return;
        }

        event.preventDefault();

        const container = button.closest('[data-quote-more]');
        const list = button.closest('[data-quote-list]');

        if (!container || !list || button.disabled) {
            return;
        }

        /* Çift tıklama aynı sayfayı iki kez eklerdi. */
        button.disabled = true;
        const oldString = button.textContent;
        button.textContent = 'Yükleniyor…';

        try {
            const address = new URL(button.dataset.url || '/quotes/more', window.location.origin);
            address.searchParams.set('oncesi', button.dataset.before);

            /* Arama kutusundaki süzgeç korunur; yoksa devamı süzgeçsiz gelirdi. */
            const search = new URL(window.location.href).searchParams.get('q');

            if (search) {
                address.searchParams.set('q', search);
            }

            const response = await fetch(address, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const html = await response.text();

            /*
             * Yeni satırlar düğmenin YERİNE geçer: gelen parça kendi
             * "Devamını gör" düğmesini de taşıyor (ya da taşımıyor — liste
             * bittiyse düğme hiç gelmez ve kap kendiliğinden kaybolur).
             */
            container.insertAdjacentHTML('beforebegin', html);
            container.remove();
        } catch {
            button.disabled = false;
            button.textContent = oldString;

            emit('Devamı yüklenemedi; tekrar deneyin.', 'error');
        }
    });
}
