import { git } from './navigation.js';
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
 * şablondaki bütün modüller gibi `init()` dışa aktarıyor. Veri satır içi enjekte edilmez; `#mesaj-akisi` üzerindeki
 * `data-*` özniteliklerinden okunur, çünkü bu öznitelikler HTML olarak
 * gelir ve `replaceWith` sonrasında da DOM'da durur.
 */

let aktifKontrolId = null;

function teklifPollingBaslat() {
    const akis = document.getElementById('mesaj-akisi');

    if (!akis) {
        aktifKontrolId = null;

        return;
    }

    /* Durum ne olursa olsun (hazir/hata/bekliyor) son mesaj gorunur olsun. */
    akis.scrollTop = akis.scrollHeight;

    if (akis.dataset.teklifDurum !== 'bekliyor') {
        aktifKontrolId = null;

        return;
    }

    const conversationId = akis.dataset.conversationId;

    /* Ayni konusma icin zaten calisan bir kontrol dongusu varsa tekrar baslatma. */
    if (aktifKontrolId === conversationId) {
        return;
    }

    aktifKontrolId = conversationId;

    async function kontrolEt() {
        /* Sayfa degisti mi (baska bir konusmaya gecildi ya da uzaklasildi)? Kesin dur. */
        if (aktifKontrolId !== conversationId || document.getElementById('mesaj-akisi') !== akis) {
            return;
        }

        try {
            const res = await fetch(`/teklif-durum/${conversationId}`);
            const data = await res.json();

            if (aktifKontrolId !== conversationId) {
                return;
            }

            if (data.durum === 'hazir') {
                git(window.location.pathname + window.location.search, 'yok');
            } else if (data.durum === 'hata') {
                const durumMetni = document.getElementById('durum-metni');

                if (durumMetni) {
                    durumMetni.textContent = 'Bir hata oluştu: '
                        + (data.hata_mesaji || 'bilinmeyen hata')
                        + ' — tekrar deneyin.';
                }

                setTimeout(() => git(window.location.pathname + window.location.search, 'yok'), 1500);
            } else {
                setTimeout(kontrolEt, 1500);
            }
        } catch {
            setTimeout(kontrolEt, 2500);
        }
    }

    kontrolEt();
}

export function init() {
    teklifPollingBaslat();
}
