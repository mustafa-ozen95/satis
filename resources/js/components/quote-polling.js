import { go } from './navigation.js';
/**
 * Teklif bekleme durumu polling'i.
 *
 * `navigation.js` sayfa içi bağlantılarda TARAYICI YENİLEMEDEN geçiş yapar:
 * `.columns` bölgesini `fetch` ile alıp DOM'a `replaceWith` eder. Bu şekilde
 * eklenen `<script>` etiketleri TARAYICI TARAFINDAN ÇALIŞTIRILMAZ (HTML
 * standardı) — sohbet ekranındaki satır içi polling script'i bu yüzden
 * yalnızca TAM sayfa yüklemesinde (F5) çalışıyordu, sol menüden bir
 * konuşmaya tıklanınca hiç başlamıyordu.
 *
 * Çözüm: mantığı buraya, KALICI bir betiğe taşımak ve hem ilk yüklemede
 * hem her yenilemesiz geçiş sonrasında yeniden tetiklemek. Tetiklemeyi
 * app.js yapıyor (bkz. `herCizimde`); modül kendi olayını dinlemiyor —
 * şablondaki bütün modüller gibi `init()` dışa aktarıyor. Veri satır içi enjekte edilmez; `#message-stream` üzerindeki
 * `data-*` özniteliklerinden okunur, çünkü bu öznitelikler HTML olarak
 * gelir ve `replaceWith` sonrasında da DOM'da durur.
 */

let activePollId = null;

function startQuotePolling() {
    const stream = document.getElementById('message-stream');

    if (!stream) {
        activePollId = null;

        return;
    }

    /* Durum ne olursa olsun (hazir/hata/bekliyor) son mesaj gorunur olsun. */
    stream.scrollTop = stream.scrollHeight;

    if (stream.dataset.quoteStatus !== 'bekliyor') {
        activePollId = null;

        return;
    }

    const conversationId = stream.dataset.conversationId;

    /* Ayni konusma icin zaten calisan bir kontrol dongusu varsa tekrar baslatma. */
    if (activePollId === conversationId) {
        return;
    }

    activePollId = conversationId;

    async function poll() {
        /* Sayfa degisti mi (baska bir konusmaya gecildi ya da uzaklasildi)? Kesin dur. */
        if (activePollId !== conversationId || document.getElementById('message-stream') !== stream) {
            return;
        }

        try {
            const res = await fetch(`/quote-status/${conversationId}`);
            const data = await res.json();

            if (activePollId !== conversationId) {
                return;
            }

            if (data.status === 'hazir') {
                go(window.location.pathname + window.location.search, 'none');
            } else if (data.status === 'hata') {
                const statusText = document.getElementById('status-text');

                if (statusText) {
                    statusText.textContent = 'Bir hata oluştu: '
                        + (data.error_message || 'bilinmeyen hata')
                        + ' — tekrar deneyin.';
                }

                setTimeout(() => go(window.location.pathname + window.location.search, 'none'), 1500);
            } else {
                setTimeout(poll, 1500);
            }
        } catch {
            setTimeout(poll, 2500);
        }
    }

    poll();
}

export function init() {
    startQuotePolling();
}
