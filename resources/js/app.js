import * as bulk from './components/bulk.js';
import * as inlineEdit from './components/inline-edit.js';
import * as columns from './components/columns.js';
import * as confirmDialog from './components/confirm.js';
import * as copy from './components/copy.js';
import * as dialog from './components/dialog.js';
import * as enterSend from './components/enter-send.js';
import * as category from './components/category.js';
import * as scrollNavigate from './components/scrolling-navigate.js';
import * as keyboard from './components/keyboard.js';
import * as userMenu from './components/user-menu.js';
import * as navigation from './components/navigation.js';
import * as quoteMore from './components/quote-more.js';
import * as quotePolling from './components/quote-polling.js';
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

function everyRender() {
    toast.init();
    keyboard.prepare();
    bulk.prepare();
    category.prepare();

    /* Dar ekranda şerit doğru bölmeye iner; geniş ekranda hiçbir şey yapmaz. */
    columns.init();

    /*
     * Her çizimde: teklif "üretiliyor" durumundayken sayfa yenilenmeden
     * hazır hâle geçiyor. Bir kez kurulsaydı ikinci teklif izlenmezdi.
     */
    quotePolling.init();
}

document.addEventListener('DOMContentLoaded', () => {
    dialog.init();
    confirmDialog.init();
    bulk.init();
    inlineEdit.init();
    copy.init();
    category.init();

    /* Telefonda parmakla bölüm değiştirme; dinleyici gövdede, bir kez kurulur. */
    scrollNavigate.init();

    /* Enter mesajı gönderir; dinleyici gövdede, bir kez kurulur. */
    enterSend.init();

    /* "Devamını gör": sonraki on beş teklif listeye eklenir. */
    quoteMore.init();

    keyboard.init();
    userMenu.init();
    navigation.init();

    everyRender();
});

document.addEventListener('sys:page', everyRender);
