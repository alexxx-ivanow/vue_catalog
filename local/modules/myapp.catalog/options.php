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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    Option::set($moduleId, $optUseBitrixVue, isset($_POST[$optUseBitrixVue]) ? 'Y' : 'N');
}

$useBitrixVue = Option::get($moduleId, $optUseBitrixVue, 'N') === 'Y';

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
    <?php
    $tabControl->Buttons();
    ?>
    <input type="submit" class="adm-btn-save" name="save" value="<?= Loc::getMessage('MAIN_SAVE') ?>">
    <?php
    $tabControl->End();
    ?>
</form>
