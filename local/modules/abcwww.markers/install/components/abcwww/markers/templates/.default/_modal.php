<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Оболочка модального окна (backdrop / dialog / крестик / body).
 * Можно править в шаблоне компонента.
 */
?>
<div class="abcwww-markers-modal" aria-hidden="true" hidden>
    <div class="abcwww-markers-modal__backdrop" data-modal-close="Y"></div>
    <div class="abcwww-markers-modal__dialog" role="dialog" aria-modal="true">
        <button type="button" class="abcwww-markers-modal__close" data-modal-close="Y" aria-label="Закрыть">&times;</button>
        <div class="abcwww-markers-modal__body"></div>
    </div>
</div>
