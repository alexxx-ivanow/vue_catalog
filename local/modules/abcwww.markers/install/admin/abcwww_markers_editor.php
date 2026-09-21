<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Abcwww\Markers\Repository;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Access denied');
}

if (!Loader::includeModule('abcwww.markers') || !Loader::includeModule('iblock')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    echo 'Module abcwww.markers is not installed.';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
$sectionId = (int)($_REQUEST['SECTION_ID'] ?? 0);
$scheme = Repository::getScheme($iblockId, $sectionId);

$APPLICATION->SetTitle('Визуальный редактор меток');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if (!$scheme) {
    echo '<div class="adm-info-message-wrap"><div class="adm-info-message">Схема не найдена. Проверьте IBLOCK_ID/SECTION_ID и UF-поля раздела.</div></div>';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

?>
<link rel="stylesheet" href="/bitrix/css/<?= \Abcwww\Markers\Options::MODULE_ID ?>/editor.css">
<div id="abcwww-markers-editor"
     data-iblock-id="<?= (int)$iblockId ?>"
     data-section-id="<?= (int)$sectionId ?>"
     data-sessid="<?= bitrix_sessid() ?>">
    <p><b>Код для вставки на страницу:</b><br><code><?= htmlspecialcharsbx($scheme['snippet']) ?></code></p>
    <div class="abcwww-markers-editor__board">
        <img src="<?= htmlspecialcharsbx($scheme['image']) ?>" class="abcwww-markers-editor__image" alt="">
        <div class="abcwww-markers-editor__layer"></div>
    </div>

    <h3>Список меток</h3>
    <table class="adm-list-table" id="abcwww-markers-editor-table">
        <thead>
        <tr class="adm-list-table-header">
            <td class="adm-list-table-cell">Название</td>
            <td class="adm-list-table-cell">X, %</td>
            <td class="adm-list-table-cell">Y, %</td>
            <td class="adm-list-table-cell">&nbsp;</td>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
<script>
    window.AbcwwwMarkersEditorData = <?= \CUtil::PhpToJSObject($scheme) ?>;
</script>
<style>
    .adm-list-table-header .adm-list-table-cell {
        text-align: center;
    }
</style>
<script src="/bitrix/js/<?= \Abcwww\Markers\Options::MODULE_ID ?>/editor.js"></script>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
