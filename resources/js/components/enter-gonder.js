/**
 * ENTER MESAJI GÖNDERİR, SHIFT+ENTER ALT SATIRA GEÇER.
 *
 * Sohbet kutusu bir `textarea`: Enter doğal olarak yeni satır açıyor ve
 * göndermek için fareye uzanmak gerekiyordu. Sohbet arayüzlerinde beklenen
 * davranış budur; alt satır isteyen Shift'e basar.
 *
 * NEDEN `form.requestSubmit()` VE `form.submit()` DEĞİL:
 * `submit()` tarayıcının doğrulamasını ATLAR — boş bir kutu sunucuya gider
 * ve kullanıcı "gönderdim ama bir şey olmadı" der. `requestSubmit()` düğmeye
 * basılmış gibi davranır: `required` çalışır, `submit` olayı tetiklenir.
 *
 * TEKRAR GÖNDERİM ENGELLENİR: iş kuyruğa girdikten sonra kutu zaten
 * kilitleniyor (sunucu `disabled` çiziyor), ama iki Enter arasında sayfa
 * henüz dönmemişse ikinci gönderim aynı mesajı iki kez yazdırırdı.
 */
export function init() {
    document.addEventListener('keydown', (olay) => {
        if (olay.key !== 'Enter' || olay.shiftKey || olay.isComposing) {
            return;
        }

        const kutu = olay.target.closest('textarea[data-enter-gonder]');

        if (!kutu || kutu.disabled) {
            return;
        }

        const form = kutu.form;

        if (!form || form.dataset.gonderiliyor === 'evet') {
            return;
        }

        olay.preventDefault();

        /* Boş ya da yalnızca boşluk: gönderilmez, kutu da temizlenmez. */
        if (kutu.value.trim() === '') {
            return;
        }

        form.dataset.gonderiliyor = 'evet';
        form.requestSubmit();
    });
}
