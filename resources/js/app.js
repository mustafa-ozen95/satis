import * as bulk from './components/bulk.js';
import * as yerindeDuzenle from './components/inline-edit.js';
import * as columns from './components/columns.js';
import * as confirmDialog from './components/confirm.js';
import * as copy from './components/copy.js';
import * as dialog from './components/dialog.js';
import * as enterGonder from './components/enter-send.js';
import * as kategori from './components/category.js';
import * as kaydirarakGezin from './components/scrolling-navigate.js';
import * as keyboard from './components/keyboard.js';
import * as kullaniciMenu from './components/user-menu.js';
import * as navigation from './components/navigation.js';
import * as teklifDaha from './components/quote-more.js';
import * as teklifPolling from './components/quote-polling.js';
import * as theme from './components/theme.js';
import * as toast from './components/toast.js';

/**
 * Uygulama girişi.
 *
 * Buradaki modüller yalnızca DOM, olay ve arayüz işleriyle ilgilenir.
 * Yetki ve doğrulama sunucuda yapılır; burada tekrar edilmez.
 *
 * İKİ AYRI KURULUM var, çünkü sayfa geçişleri tarayıcıyı yenilemiyor
 * (bkz. navigation.js):
 *
 *  - BİR KEZ: olayları document üzerinde dinleyen bileşenler. İkinci kez
 *    kurulsalardı her tıklama iki kez işlenirdi.
 *  - HER ÇİZİMDE: düğümün kendisine bakan işler (yeni gelen içerikteki
 *    bildirim, satırların odaklanabilirliği, seçim sayacı).
 *
 * navigation EN SONA kurulur: bağlantıyı önce pencere ve onay bileşenleri
 * görmeli, geçiş yalnızca onlar karışmadıysa devralınmalı.
 *
 * Kardeş panellerle aynı yapı; farklı olan yalnızca bu
 * panele ait modül (teklif üretimini izleyen yoklama).
 */
theme.init();

function herCizimde() {
    toast.init();
    keyboard.prepare();
    bulk.prepare();
    kategori.prepare();

    /* Dar ekranda şerit doğru bölmeye iner; geniş ekranda hiçbir şey yapmaz. */
    columns.init();

    /*
     * Her çizimde: teklif "üretiliyor" durumundayken sayfa yenilenmeden
     * hazır hâle geçiyor. Bir kez kurulsaydı ikinci teklif izlenmezdi.
     */
    teklifPolling.init();
}

document.addEventListener('DOMContentLoaded', () => {
    dialog.init();
    confirmDialog.init();
    bulk.init();
    yerindeDuzenle.init();
    copy.init();
    kategori.init();

    /* Telefonda parmakla bölüm değiştirme; dinleyici gövdede, bir kez kurulur. */
    kaydirarakGezin.init();

    /* Enter mesajı gönderir; dinleyici gövdede, bir kez kurulur. */
    enterGonder.init();

    /* "Devamını gör": sonraki on beş teklif listeye eklenir. */
    teklifDaha.init();

    keyboard.init();
    kullaniciMenu.init();
    navigation.init();

    herCizimde();
});

document.addEventListener('sys:sayfa', herCizimde);
