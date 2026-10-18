<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Контент модального окна для одной метки.
 * Можно править в шаблоне компонента — JS подставит этот HTML в .abcwww-markers-modal__body.
 *
 * Доступны: $marker, $scheme
 * Доп. свойства: $marker['properties']['CODE']
 */
$modalImage = !empty($marker['detailImage']) ? $marker['detailImage'] : ($marker['previewImage'] ?? '');
$modalText = !empty($marker['detailText']) ? $marker['detailText'] : ($marker['previewText'] ?? '');
?>
<div class="abcwww-markers__modal-source" hidden>
    <h4><?= htmlspecialcharsbx((string)$marker['name']) ?></h4>

    <? if ($modalImage !== ''): ?>
        <img class="abcwww-markers__preview-image" src="<?= htmlspecialcharsbx((string)$modalImage) ?>" alt="">
    <? endif; ?>

    <? if ($modalText !== ''): ?>
        <div class="abcwww-markers__preview-text"><?= $modalText ?></div>
    <? endif; ?>

    <? if (!empty($marker['showMoreButton']) && !empty($marker['buttonText'])): ?>
        <? if (!empty($marker['productUrl'])): ?>
            <a class="abcwww-markers__btn" style="background:<?= htmlspecialcharsbx((string)$marker['buttonColor']) ?>" href="<?= htmlspecialcharsbx((string)$marker['productUrl']) ?>">
                <?= htmlspecialcharsbx((string)$marker['buttonText']) ?>
            </a>
        <? else: ?>
            <span class="abcwww-markers__btn" style="background:<?= htmlspecialcharsbx((string)$marker['buttonColor']) ?>">
                <?= htmlspecialcharsbx((string)$marker['buttonText']) ?>
            </span>
        <? endif; ?>
    <? endif; ?>
</div>
