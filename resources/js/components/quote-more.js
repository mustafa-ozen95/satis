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
    document.addEventListener('click', async (olay) => {
        const dugme = olay.target.closest('[data-teklif-daha-dugme]');

        if (!dugme) {
            return;
        }

        olay.preventDefault();

        const kap = dugme.closest('[data-teklif-daha]');
        const liste = dugme.closest('[data-teklif-listesi]');

        if (!kap || !liste || dugme.disabled) {
            return;
        }

        /* Çift tıklama aynı sayfayı iki kez eklerdi. */
        dugme.disabled = true;
        const eskiYazi = dugme.textContent;
        dugme.textContent = 'Yükleniyor…';

        try {
            const adres = new URL(dugme.dataset.adres || '/tekliflerim/daha', window.location.origin);
            adres.searchParams.set('oncesi', dugme.dataset.oncesi);

            /* Arama kutusundaki süzgeç korunur; yoksa devamı süzgeçsiz gelirdi. */
            const arama = new URL(window.location.href).searchParams.get('q');

            if (arama) {
                adres.searchParams.set('q', arama);
            }

            const yanit = await fetch(adres, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!yanit.ok) {
                throw new Error(String(yanit.status));
            }

            const html = await yanit.text();

            /*
             * Yeni satırlar düğmenin YERİNE geçer: gelen parça kendi
             * "Devamını gör" düğmesini de taşıyor (ya da taşımıyor — liste
             * bittiyse düğme hiç gelmez ve kap kendiliğinden kaybolur).
             */
            kap.insertAdjacentHTML('beforebegin', html);
            kap.remove();
        } catch {
            dugme.disabled = false;
            dugme.textContent = eskiYazi;

            emit('Devamı yüklenemedi; tekrar deneyin.', 'error');
        }
    });
}
