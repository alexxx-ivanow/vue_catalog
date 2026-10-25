<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use MyApp\Catalog\Config\Assets;

/**
 * @global CUser $USER
 * @global CMain $APPLICATION
 * @var string $mid
 */

if (!$USER->IsAdmin()) {
    return;
}

if (!Loader::includeModule('myapp.catalog')) {
    return;
}

Loc::loadMessages(__FILE__);

$moduleId = 'myapp.catalog';
$optUseBitrixVue = 'use_bitrix_vue';
$optCatalogIblockId = 'catalog_iblock_id';
$optAutoLoginAfterGuestOrder = 'auto_login_after_guest_order';
$optShowCatalogFilter = Assets::OPT_SHOW_CATALOG_FILTER;
$optCatalogPriceTypeCode = Assets::OPT_CATALOG_PRICE_TYPE_CODE;
$optFilterPropertyCodes = Assets::OPT_FILTER_PROPERTY_CODES;
$defaultCatalogIblockId = 2;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    Option::set($moduleId, $optUseBitrixVue, isset($_POST[$optUseBitrixVue]) ? 'Y' : 'N');
    Option::set(
        $moduleId,
        $optAutoLoginAfterGuestOrder,
        isset($_POST[$optAutoLoginAfterGuestOrder]) ? 'Y' : 'N'
    );
    Option::set($moduleId, $optShowCatalogFilter, isset($_POST[$optShowCatalogFilter]) ? 'Y' : 'N');

    $postedPriceCode = trim((string)($_POST[$optCatalogPriceTypeCode] ?? ''));
    if ($postedPriceCode === '' || !preg_match('/^[A-Za-z0-9_]+$/', $postedPriceCode)) {
        $postedPriceCode = Assets::DEFAULT_CATALOG_PRICE_TYPE_CODE;
    }
    Option::set($moduleId, $optCatalogPriceTypeCode, $postedPriceCode);

    $postedCodes = $_POST[$optFilterPropertyCodes] ?? [];
    $propertyCodes = Assets::normalizePropertyCodes(is_array($postedCodes) ? $postedCodes : []);
    Option::set(
        $moduleId,
        $optFilterPropertyCodes,
        (string)json_encode($propertyCodes, JSON_UNESCAPED_UNICODE)
    );

    $postedIblockId = (int)($_POST[$optCatalogIblockId] ?? 0);
    if ($postedIblockId > 0) {
        Option::set($moduleId, $optCatalogIblockId, (string)$postedIblockId);
    }
}

