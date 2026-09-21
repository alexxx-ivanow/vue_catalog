<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
?>
<div
    class="abcwww-markers__item<?= !empty($marker['opened']) ? ' is-active' : '' ?>"
    data-marker-id="<?= (int)$marker['id'] ?>"
    style="left: <?= (float)$marker['x'] ?>%; top: <?= (float)$marker['y'] ?>%; --marker-color: <?= htmlspecialcharsbx((string)$marker['color']) ?>; --marker-hover: <?= htmlspecialcharsbx((string)$marker['hoverColor']) ?>;"
>
    <button type="button" class="abcwww-markers__pin">
        <?php if (!empty($scheme['icon'])): ?>
            <span class="abcwww-markers__icon-mask" style="--marker-icon:url('<?= htmlspecialcharsbx((string)$scheme['icon']) ?>');"></span>
        <?php else: ?>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M12 2c-4.1 0-7.5 3.3-7.5 7.4 0 5.1 6.3 11.8 7.1 12.5.2.2.6.2.8 0 .8-.7 7.1-7.4 7.1-12.5C19.5 5.3 16.1 2 12 2zm0 10.2a2.8 2.8 0 1 1 0-5.6 2.8 2.8 0 0 1 0 5.6z"></path>
            </svg>
        <?php endif; ?>
    </button>

    <div class="abcwww-markers__tooltip<?= !empty($marker['opened']) ? ' is-open' : '' ?>">
        <h5><?= htmlspecialcharsbx((string)$marker['name']) ?></h5>
        <?php if (!empty($marker['previewImage'])): ?>
            <img class="abcwww-markers__preview-image" src="<?= htmlspecialcharsbx((string)$marker['previewImage']) ?>" alt="">
        <?php endif; ?>
        <?php if (!empty($marker['previewText'])): ?>
            <div class="abcwww-markers__preview-text"><?= $marker['previewText'] ?></div>
        <?php endif; ?>

        <?php if (!empty($marker['showMoreButton']) && !empty($marker['buttonText'])): ?>
            <?php if (!empty($marker['productUrl'])): ?>
            <a class="abcwww-markers__btn" style="background:<?= htmlspecialcharsbx((string)$marker['buttonColor']) ?>" href="<?= htmlspecialcharsbx((string)$marker['productUrl']) ?>">
                <?= htmlspecialcharsbx((string)$marker['buttonText']) ?>
            </a>
            <?php else: ?>
            <span class="abcwww-markers__btn" style="background:<?= htmlspecialcharsbx((string)$marker['buttonColor']) ?>">
                <?= htmlspecialcharsbx((string)$marker['buttonText']) ?>
            </span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
