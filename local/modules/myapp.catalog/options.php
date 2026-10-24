<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

/**
 * @global CUser $USER
 * @global CMain $APPLICATION
 * @var string $mid
 */

if (!$USER->IsAdmin()) {
    return;
}

Loc::loadMessages(__FILE__);

$moduleId = 'myapp.catalog';
$optUseBitrixVue = 'use_bitrix_vue';
$optCatalogIblockId = 'catalog_iblock_id';
$defaultCatalogIblockId = 2;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    Option::set($moduleId, $optUseBitrixVue, isset($_POST[$optUseBitrixVue]) ? 'Y' : 'N');

    $postedIblockId = (int)($_POST[$optCatalogIblockId] ?? 0);
    if ($postedIblockId > 0) {
        Option::set($moduleId, $optCatalogIblockId, (string)$postedIblockId);
    }
}

$useBitrixVue = Option::get($moduleId, $optUseBitrixVue, 'N') === 'Y';
$catalogIblockId = (int)Option::get($moduleId, $optCatalogIblockId, (string)$defaultCatalogIblockId);
if ($catalogIblockId <= 0) {
    $catalogIblockId = $defaultCatalogIblockId;
}

$aTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => Loc::getMessage('MYAPP_CATALOG_OPT_TAB'),
        'TITLE' => Loc::getMessage('MYAPP_CATALOG_OPT_TAB_TITLE'),
    ],
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$tabControl->Begin();
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($mid) ?>&lang=<?= LANGUAGE_ID ?>">
    <?php
    echo bitrix_sessid_post();
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td width="50%" class="adm-detail-content-cell-l">
            <label for="MYAPP_CATALOG_USE_BITRIX_VUE">
                <?= Loc::getMessage('MYAPP_CATALOG_OPT_USE_BITRIX_VUE') ?>
            </label>
            <br>
            <small><?= Loc::getMessage('MYAPP_CATALOG_OPT_USE_BITRIX_VUE_HINT') ?></small>
        </td>
        <td width="50%" class="adm-detail-content-cell-r">
            <input
                type="checkbox"
                class="adm-designed-checkbox"
                id="MYAPP_CATALOG_USE_BITRIX_VUE"
                name="<?= htmlspecialcharsbx($optUseBitrixVue) ?>"
                value="Y"
                <?= $useBitrixVue ? 'checked' : '' ?>
            >
            <label class="adm-designed-checkbox-label" for="MYAPP_CATALOG_USE_BITRIX_VUE"></label>
        </td>
    </tr>
    <tr>
        <td width="50%" class="adm-detail-content-cell-l">
            <label for="MYAPP_CATALOG_IBLOCK_ID">
                <?= Loc::getMessage('MYAPP_CATALOG_OPT_IBLOCK_ID') ?>
            </label>
        </td>
        <td width="50%" class="adm-detail-content-cell-r">
            <input
                type="number"
                min="1"
                step="1"
                id="MYAPP_CATALOG_IBLOCK_ID"
                name="<?= htmlspecialcharsbx($optCatalogIblockId) ?>"
                value="<?= (int)$catalogIblockId ?>"
            >
        </td>
    </tr>
    <?php
    $tabControl->Buttons();
    ?>
    <input type="submit" class="adm-btn-save" name="save" value="<?= Loc::getMessage('MAIN_SAVE') ?>">
    <?php
    $tabControl->End();
    ?>
</form>
