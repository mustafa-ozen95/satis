import { $, $$ } from '../lib/dom.js';

/**
 * Sütunlar arasında klavyeyle gezinme.
 *
 * Bu tasarımın çekirdeği klavye gezinmesidir: ↑↓ satırlar arasında dolaşır,
 * → veya Enter seçili satırı açar, ← bir önceki sütuna döner, "/" filtreye
 * odaklanır.
 *
 * Yalnızca odak ve tıklama yönetir; hangi satırın nereye gittiğine sunucunun
 * ürettiği bağlantı karar verir.
 */

const ROW_SELECTOR = '.row-item, .grid-row';

function columns() {
    return $$('.columns .column, .columns .detail');
}

function rows(column) {
    return $$(ROW_SELECTOR, column).filter((row) => row.offsetParent !== null);
}

function activeColumn() {
    const focusTarget = document.activeElement?.closest('.column, .detail');

    return focusTarget ?? columns().at(-1) ?? null;
}

function move(column, direction) {
    const list = rows(column);

    if (list.length === 0) {
        return;
    }

    const now = list.indexOf(document.activeElement);
    const next = now === -1
        ? 0
        : Math.min(list.length - 1, Math.max(0, now + direction));

    list[next].focus();
    list[next].scrollIntoView({ block: 'nearest' });
}

function neighborColumn(column, direction) {
    const list = columns();
    const next = list[list.indexOf(column) + direction];

    if (!next) {
        return;
    }

    const destination = rows(next);
    (destination.find((row) => row.getAttribute('aria-selected') === 'true') ?? destination[0])?.focus();
    next.scrollIntoView({ inline: 'nearest', behavior: 'smooth' });
}

function isTypingField(destination) {
    return ['INPUT', 'TEXTAREA', 'SELECT'].includes(destination?.tagName);
}

/**
 * Satırları klavyeyle odaklanabilir yapar.
 *
 * Her sayfa çiziminde yeniden çalışır: geçişlerde tarayıcı yenilenmediği
 * için yeni satırlar bu işlemden geçmemiş olurdu. Bağlantı ve düğme zaten
 * odaklanabilir.
 */
export function prepare() {
    $$(ROW_SELECTOR).forEach((row) => {
        if (! row.hasAttribute('tabindex') && ! ['A', 'BUTTON'].includes(row.tagName)) {
            row.tabIndex = 0;
        }
    });
}

export function init() {
    prepare();

    document.addEventListener('keydown', (event) => {
        /* Sütunlu ekranlarda çalışır; kontrol her tuşta yapılır çünkü sayfa
           geçişte değişiyor ve dinleyici bir kez kuruluyor. */
        if (! $('.columns') || event.metaKey || event.ctrlKey || event.altKey) {
            return;
        }

        if (event.key === '/' && ! isTypingField(event.target)) {
            event.preventDefault();
            $('.filter__input')?.focus();

            return;
        }

        if (isTypingField(event.target)) {
            return;
        }

        const column = activeColumn();

        if (! column) {
            return;
        }

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                move(column, 1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                move(column, -1);
                break;
            case 'ArrowRight':
                event.preventDefault();
                document.activeElement?.matches(ROW_SELECTOR)
                    ? document.activeElement.click()
                    : neighborColumn(column, 1);
                break;
            case 'ArrowLeft':
                event.preventDefault();
                neighborColumn(column, -1);
                break;
            default:
        }
    });
}
