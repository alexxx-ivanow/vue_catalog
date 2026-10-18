<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$colorizeIcons = !empty($scheme['colorizeIcons']);
$useModal = !empty($scheme['useModal']);
$itemClass = 'abcwww-markers__item';
if (!empty($marker['opened'])) {
    $itemClass .= ' is-active';
}
if (!$colorizeIcons) {
    $itemClass .= ' is-natural';
}

$style = 'left: ' . (float)$marker['x'] . '%; top: ' . (float)$marker['y'] . '%;';
if ($colorizeIcons) {
    $style .= ' --marker-color: ' . htmlspecialcharsbx((string)$marker['color']) . ';';
    $style .= ' --marker-hover: ' . htmlspecialcharsbx((string)$marker['hoverColor']) . ';';
}
?>
<div
    class="<?= $itemClass ?>"
    data-marker-id="<?= (int)$marker['id'] ?>"
    style="<?= $style ?>"
>
    <button type="button" class="abcwww-markers__pin" aria-expanded="false" aria-label="<?= htmlspecialcharsbx((string)$marker['name']) ?>">
        <? if (!empty($scheme['icon'])): ?>
            <? if ($colorizeIcons): ?>
                <span class="abcwww-markers__icon-mask" style="--marker-icon:url('<?= htmlspecialcharsbx((string)$scheme['icon']) ?>');"></span>
            <? else: ?>
                <img class="abcwww-markers__icon-image" src="<?= htmlspecialcharsbx((string)$scheme['icon']) ?>" alt="" loading="lazy">
            <? endif; ?>
        <? else: ?>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M12 2c-4.1 0-7.5 3.3-7.5 7.4 0 5.1 6.3 11.8 7.1 12.5.2.2.6.2.8 0 .8-.7 7.1-7.4 7.1-12.5C19.5 5.3 16.1 2 12 2zm0 10.2a2.8 2.8 0 1 1 0-5.6 2.8 2.8 0 0 1 0 5.6z"></path>
            </svg>
        <? endif; ?>
    </button>

    <? if ($useModal): ?>
        <? include __DIR__ . '/_modal_content.php'; ?>
    <? else: ?>
    <div class="abcwww-markers__tooltip<?= !empty($marker['opened']) ? ' is-open' : '' ?>" role="dialog">
        <h5><?= htmlspecialcharsbx((string)$marker['name']) ?></h5>
        <? if (!empty($marker['previewImage'])): ?>
            <img class="abcwww-markers__preview-image" src="<?= htmlspecialcharsbx((string)$marker['previewImage']) ?>" alt="">
        <? endif; ?>
        <? if (!empty($marker['previewText'])): ?>
            <div class="abcwww-markers__preview-text"><?= $marker['previewText'] ?></div>
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
    <? endif; ?>
</div>