$useBitrixVue = Option::get($moduleId, $optUseBitrixVue, 'N') === 'Y';
$autoLoginAfterGuestOrder = Option::get($moduleId, $optAutoLoginAfterGuestOrder, 'N') === 'Y';
$showCatalogFilter = Assets::showCatalogFilter();
$catalogPriceTypeCode = Assets::catalogPriceTypeCode();
$filterPropertyCodes = Assets::filterPropertyCodes();
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
            <label for="MYAPP_CATALOG_AUTO_LOGIN_GUEST">
                <?= Loc::getMessage('MYAPP_CATALOG_OPT_AUTO_LOGIN_GUEST') ?>
            </label>
            <br>
            <small><?= Loc::getMessage('MYAPP_CATALOG_OPT_AUTO_LOGIN_GUEST_HINT') ?></small>
        </td>
        <td width="50%" class="adm-detail-content-cell-r">
            <input
                type="checkbox"
                class="adm-designed-checkbox"
                id="MYAPP_CATALOG_AUTO_LOGIN_GUEST"
                name="<?= htmlspecialcharsbx($optAutoLoginAfterGuestOrder) ?>"
                value="Y"
                <?= $autoLoginAfterGuestOrder ? 'checked' : '' ?>
            >
            <label class="adm-designed-checkbox-label" for="MYAPP_CATALOG_AUTO_LOGIN_GUEST"></label>
        </td>
    </tr>
    <tr>
        <td width="50%" class="adm-detail-content-cell-l">
            <label for="MYAPP_CATALOG_SHOW_FILTER">
                <?= Loc::getMessage('MYAPP_CATALOG_OPT_SHOW_FILTER') ?>
            </label>
            <br>
            <small><?= Loc::getMessage('MYAPP_CATALOG_OPT_SHOW_FILTER_HINT') ?></small>
        </td>
        <td width="50%" class="adm-detail-content-cell-r">
            <input
                type="checkbox"
                class="adm-designed-checkbox"
                id="MYAPP_CATALOG_SHOW_FILTER"
                name="<?= htmlspecialcharsbx($optShowCatalogFilter) ?>"
                value="Y"
                <?= $showCatalogFilter ? 'checked' : '' ?>
            >
            <label class="adm-designed-checkbox-label" for="MYAPP_CATALOG_SHOW_FILTER"></label>
        </td>
    </tr>
    <tr>
        <td width="50%" class="adm-detail-content-cell-l">
            <label for="MYAPP_CATALOG_PRICE_TYPE_CODE">
                <?= Loc::getMessage('MYAPP_CATALOG_OPT_PRICE_TYPE_CODE') ?>
            </label>
            <br>
            <small><?= Loc::getMessage('MYAPP_CATALOG_OPT_PRICE_TYPE_CODE_HINT') ?></small>
        </td>
        <td width="50%" class="adm-detail-content-cell-r">
            <input
                type="text"
                id="MYAPP_CATALOG_PRICE_TYPE_CODE"
                name="<?= htmlspecialcharsbx($optCatalogPriceTypeCode) ?>"
                value="<?= htmlspecialcharsbx($catalogPriceTypeCode) ?>"
                maxlength="32"
            >
        </td>
    </tr>
    <tr>
        <td width="50%" class="adm-detail-content-cell-l" style="vertical-align: top;">
            <?= Loc::getMessage('MYAPP_CATALOG_OPT_FILTER_PROPS') ?>
            <br>
            <small><?= Loc::getMessage('MYAPP_CATALOG_OPT_FILTER_PROPS_HINT') ?></small>
        </td>
        <td width="50%" class="adm-detail-content-cell-r">
            <div id="myapp-catalog-filter-props">
                <?php foreach ($filterPropertyCodes as $propertyCode): ?>
                    <div class="myapp-catalog-filter-prop" style="margin-bottom: 8px;">
                        <input
                            type="text"
                            name="<?= htmlspecialcharsbx($optFilterPropertyCodes) ?>[]"
                            value="<?= htmlspecialcharsbx($propertyCode) ?>"
                            maxlength="50"
                        >
                        <button type="button" class="adm-btn myapp-catalog-filter-prop-remove">
                            <?= Loc::getMessage('MYAPP_CATALOG_OPT_FILTER_PROPS_REMOVE') ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="adm-btn" id="myapp-catalog-filter-prop-add">
                <?= Loc::getMessage('MYAPP_CATALOG_OPT_FILTER_PROPS_ADD') ?>
            </button>
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
<script>
(function () {
    var list = document.getElementById('myapp-catalog-filter-props');
    var addButton = document.getElementById('myapp-catalog-filter-prop-add');
    if (!list || !addButton) {
        return;
    }

    var fieldName = <?= json_encode($optFilterPropertyCodes) ?>;
    var removeLabel = <?= json_encode(Loc::getMessage('MYAPP_CATALOG_OPT_FILTER_PROPS_REMOVE')) ?>;

    function bindRemove(button) {
        button.addEventListener('click', function () {
            var row = button.closest('.myapp-catalog-filter-prop');
            if (row) {
                row.remove();
            }
        });
    }

    list.querySelectorAll('.myapp-catalog-filter-prop-remove').forEach(bindRemove);

    addButton.addEventListener('click', function () {
        var row = document.createElement('div');
        row.className = 'myapp-catalog-filter-prop';
        row.style.marginBottom = '8px';

        var input = document.createElement('input');
        input.type = 'text';
        input.name = fieldName + '[]';
        input.maxLength = 50;

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'adm-btn myapp-catalog-filter-prop-remove';
        button.textContent = removeLabel;
        bindRemove(button);

        row.appendChild(input);
        row.appendChild(document.createTextNode(' '));
        row.appendChild(button);
        list.appendChild(row);
        input.focus();
    });
})();
</script>
