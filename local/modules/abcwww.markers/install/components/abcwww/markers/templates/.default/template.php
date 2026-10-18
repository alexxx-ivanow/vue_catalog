<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$asset = \Bitrix\Main\Page\Asset::getInstance();
$asset->addCss('/bitrix/css/abcwww.markers/markers.css');
$asset->addJs('/bitrix/js/abcwww.markers/markers.js');

$uid = 'abcwww-markers-' . $arResult['SECTION_ID'] . '-' . mt_rand(1000, 9999);
$scheme = $arResult['SCHEME'];
$useModal = !empty($scheme['useModal']);
$json = CUtil::PhpToJSObject($scheme);
?>
<div id="<?= htmlspecialcharsbx($uid) ?>" class="abcwww-markers-root">
    <div class="abcwww-markers" style="--marker-size-desktop:<?= (int)($scheme['iconPc'] ?? 24) ?>px;--marker-size-mobile:<?= (int)($scheme['iconMobile'] ?? 18) ?>px;">
        <div class="abcwww-markers__board">
            <img class="abcwww-markers__image" src="<?= htmlspecialcharsbx((string)($scheme['image'] ?? '')) ?>" alt="<?= htmlspecialcharsbx((string)($scheme['name'] ?? '')) ?>">
            <div class="abcwww-markers__layer">
                <? foreach (($scheme['markers'] ?? []) as $marker): ?>
                    <? include __DIR__ . '/_marker_item.php'; ?>
                <? endforeach; ?>
            </div>
        </div>
    </div>

    <? if ($useModal): ?>
        <? include __DIR__ . '/_modal.php'; ?>
    <? endif; ?>
</div>
<script>
    window.AbcwwwMarkersInstances = window.AbcwwwMarkersInstances || [];
    window.AbcwwwMarkersInstances.push({
        targetId: <?= CUtil::PhpToJSObject($uid) ?>,
        serverRendered: true,
        data: <?= $json ?>
    });
</script>
