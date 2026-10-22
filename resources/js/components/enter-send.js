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
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
            return;
        }

        const box = event.target.closest('textarea[data-enter-send]');

        if (!box || box.disabled) {
            return;
        }

        const form = box.form;

        if (!form || form.dataset.submitting === 'yes') {
            return;
        }

        event.preventDefault();

        /* Boş ya da yalnızca boşluk: gönderilmez, kutu da temizlenmez. */
        if (box.value.trim() === '') {
            return;
        }

        form.dataset.submitting = 'yes';
        form.requestSubmit();
    });
}
